<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ZatcaInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ZatcaService
{
    private string $apiUrl;
    private string $apiKey;
    private string $certificatePath;
    private string $privateKeyPath;

    public function __construct()
    {
        $this->apiUrl = config('services.zatca.api_url');
        $this->apiKey = config('services.zatca.api_key');
        $this->certificatePath = config('services.zatca.certificate_path');
        $this->privateKeyPath = config('services.zatca.private_key_path');
    }

    /**
     * Generate ZATCA compliant e-invoice
     */
    public function generateInvoice(Payment $payment): ZatcaInvoice
    {
        try {
            // Generate unique invoice number
            $invoiceNumber = $this->generateInvoiceNumber();
            
            // Generate ZATCA UUID
            $zatcaUuid = Str::uuid()->toString();

            // Prepare invoice data
            $invoiceData = $this->prepareInvoiceData($payment, $invoiceNumber, $zatcaUuid);

            // Generate QR code
            $qrCode = $this->generateQrCode($invoiceData);

            // Create ZATCA invoice record
            $zatcaInvoice = ZatcaInvoice::create([
                'payment_id' => $payment->id,
                'invoice_number' => $invoiceNumber,
                'zatca_uuid' => $zatcaUuid,
                'qr_code' => $qrCode,
                'invoice_data' => $invoiceData,
                'status' => 'draft'
            ]);

            Log::info('ZATCA invoice generated', [
                'payment_id' => $payment->id,
                'invoice_number' => $invoiceNumber,
                'zatca_uuid' => $zatcaUuid
            ]);

            return $zatcaInvoice;

        } catch (\Exception $e) {
            Log::error('Failed to generate ZATCA invoice', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Submit invoice to ZATCA
     */
    public function submitInvoice(ZatcaInvoice $zatcaInvoice): array
    {
        try {
            // Prepare submission data
            $submissionData = $this->prepareSubmissionData($zatcaInvoice);

            // Submit to ZATCA API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->withOptions([
                'cert' => $this->certificatePath,
                'ssl_key' => $this->privateKeyPath,
                'verify' => true
            ])
            ->post($this->apiUrl . '/invoices', $submissionData);

            $responseData = $response->json();

            // Update invoice status based on response
            if ($response->successful()) {
                $zatcaInvoice->update([
                    'status' => 'submitted',
                    'zatca_response' => $responseData,
                    'submitted_at' => now()
                ]);

                Log::info('ZATCA invoice submitted successfully', [
                    'invoice_id' => $zatcaInvoice->id,
                    'zatca_uuid' => $zatcaInvoice->zatca_uuid
                ]);
            } else {
                $zatcaInvoice->update([
                    'status' => 'failed',
                    'zatca_response' => $responseData
                ]);

                Log::error('ZATCA invoice submission failed', [
                    'invoice_id' => $zatcaInvoice->id,
                    'error' => $responseData
                ]);
            }

            return $responseData;

        } catch (\Exception $e) {
            $zatcaInvoice->update([
                'status' => 'failed',
                'zatca_response' => ['error' => $e->getMessage()]
            ]);

            Log::error('ZATCA invoice submission error', [
                'invoice_id' => $zatcaInvoice->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Check invoice status with ZATCA
     */
    public function checkInvoiceStatus(ZatcaInvoice $zatcaInvoice): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json'
            ])
            ->withOptions([
                'cert' => $this->certificatePath,
                'ssl_key' => $this->privateKeyPath,
                'verify' => true
            ])
            ->get($this->apiUrl . '/invoices/' . $zatcaInvoice->zatca_uuid);

            $responseData = $response->json();

            if ($response->successful()) {
                // Update status based on ZATCA response
                $newStatus = $this->mapZatcaStatus($responseData['status'] ?? 'unknown');
                
                $zatcaInvoice->update([
                    'status' => $newStatus,
                    'zatca_response' => $responseData,
                    'approved_at' => $newStatus === 'approved' ? now() : null
                ]);

                Log::info('ZATCA invoice status updated', [
                    'invoice_id' => $zatcaInvoice->id,
                    'status' => $newStatus
                ]);
            }

            return $responseData;

        } catch (\Exception $e) {
            Log::error('Failed to check ZATCA invoice status', [
                'invoice_id' => $zatcaInvoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate Saudi National ID
     */
    public function validateNationalId(string $nationalId): bool
    {
        // Saudi National ID validation logic
        if (strlen($nationalId) !== 10) {
            return false;
        }

        if (!ctype_digit($nationalId)) {
            return false;
        }

        // Check if it starts with 1 or 2 (Saudi nationals)
        if (!in_array($nationalId[0], ['1', '2'])) {
            return false;
        }

        // Luhn algorithm check
        return $this->luhnCheck($nationalId);
    }

    /**
     * Validate Saudi tax number
     */
    public function validateTaxNumber(string $taxNumber): bool
    {
        // Saudi tax number validation (15 digits)
        if (strlen($taxNumber) !== 15) {
            return false;
        }

        if (!ctype_digit($taxNumber)) {
            return false;
        }

        // Must end with 03 for VAT registered entities
        return substr($taxNumber, -2) === '03';
    }

    /**
     * Calculate VAT amount
     */
    public function calculateVat(float $amount, float $vatRate = 0.15): float
    {
        return round($amount * $vatRate, 2);
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'RT'; // Reverse Tender
        $year = date('Y');
        $month = date('m');
        
        // Get next sequence number for this month
        $lastInvoice = ZatcaInvoice::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastInvoice ? 
            (int) substr($lastInvoice->invoice_number, -6) + 1 : 1;

        return sprintf('%s%s%s%06d', $prefix, $year, $month, $sequence);
    }

    /**
     * Prepare invoice data for ZATCA
     */
    private function prepareInvoiceData(Payment $payment, string $invoiceNumber, string $zatcaUuid): array
    {
        $order = $payment->order;
        $customer = $order->customer;
        $merchant = $payment->payee->merchantProfile;

        $subtotal = $payment->amount - $payment->tax_amount;
        $vatAmount = $payment->tax_amount;

        return [
            'invoice_number' => $invoiceNumber,
            'uuid' => $zatcaUuid,
            'issue_date' => now()->format('Y-m-d'),
            'issue_time' => now()->format('H:i:s'),
            'invoice_type' => 'standard',
            'currency' => $payment->currency,
            
            // Supplier (Merchant) information
            'supplier' => [
                'name' => $merchant->business_name,
                'tax_number' => $merchant->tax_number,
                'address' => $merchant->business_address ?? '',
                'city' => $merchant->business_city ?? '',
                'postal_code' => $merchant->business_postal_code ?? '',
                'country' => 'SA'
            ],
            
            // Customer information
            'customer' => [
                'name' => $customer->user->name,
                'national_id' => $customer->national_id,
                'address' => $customer->national_address ?? '',
                'city' => $customer->city ?? '',
                'postal_code' => $customer->postal_code ?? '',
                'country' => 'SA'
            ],
            
            // Invoice lines
            'lines' => [
                [
                    'description' => $order->title,
                    'quantity' => 1,
                    'unit_price' => $subtotal,
                    'line_total' => $subtotal,
                    'vat_rate' => 0.15,
                    'vat_amount' => $vatAmount
                ]
            ],
            
            // Totals
            'subtotal' => $subtotal,
            'vat_total' => $vatAmount,
            'total' => $payment->amount,
            
            // Additional data
            'payment_method' => $payment->payment_method,
            'order_reference' => $order->order_number,
            'notes' => 'Reverse Tender Platform - Auto Parts Service'
        ];
    }

    /**
     * Generate QR code data
     */
    private function generateQrCode(array $invoiceData): string
    {
        $qrData = [
            'seller_name' => $invoiceData['supplier']['name'],
            'tax_number' => $invoiceData['supplier']['tax_number'],
            'timestamp' => $invoiceData['issue_date'] . 'T' . $invoiceData['issue_time'],
            'total' => $invoiceData['total'],
            'vat_total' => $invoiceData['vat_total']
        ];

        return base64_encode(json_encode($qrData));
    }

    /**
     * Prepare submission data for ZATCA API
     */
    private function prepareSubmissionData(ZatcaInvoice $zatcaInvoice): array
    {
        return [
            'uuid' => $zatcaInvoice->zatca_uuid,
            'invoice_data' => $zatcaInvoice->invoice_data,
            'qr_code' => $zatcaInvoice->qr_code,
            'submission_type' => 'standard'
        ];
    }

    /**
     * Map ZATCA status to internal status
     */
    private function mapZatcaStatus(string $zatcaStatus): string
    {
        return match($zatcaStatus) {
            'ACCEPTED' => 'approved',
            'REJECTED' => 'rejected',
            'PENDING' => 'submitted',
            'CANCELLED' => 'cancelled',
            default => 'unknown'
        };
    }

    /**
     * Luhn algorithm check for National ID validation
     */
    private function luhnCheck(string $number): bool
    {
        $sum = 0;
        $alternate = false;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];
            
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }
            
            $sum += $digit;
            $alternate = !$alternate;
        }
        
        return ($sum % 10) === 0;
    }
}

