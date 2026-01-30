<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique()->index(); // Human-readable invoice number
            $table->unsignedBigInteger('order_id')->unique(); // One invoice per order
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('merchant_id');
            
            // Invoice details
            $table->decimal('subtotal', 10, 2); // Before tax and fees
            $table->decimal('tax_amount', 8, 2)->default(0); // VAT/Tax
            $table->decimal('platform_fee', 8, 2)->default(0);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('discount_amount', 8, 2)->default(0);
            $table->decimal('total_amount', 10, 2); // Final amount
            $table->string('currency', 3)->default('SAR');
            
            // Invoice status
            $table->enum('status', [
                'draft',
                'sent',
                'viewed',
                'paid',
                'overdue',
                'cancelled',
                'refunded'
            ])->default('draft')->index();
            
            // Dates
            $table->date('invoice_date');
            $table->date('due_date');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Customer information
            $table->json('billing_address');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_tax_id')->nullable(); // For ZATCA
            
            // Merchant information
            $table->string('merchant_name');
            $table->string('merchant_tax_number')->nullable(); // For ZATCA
            $table->json('merchant_address')->nullable();
            
            // Invoice items
            $table->json('line_items'); // Array of invoice line items
            
            // ZATCA compliance
            $table->string('zatca_uuid')->nullable()->unique()->index();
            $table->string('zatca_hash')->nullable()->index();
            $table->json('zatca_qr_code')->nullable(); // QR code data
            $table->enum('zatca_status', ['pending', 'submitted', 'approved', 'rejected'])->nullable()->index();
            $table->timestamp('zatca_submitted_at')->nullable();
            $table->json('zatca_response')->nullable(); // ZATCA API response
            
            // Payment tracking
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable()->index();
            $table->json('payment_metadata')->nullable();
            
            // Notes and communication
            $table->text('notes')->nullable();
            $table->json('status_history')->nullable(); // Status change history
            $table->json('email_history')->nullable(); // Email sending history
            
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index(['merchant_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index(['invoice_date', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

