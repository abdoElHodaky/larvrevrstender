<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Shared\Services\FileUploadService;

class ImageUploadController extends Controller
{
    public function __construct(
        protected FileUploadService$fileUploadService
    ) {
    }

    /**
     * Upload images for an auction.
     */
    public function uploadImages(Request $request, int $auctionId): JsonResponse
    {
        try {
            // Verify auction exists
            $auction = Auction::findOrFail($auctionId);

            $validated = $request->validate([
                'images' => 'required|array|min:1|max:10',
                'images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240', // 10MB max
                'alt_texts' => 'nullable|array',
                'alt_texts.*' => 'nullable|string|max:255',
                'is_primary' => 'nullable|array',
                'is_primary.*' => 'boolean',
                'optimize' => 'nullable|boolean',
                'generate_thumbnails' => 'nullable|boolean',
            ]);

            $images = $validated['images'];
            $altTexts = $validated['alt_texts'] ?? [];
            $isPrimaryFlags = $validated['is_primary'] ?? [];
            $optimize = $validated['optimize'] ?? true;
            $generateThumbnails = $validated['generate_thumbnails'] ?? true;

            // Check current image count
            $currentImageCount = $auction->productImages()->count();
            $maxImages = config('auction.max_images_per_auction', 20);

            if ($currentImageCount + count($images) > $maxImages) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot upload more than {$maxImages} images per auction"
                ], 422);
            }

            $uploadedImages = [];
            $errors = [];

            foreach ($images as $index => $image) {
                try {
                    // Upload options
                    $uploadOptions = [
                        'optimize' => $optimize,
                        'generate_thumbnails' => $generateThumbnails,
                        'max_width' => 2048,
                        'max_height' => 2048,
                        'quality' => 85,
                        'thumbnails' => [
                            'small' => ['width' => 300, 'height' => 300],
                            'medium' => ['width' => 600, 'height' => 600],
                            'large' => ['width' => 1200, 'height' => 1200],
                        ]
                    ];

                    // Upload to storage
                    $uploadResult = $this->fileUploadService->upload(
                        $image,
                        "auctions/{$auctionId}/images",
                        $uploadOptions
                    );

                    if (!$uploadResult['success']) {
                        $errors[$index] = 'Failed to upload image';
                        continue;
                    }

                    // Determine if this should be primary image
                    $isPrimary = $isPrimaryFlags[$index] ?? false;
                    
                    // If this is the first image and no primary is set, make it primary
                    if ($currentImageCount === 0 && $index === 0 && !$isPrimary) {
                        $isPrimary = true;
                    }

                    // If setting as primary, unset other primary images
                    if ($isPrimary) {
                        ProductImage::where('auction_id', $auctionId)
                            ->where('is_primary', true)
                            ->update(['is_primary' => false]);
                    }

                    // Create ProductImage record
                    $productImage = ProductImage::create([
                        'auction_id' => $auctionId,
                        'file_path' => $uploadResult['path'],
                        'file_name' => $uploadResult['filename'],
                        'original_name' => $uploadResult['original_name'],
                        'file_size' => $uploadResult['size'],
                        'mime_type' => $uploadResult['mime_type'],
                        'storage_provider' => config('filesystems.default', 's3'),
                        'url' => $uploadResult['urls']['public'] ?? $uploadResult['urls']['url'],
                        'alt_text' => $altTexts[$index] ?? null,
                        'is_primary' => $isPrimary,
                        'width' => $uploadResult['metadata']['width'] ?? null,
                        'height' => $uploadResult['metadata']['height'] ?? null,
                    ]);

                    $uploadedImages[] = $productImage;

                } catch (\Exception $e) {
                    $errors[$index] = $e->getMessage();
                }
            }

            $successCount = count($uploadedImages);
            $errorCount = count($errors);

            return response()->json([
                'success' => $errorCount === 0,
                'message' => $errorCount === 0 
                    ? "Successfully uploaded {$successCount} images"
                    : "Uploaded {$successCount} images with {$errorCount} errors",
                'data' => [
                    'uploaded_images' => $uploadedImages,
                    'total_uploaded' => $successCount,
                    'total_errors' => $errorCount,
                    'errors' => $errors,
                ]
            ], $errorCount === 0 ? 201 : 207); // 207 = Multi-Status

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a single image for an auction.
     */
    public function uploadSingleImage(Request $request, int $auctionId): JsonResponse
    {
        try {
            // Verify auction exists
            $auction = Auction::findOrFail($auctionId);

            $validated = $request->validate([
                'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240', // 10MB max
                'alt_text' => 'nullable|string|max:255',
                'is_primary' => 'nullable|boolean',
                'optimize' => 'nullable|boolean',
                'generate_thumbnails' => 'nullable|boolean',
            ]);

            // Check current image count
            $currentImageCount = $auction->productImages()->count();
            $maxImages = config('auction.max_images_per_auction', 20);

            if ($currentImageCount >= $maxImages) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot upload more than {$maxImages} images per auction"
                ], 422);
            }

            $image = $validated['image'];
            $altText = $validated['alt_text'] ?? null;
            $isPrimary = $validated['is_primary'] ?? false;
            $optimize = $validated['optimize'] ?? true;
            $generateThumbnails = $validated['generate_thumbnails'] ?? true;

            // If this is the first image, make it primary
            if ($currentImageCount === 0) {
                $isPrimary = true;
            }

            // Upload options
            $uploadOptions = [
                'optimize' => $optimize,
                'generate_thumbnails' => $generateThumbnails,
                'max_width' => 2048,
                'max_height' => 2048,
                'quality' => 85,
                'thumbnails' => [
                    'small' => ['width' => 300, 'height' => 300],
                    'medium' => ['width' => 600, 'height' => 600],
                    'large' => ['width' => 1200, 'height' => 1200],
                ]
            ];

            // Upload to storage
            $uploadResult = $this->fileUploadService->upload(
                $image,
                "auctions/{$auctionId}/images",
                $uploadOptions
            );

            if (!$uploadResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload image'
                ], 500);
            }

            // If setting as primary, unset other primary images
            if ($isPrimary) {
                ProductImage::where('auction_id', $auctionId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            // Create ProductImage record
            $productImage = ProductImage::create([
                'auction_id' => $auctionId,
                'file_path' => $uploadResult['path'],
                'file_name' => $uploadResult['filename'],
                'original_name' => $uploadResult['original_name'],
                'file_size' => $uploadResult['size'],
                'mime_type' => $uploadResult['mime_type'],
                'storage_provider' => config('filesystems.default', 's3'),
                'url' => $uploadResult['urls']['public'] ?? $uploadResult['urls']['url'],
                'alt_text' => $altText,
                'is_primary' => $isPrimary,
                'width' => $uploadResult['metadata']['width'] ?? null,
                'height' => $uploadResult['metadata']['height'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => $productImage
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all images for an auction.
     */
    public function getAuctionImages(int $auctionId): JsonResponse
    {
        try {
            // Verify auction exists
            $auction = Auction::findOrFail($auctionId);

            $images = $auction->productImages()->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => $images,
                'meta' => [
                    'total' => $images->count(),
                    'primary_image' => $images->where('is_primary', true)->first(),
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve auction images',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update image metadata.
     */
    public function updateImage(Request $request, int $auctionId, int $imageId): JsonResponse
    {
        try {
            // Verify auction exists
            $auction = Auction::findOrFail($auctionId);

            // Find the image
            $image = ProductImage::where('auction_id', $auctionId)
                ->where('id', $imageId)
                ->firstOrFail();

            $validated = $request->validate([
                'alt_text' => 'nullable|string|max:255',
                'is_primary' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:1',
            ]);

            // If setting as primary, unset other primary images
            if (isset($validated['is_primary']) && $validated['is_primary']) {
                ProductImage::where('auction_id', $auctionId)
                    ->where('id', '!=', $imageId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $image->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Image updated successfully',
                'data' => $image->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction or image not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an image.
     */
    public function deleteImage(int $auctionId, int $imageId): JsonResponse
    {
        try {
            // Verify auction exists
            $auction = Auction::findOrFail($auctionId);

            // Find the image
            $image = ProductImage::where('auction_id', $auctionId)
                ->where('id', $imageId)
                ->firstOrFail();

            $wasPrimary = $image->is_primary;

            // Delete the image (this will also delete from cloud storage via model event)
            $image->delete();

            // If this was the primary image, set another image as primary
            if ($wasPrimary) {
                $nextImage = ProductImage::where('auction_id', $auctionId)
                    ->ordered()
                    ->first();

                if ($nextImage) {
                    $nextImage->update(['is_primary' => true]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction or image not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder images for an auction.
     */
    public function reorderImages(Request $request, int $auctionId): JsonResponse
    {
        try {
            // Verify auction exists
            $auction = Auction::findOrFail($auctionId);

            $validated = $request->validate([
                'image_orders' => 'required|array',
                'image_orders.*.id' => 'required|integer|exists:product_images,id',
                'image_orders.*.sort_order' => 'required|integer|min:1',
            ]);

            $imageOrders = $validated['image_orders'];

            foreach ($imageOrders as $order) {
                ProductImage::where('auction_id', $auctionId)
                    ->where('id', $order['id'])
                    ->update(['sort_order' => $order['sort_order']]);
            }

            $updatedImages = $auction->productImages()->ordered()->get();

            return response()->json([
                'success' => true,
                'message' => 'Images reordered successfully',
                'data' => $updatedImages
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder images',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
