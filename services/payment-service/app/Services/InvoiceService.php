<?php

namespace App\Services;

use App\Models\Invoice;
use App\Events\InvoiceCreated;
use App\Events\InvoiceStatusChanged;
use App\Events\InvoicePaid;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InvoiceService
{
    /**
     * Get invoice by ID.
     */
    public function getInvoice(int $invoiceId): Invoice
    {
        return Invoice::with(['payments'])->findOrFail($invoiceId);
    }

    /**
     * Get invoice by invoice number.
     */
    public function getInvoiceByNumber(string $invoiceNumber): Invoice
    {
        return Invoice::with(['payments'])
                     ->where('invoice_number', $invoiceNumber)
                     ->firstOrFail();
    }

    /**
     * Get customer invoices.
     */
    public function getCustomerInvoices(int $customerId, array $filters = []): Collection
    {
        $query = Invoice::byCustomer($customerId)
                       ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }
        
        return $query->get();
    }

    /**
     * Get merchant invoices.
     */
    public function getMerchantInvoices(int $merchantId, array $filters = []): Collection
    {
        $query = Invoice::byMerchant($merchantId)
                       ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }
        
        return $query->get();
    }

    /**
     * Create invoice from order.
     */
    public function createInvoiceFromOrder(array $orderData): Invoice
    {
        // Validate order data
        $validation = $this->validateOrderData($orderData);
        if (!$validation['valid']) {
            throw new \Exception('Order data validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $invoiceData = [
            'order_id' => $orderData['order_id'],
            'customer_id' => $orderData['customer_id'],
            'merchant_id' => $orderData['merchant_id'],
            'subtotal' => $orderData['part_cost'],
            'delivery_fee' => $orderData['delivery_cost'] ?? 0,
            'platform_fee' => $orderData['platform_fee'] ?? 0,
            'discount_amount' => $orderData['discount_amount'] ?? 0,
            'currency' => $orderData['currency'] ?? 'SAR',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(), // 3 days payment term
            'billing_address' => $orderData['delivery_address'] ?? [],
            'customer_name' => $orderData['customer_name'],
            'customer_email' => $orderData['customer_email'] ?? null,
            'customer_phone' => $orderData['customer_phone'] ?? null,
            'customer_tax_id' => $orderData['customer_tax_id'] ?? null,
            'merchant_name' => $orderData['merchant_name'],
            'merchant_tax_number' => $orderData['merchant_tax_number'] ?? null,
            'merchant_address' => $orderData['merchant_address'] ?? [],
            'line_items' => $this->createLineItemsFromOrder($orderData),
            'notes' => $orderData['notes'] ?? null,
        ];
        
        $invoice = Invoice::create($invoiceData);
        
        // Calculate tax and total
        $invoice->recalculateTotal();
        
        // Submit to ZATCA if merchant has tax number
        if ($invoice->merchant_tax_number) {
            $invoice->submitToZatca();
        }
        
        event(new InvoiceCreated($invoice));
        
        return $invoice;
    }

    /**
     * Create line items from order data.
     */
    private function createLineItemsFromOrder(array $orderData): array
    {
        $lineItems = [];
        
        // Main part item
        $lineItems[] = [
            'id' => \Illuminate\Support\Str::uuid(),
            'description' => $orderData['part_description'] ?? 'Vehicle Part',
            'part_number' => $orderData['part_number'] ?? null,
            'brand' => $orderData['brand'] ?? null,
            'condition' => $orderData['condition'] ?? null,
            'quantity' => 1,
            'unit_price' => $orderData['part_cost'],
            'total_price' => $orderData['part_cost'],
            'warranty_months' => $orderData['warranty_months'] ?? null,
        ];
        
        // Delivery fee as separate line item if applicable
        if (isset($orderData['delivery_cost']) && $orderData['delivery_cost'] > 0) {
            $lineItems[] = [
                'id' => \Illuminate\Support\Str::uuid(),
                'description' => 'Delivery Service',
                'quantity' => 1,
                'unit_price' => $orderData['delivery_cost'],
                'total_price' => $orderData['delivery_cost'],
                'delivery_method' => $orderData['delivery_method'] ?? null,
                'estimated_delivery' => $orderData['estimated_delivery'] ?? null,
            ];
        }
        
        return $lineItems;
    }

    /**
     * Update invoice.
     */
    public function updateInvoice(int $invoiceId, array $data): Invoice
    {
        $invoice = $this->getInvoice($invoiceId);
        
        // Don't allow updates to paid or cancelled invoices
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])) {
            throw new \Exception('Cannot update paid or cancelled invoice');
        }
        
        $invoice->update($data);
        
        // Recalculate totals if financial data changed
        if (array_intersect_key($data, array_flip(['subtotal', 'delivery_fee', 'platform_fee', 'discount_amount']))) {
            $invoice->recalculateTotal();
        }
        
        return $invoice->fresh();
    }

    /**
     * Send invoice to customer.
     */
    public function sendInvoice(int $invoiceId, array $sendOptions = []): Invoice
    {
        $invoice = $this->getInvoice($invoiceId);
        
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new \Exception('Only draft invoices can be sent');
        }
        
        // Here you would integrate with email service
        // For now, we'll simulate sending
        $emailSent = $this->simulateEmailSending($invoice, $sendOptions);
        
        if ($emailSent) {
            $invoice->markAsSent();
            $invoice->addEmailHistory('invoice_sent', $invoice->customer_email ?? 'N/A', true);
        } else {
            $invoice->addEmailHistory('invoice_sent', $invoice->customer_email ?? 'N/A', false, 'Email sending failed');
            throw new \Exception('Failed to send invoice email');
        }
        
        event(new InvoiceStatusChanged($invoice, Invoice::STATUS_DRAFT, Invoice::STATUS_SENT));
        
        return $invoice->fresh();
    }

    /**
     * Simulate email sending (replace with actual email service).
     */
    private function simulateEmailSending(Invoice $invoice, array $options): bool
    {
        // In a real implementation, this would:
        // 1. Generate PDF invoice
        // 2. Send email with PDF attachment
        // 3. Include payment link
        // 4. Track email delivery
        
        return true; // Simulate successful sending
    }

    /**
     * Mark invoice as viewed.
     */
    public function markAsViewed(int $invoiceId): Invoice
    {
        $invoice = $this->getInvoice($invoiceId);
        
        if ($invoice->status === Invoice::STATUS_SENT) {
            $oldStatus = $invoice->status;
            $invoice->markAsViewed();
            
            event(new InvoiceStatusChanged($invoice, $oldStatus, Invoice::STATUS_VIEWED));
        }
        
        return $invoice->fresh();
    }

    /**
     * Cancel invoice.
     */
    public function cancelInvoice(int $invoiceId, string $reason = null): Invoice
    {
        $invoice = $this->getInvoice($invoiceId);
        $oldStatus = $invoice->status;
        
        $invoice->cancel($reason);
        
        event(new InvoiceStatusChanged($invoice, $oldStatus, Invoice::STATUS_CANCELLED));
        
        return $invoice->fresh();
    }

    /**
     * Get invoice statistics.
     */
    public function getInvoiceStats(int $invoiceId): array
    {
        $invoice = $this->getInvoice($invoiceId);
        
        return [
            'invoice_number' => $invoice->invoice_number,
            'status_display' => $invoice->status_display,
            'status_color' => $invoice->status_color,
            'payment_status' => $invoice->payment_status,
            'is_paid' => $invoice->isPaid(),
            'is_overdue' => $invoice->isOverdue(),
            'can_be_paid' => $invoice->canBePaid(),
            'can_be_cancelled' => $invoice->canBeCancelled(),
            'days_until_due' => $invoice->days_until_due,
            'days_overdue' => $invoice->days_overdue,
            'total_paid' => $invoice->total_paid,
            'total_refunded' => $invoice->total_refunded,
            'remaining_balance' => $invoice->remaining_balance,
            'payment_attempts' => $invoice->payments()->count(),
            'zatca_status' => $invoice->zatca_status,
            'emails_sent' => count($invoice->email_history ?? []),
        ];
    }

    /**
     * Get customer invoice statistics.
     */
    public function getCustomerInvoiceStats(int $customerId): array
    {
        $invoices = Invoice::byCustomer($customerId);
        
        return [
            'total_invoices' => $invoices->count(),
            'draft_invoices' => $invoices->byStatus(Invoice::STATUS_DRAFT)->count(),
            'sent_invoices' => $invoices->byStatus(Invoice::STATUS_SENT)->count(),
            'paid_invoices' => $invoices->byStatus(Invoice::STATUS_PAID)->count(),
            'overdue_invoices' => $invoices->overdue()->count(),
            'cancelled_invoices' => $invoices->byStatus(Invoice::STATUS_CANCELLED)->count(),
            'total_amount_invoiced' => $invoices->sum('total_amount'),
            'total_amount_paid' => $invoices->paid()->sum('total_amount'),
            'total_outstanding' => $invoices->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_VIEWED,
                Invoice::STATUS_OVERDUE
            ])->sum('total_amount'),
            'average_payment_time' => $this->calculateAveragePaymentTime($customerId),
        ];
    }

    /**
     * Get merchant invoice statistics.
     */
    public function getMerchantInvoiceStats(int $merchantId): array
    {
        $invoices = Invoice::byMerchant($merchantId);
        
        return [
            'total_invoices' => $invoices->count(),
            'draft_invoices' => $invoices->byStatus(Invoice::STATUS_DRAFT)->count(),
            'sent_invoices' => $invoices->byStatus(Invoice::STATUS_SENT)->count(),
            'paid_invoices' => $invoices->byStatus(Invoice::STATUS_PAID)->count(),
            'overdue_invoices' => $invoices->overdue()->count(),
            'total_revenue' => $invoices->paid()->sum('subtotal'),
            'total_fees_earned' => $invoices->paid()->sum('platform_fee'),
            'total_taxes_collected' => $invoices->paid()->sum('tax_amount'),
            'average_invoice_value' => $invoices->avg('total_amount'),
            'payment_collection_rate' => $this->calculatePaymentCollectionRate($merchantId),
        ];
    }

    /**
     * Calculate average payment time for customer.
     */
    private function calculateAveragePaymentTime(int $customerId): ?float
    {
        $paidInvoices = Invoice::byCustomer($customerId)
                              ->paid()
                              ->whereNotNull('sent_at')
                              ->whereNotNull('paid_at')
                              ->get();
        
        if ($paidInvoices->isEmpty()) {
            return null;
        }
        
        $totalDays = $paidInvoices->sum(function ($invoice) {
            return $invoice->sent_at->diffInDays($invoice->paid_at);
        });
        
        return round($totalDays / $paidInvoices->count(), 1);
    }

    /**
     * Calculate payment collection rate for merchant.
     */
    private function calculatePaymentCollectionRate(int $merchantId): float
    {
        $totalInvoices = Invoice::byMerchant($merchantId)
                               ->whereIn('status', [
                                   Invoice::STATUS_SENT,
                                   Invoice::STATUS_VIEWED,
                                   Invoice::STATUS_PAID,
                                   Invoice::STATUS_OVERDUE
                               ])
                               ->count();
        
        if ($totalInvoices === 0) {
            return 0.0;
        }
        
        $paidInvoices = Invoice::byMerchant($merchantId)
                              ->paid()
                              ->count();
        
        return round(($paidInvoices / $totalInvoices) * 100, 2);
    }

    /**
     * Get overdue invoices.
     */
    public function getOverdueInvoices(): Collection
    {
        return Invoice::overdue()
                     ->with(['customer', 'merchant'])
                     ->orderBy('due_date', 'asc')
                     ->get();
    }

    /**
     * Process automatic invoice status updates.
     */
    public function processAutomaticStatusUpdates(): array
    {
        $results = [
            'marked_overdue' => 0,
            'auto_cancelled' => 0,
        ];
        
        // Mark invoices as overdue
        $overdueInvoices = Invoice::whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED])
                                 ->where('due_date', '<', now()->toDateString())
                                 ->get();
        
        foreach ($overdueInvoices as $invoice) {
            $invoice->updateStatus(Invoice::STATUS_OVERDUE, 'Automatically marked as overdue');
            $results['marked_overdue']++;
        }
        
        // Auto-cancel very old overdue invoices (after 30 days)
        $veryOverdueInvoices = Invoice::overdue()
                                     ->where('due_date', '<', now()->subDays(30)->toDateString())
                                     ->get();
        
        foreach ($veryOverdueInvoices as $invoice) {
            $invoice->cancel('Automatically cancelled - overdue for 30+ days');
            $results['auto_cancelled']++;
        }
        
        return $results;
    }

    /**
     * Generate invoice PDF data.
     */
    public function generateInvoicePdfData(int $invoiceId): array
    {
        $invoice = $this->getInvoice($invoiceId);
        
        return [
            'invoice' => $invoice->toArray(),
            'company_info' => [
                'name' => config('app.name'),
                'address' => config('company.address'),
                'phone' => config('company.phone'),
                'email' => config('company.email'),
                'website' => config('app.url'),
            ],
            'qr_code_data' => $invoice->zatca_qr_code,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Search invoices.
     */
    public function searchInvoices(array $filters): Collection
    {
        $query = Invoice::with(['payments']);
        
        if (isset($filters['invoice_number'])) {
            $query->where('invoice_number', 'like', '%' . $filters['invoice_number'] . '%');
        }
        
        if (isset($filters['customer_id'])) {
            $query->byCustomer($filters['customer_id']);
        }
        
        if (isset($filters['merchant_id'])) {
            $query->byMerchant($filters['merchant_id']);
        }
        
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }
        
        if (isset($filters['amount_min'])) {
            $query->where('total_amount', '>=', $filters['amount_min']);
        }
        
        if (isset($filters['amount_max'])) {
            $query->where('total_amount', '<=', $filters['amount_max']);
        }
        
        if (isset($filters['zatca_status'])) {
            $query->where('zatca_status', $filters['zatca_status']);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Validate order data for invoice creation.
     */
    private function validateOrderData(array $orderData): array
    {
        $errors = [];
        
        // Required fields
        $requiredFields = ['order_id', 'customer_id', 'merchant_id', 'part_cost', 'customer_name', 'merchant_name'];
        
        foreach ($requiredFields as $field) {
            if (empty($orderData[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        
        // Validate amounts
        if (isset($orderData['part_cost']) && $orderData['part_cost'] <= 0) {
            $errors[] = 'Part cost must be greater than zero';
        }
        
        if (isset($orderData['delivery_cost']) && $orderData['delivery_cost'] < 0) {
            $errors[] = 'Delivery cost cannot be negative';
        }
        
        if (isset($orderData['platform_fee']) && $orderData['platform_fee'] < 0) {
            $errors[] = 'Platform fee cannot be negative';
        }
        
        // Validate email format if provided
        if (isset($orderData['customer_email']) && !filter_var($orderData['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid customer email format';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Resend invoice.
     */
    public function resendInvoice(int $invoiceId): Invoice
    {
        $invoice = $this->getInvoice($invoiceId);
        
        if (!in_array($invoice->status, [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED, Invoice::STATUS_OVERDUE])) {
            throw new \Exception('Invoice cannot be resent in current status');
        }
        
        // Simulate resending
        $emailSent = $this->simulateEmailSending($invoice, ['type' => 'resend']);
        
        if ($emailSent) {
            $invoice->addEmailHistory('invoice_resent', $invoice->customer_email ?? 'N/A', true);
        } else {
            $invoice->addEmailHistory('invoice_resent', $invoice->customer_email ?? 'N/A', false, 'Email resending failed');
            throw new \Exception('Failed to resend invoice email');
        }
        
        return $invoice->fresh();
    }
}

