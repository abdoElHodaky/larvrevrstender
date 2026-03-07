<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\BidEvaluation;
use App\RPC\Adapters\UserServiceAdapter;
use Illuminate\Support\Facades\Log;
use Shared\Core\BaseService;
use App\Services\Contracts\BidEvaluationServiceInterface;

/**
 * Bid Evaluation Service
 * 
 * Handles the scoring and evaluation of individual bids based on multiple criteria.
 */
class BidEvaluationService extends BaseService
{
    private UserServiceAdapter $userService;

    public function __construct(UserServiceAdapter $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Evaluate a bid against auction criteria.
     */
    public function evaluateBid(Bid $bid, Auction $auction): BidEvaluation
    {
        $evaluation = BidEvaluation::create([
            'bid_id' => $bid->id,
            'auction_id' => $auction->id,
            'evaluator_id' => null, // System evaluation
            'evaluation_status' => BidEvaluation::STATUS_PENDING,
        ]);

        // Score each criterion
        $evaluation->price_score = $this->scorePriceCriterion($bid, $auction);
        $evaluation->delivery_score = $this->scoreDeliveryTimeCriterion($bid, $auction);
        $evaluation->quality_score = $this->scoreQualitySpecificationCriterion($bid, $auction);
        $evaluation->supplier_score = $this->scoreSupplierRatingCriterion($bid);
        $evaluation->technical_score = $this->scoreTechnicalComplianceCriterion($bid, $auction);
        $evaluation->compliance_score = $this->scoreRegulatoryComplianceCriterion($bid, $auction);

        // Calculate composite score
        $evaluation->updateCompositeScore();
        
        // Mark as completed
        $evaluation->markCompleted();

        Log::info('Bid evaluation completed', [
            'bid_id' => $bid->id,
            'auction_id' => $auction->id,
            'composite_score' => $evaluation->composite_score,
            'individual_scores' => [
                'price' => $evaluation->price_score,
                'delivery' => $evaluation->delivery_score,
                'quality' => $evaluation->quality_score,
                'supplier' => $evaluation->supplier_score,
                'technical' => $evaluation->technical_score,
                'compliance' => $evaluation->compliance_score,
            ]
        ]);

        return $evaluation;
    }

    /**
     * Score bid based on price competitiveness.
     * Lower price = higher score (reverse auction logic)
     */
    private function scorePriceCriterion(Bid $bid, Auction $auction): float
    {
        try {
            // Get all bids for this auction to calculate relative pricing
            $allBids = Bid::where('auction_id', $auction->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->pluck('amount');

            if ($allBids->isEmpty()) {
                return 50.0; // Neutral score if no other bids
            }

            $minBid = $allBids->min();
            $maxBid = $allBids->max();
            
            // If all bids are the same price
            if ($minBid == $maxBid) {
                return 100.0;
            }

            // Calculate score: lower price gets higher score
            // Score = 100 - ((bid_amount - min_bid) / (max_bid - min_bid)) * 100
            $priceRange = $maxBid - $minBid;
            $bidPosition = $bid->amount - $minBid;
            $score = 100 - (($bidPosition / $priceRange) * 100);

            // Ensure score is between 0 and 100
            return max(0, min(100, $score));

        } catch (\Exception $e) {
            Log::warning('Failed to score price criterion', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage()
            ]);
            return 50.0; // Default neutral score
        }
    }

    /**
     * Score bid based on delivery timeline.
     * Faster delivery = higher score
     */
    private function scoreDeliveryTimeCriterion(Bid $bid, Auction $auction): float
    {
        try {
            // Extract delivery timeline from bid metadata or notes
            $deliveryDays = $this->extractDeliveryDays($bid);
            
            if ($deliveryDays === null) {
                return 50.0; // Neutral score if delivery time not specified
            }

            // Score based on delivery speed
            // 1-7 days: 100 points
            // 8-14 days: 80 points
            // 15-30 days: 60 points
            // 31-60 days: 40 points
            // 61+ days: 20 points
            
            if ($deliveryDays <= 7) {
                return 100.0;
            } elseif ($deliveryDays <= 14) {
                return 80.0;
            } elseif ($deliveryDays <= 30) {
                return 60.0;
            } elseif ($deliveryDays <= 60) {
                return 40.0;
            } else {
                return 20.0;
            }

        } catch (\Exception $e) {
            Log::warning('Failed to score delivery criterion', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage()
            ]);
            return 50.0;
        }
    }

    /**
     * Score bid based on quality specifications compliance.
     */
    private function scoreQualitySpecificationCriterion(Bid $bid, Auction $auction): float
    {
        try {
            $qualityScore = 50.0; // Base score

            // Check for quality certifications in attachments
            $certificationScore = $this->evaluateQualityCertifications($bid);
            
            // Check for detailed specifications in bid notes
            $specificationScore = $this->evaluateSpecificationDetail($bid);
            
            // Check for warranty/guarantee information
            $warrantyScore = $this->evaluateWarrantyOffering($bid);

            // Combine scores (weighted average)
            $qualityScore = ($certificationScore * 0.5) + 
                           ($specificationScore * 0.3) + 
                           ($warrantyScore * 0.2);

            return max(0, min(100, $qualityScore));

        } catch (\Exception $e) {
            Log::warning('Failed to score quality criterion', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage()
            ]);
            return 50.0;
        }
    }

    /**
     * Score bid based on supplier reputation and rating.
     */
    private function scoreSupplierRatingCriterion(Bid $bid): float
    {
        try {
            // Get supplier profile from user service
            $supplierProfile = $this->userService->getMerchantProfile($bid->user_id);
            
            if (!$supplierProfile || !isset($supplierProfile['rating'])) {
                return 30.0; // Low score for unrated suppliers
            }

            $rating = $supplierProfile['rating'];
            $totalReviews = $supplierProfile['total_reviews'] ?? 0;
            $verified = $supplierProfile['verified'] ?? false;

            // Base score from rating (0-5 scale converted to 0-100)
            $ratingScore = ($rating / 5.0) * 100;

            // Bonus for verification status
            $verificationBonus = $verified ? 10.0 : 0.0;

            // Bonus for review count (more reviews = more reliable rating)
            $reviewBonus = min(10.0, $totalReviews / 10); // Max 10 points for 100+ reviews

            $totalScore = $ratingScore + $verificationBonus + $reviewBonus;

            return max(0, min(100, $totalScore));

        } catch (\Exception $e) {
            Log::warning('Failed to score supplier criterion', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage()
            ]);
            return 30.0; // Conservative score for unknown suppliers
        }
    }

    /**
     * Score bid based on technical compliance.
     */
    private function scoreTechnicalComplianceCriterion(Bid $bid, Auction $auction): float
    {
        try {
            $complianceScore = 50.0; // Base score

            // Check for technical documentation
            $technicalDocs = $bid->attachments()
                ->whereIn('attachment_type', ['technical_spec', 'certification'])
                ->count();

            // Score based on documentation completeness
            if ($technicalDocs >= 3) {
                $complianceScore = 90.0;
            } elseif ($technicalDocs >= 2) {
                $complianceScore = 75.0;
            } elseif ($technicalDocs >= 1) {
                $complianceScore = 60.0;
            } else {
                $complianceScore = 30.0;
            }

            // Check for detailed technical notes
            if (!empty($bid->notes) && strlen($bid->notes) > 100) {
                $complianceScore += 10.0; // Bonus for detailed notes
            }

            return max(0, min(100, $complianceScore));

        } catch (\Exception $e) {
            Log::warning('Failed to score technical criterion', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage()
            ]);
            return 50.0;
        }
    }

    /**
     * Score bid based on regulatory compliance.
     */
    private function scoreRegulatoryComplianceCriterion(Bid $bid, Auction $auction): float
    {
        try {
            $complianceScore = 50.0; // Base score

            // Check for compliance documents
            $complianceDocs = $bid->attachments()
                ->whereIn('attachment_type', ['license', 'insurance', 'certification'])
                ->count();

            // Check supplier verification status
            $supplierProfile = $this->userService->getMerchantProfile($bid->user_id);
            $isVerified = $supplierProfile['verified'] ?? false;

            // Score based on compliance documentation
            if ($complianceDocs >= 2 && $isVerified) {
                $complianceScore = 95.0;
            } elseif ($complianceDocs >= 2) {
                $complianceScore = 80.0;
            } elseif ($complianceDocs >= 1 && $isVerified) {
                $complianceScore = 75.0;
            } elseif ($complianceDocs >= 1) {
                $complianceScore = 60.0;
            } elseif ($isVerified) {
                $complianceScore = 55.0;
            } else {
                $complianceScore = 25.0;
            }

            return max(0, min(100, $complianceScore));

        } catch (\Exception $e) {
            Log::warning('Failed to score compliance criterion', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage()
            ]);
            return 50.0;
        }
    }

    /**
     * Extract delivery days from bid metadata or notes.
     */
    private function extractDeliveryDays(Bid $bid): ?int
    {
        // Check metadata first
        if (isset($bid->metadata['delivery_days'])) {
            return (int) $bid->metadata['delivery_days'];
        }

        // Parse from notes using regex
        if (!empty($bid->notes)) {
            $patterns = [
                '/(\d+)\s*days?/i',
                '/(\d+)\s*day\s*delivery/i',
                '/delivery\s*in\s*(\d+)\s*days?/i',
                '/(\d+)\s*working\s*days?/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $bid->notes, $matches)) {
                    return (int) $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * Evaluate quality certifications from attachments.
     */
    private function evaluateQualityCertifications(Bid $bid): float
    {
        $certifications = $bid->attachments()
            ->where('attachment_type', 'certification')
            ->count();

        if ($certifications >= 3) return 90.0;
        if ($certifications >= 2) return 75.0;
        if ($certifications >= 1) return 60.0;
        return 30.0;
    }

    /**
     * Evaluate specification detail in bid notes.
     */
    private function evaluateSpecificationDetail(Bid $bid): float
    {
        if (empty($bid->notes)) return 30.0;

        $noteLength = strlen($bid->notes);
        
        if ($noteLength > 500) return 90.0;
        if ($noteLength > 200) return 70.0;
        if ($noteLength > 100) return 50.0;
        return 30.0;
    }

    /**
     * Evaluate warranty offering from bid information.
     */
    private function evaluateWarrantyOffering(Bid $bid): float
    {
        if (empty($bid->notes)) return 40.0;

        $warrantyKeywords = ['warranty', 'guarantee', 'replacement', 'support'];
        $notes = strtolower($bid->notes);
        
        $warrantyMentions = 0;
        foreach ($warrantyKeywords as $keyword) {
            if (strpos($notes, $keyword) !== false) {
                $warrantyMentions++;
            }
        }

        if ($warrantyMentions >= 3) return 90.0;
        if ($warrantyMentions >= 2) return 70.0;
        if ($warrantyMentions >= 1) return 55.0;
        return 40.0;
    }

    /**
     * Get evaluation criteria weights for an auction.
     */
    public function getEvaluationCriteria(Auction $auction): array
    {
        // Check if auction has custom criteria weights
        if (isset($auction->metadata['evaluation_criteria'])) {
            return $auction->metadata['evaluation_criteria'];
        }

        // Return default weights
        return BidEvaluation::DEFAULT_CRITERIA_WEIGHTS;
    }

    /**
     * Validate bid meets minimum requirements.
     */
    public function validateBidRequirements(Bid $bid, Auction $auction): array
    {
        $errors = [];

        // Check minimum bid amount
        if ($bid->amount < $auction->starting_price) {
            $errors[] = 'Bid amount is below minimum starting price';
        }

        // Check required attachments
        $requiredAttachments = $auction->metadata['required_attachments'] ?? [];
        foreach ($requiredAttachments as $requiredType) {
            $hasAttachment = $bid->attachments()
                ->where('attachment_type', $requiredType)
                ->exists();
            
            if (!$hasAttachment) {
                $errors[] = "Missing required attachment: {$requiredType}";
            }
        }

        // Check supplier verification if required
        if ($auction->metadata['require_verified_supplier'] ?? false) {
            $supplierProfile = $this->userService->getMerchantProfile($bid->user_id);
            if (!($supplierProfile['verified'] ?? false)) {
                $errors[] = 'Supplier must be verified to bid on this auction';
            }
        }

        return $errors;
    }
}
