<?php

namespace App\Services\Contracts;

/**
 * Invoice Service Contract
 * 
 * Defines the interface for invoice management services
 */
interface InvoiceServiceInterface
{
    /**
     * Create invoice
     */
    public function createInvoice(array $invoiceData): array;

    /**
     * Update invoice
     */
    public function updateInvoice(string $invoiceId, array $invoiceData): array;

    /**
     * Get invoice details
     */
    public function getInvoice(string $invoiceId): array;

    /**
     * Get invoices for user
     */
    public function getUserInvoices(int $userId, array $filters = []): array;

    /**
     * Get invoices for merchant
     */
    public function getMerchantInvoices(int $merchantId, array $filters = []): array;

    /**
     * Mark invoice as paid
     */
    public function markInvoiceAsPaid(string $invoiceId, array $paymentData): array;

    /**
     * Cancel invoice
     */
    public function cancelInvoice(string $invoiceId, string $reason = null): array;

    /**
     * Send invoice reminder
     */
    public function sendInvoiceReminder(string $invoiceId): array;

    /**
     * Generate invoice PDF
     */
    public function generateInvoicePdf(string $invoiceId): array;

    /**
     * Calculate invoice totals
     */
    public function calculateInvoiceTotals(array $invoiceItems, array $taxData = []): array;

    /**
     * Apply discount to invoice
     */
    public function applyDiscountToInvoice(string $invoiceId, array $discountData): array;

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices(array $filters = []): array;

    /**
     * Export invoices
     */
    public function exportInvoices(array $filters = [], string $format = 'pdf'): array;
}
