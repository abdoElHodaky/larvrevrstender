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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_reference')->unique();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('original_payment_id')->nullable(); // For tracking refund chains
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('merchant_id');
            
            // Refund details
            $table->enum('type', ['full', 'partial']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->decimal('fee_amount', 10, 2)->default(0); // Refund processing fee
            $table->decimal('net_refund_amount', 10, 2); // Amount actually refunded to customer
            
            // Refund reason and details
            $table->enum('reason', [
                'requested_by_customer',
                'duplicate_payment',
                'fraudulent_transaction',
                'order_cancelled',
                'product_not_delivered',
                'product_defective',
                'merchant_error',
                'chargeback_prevention',
                'other'
            ]);
            $table->text('reason_description')->nullable();
            $table->string('initiated_by')->nullable(); // User ID or system that initiated
            
            // Gateway information
            $table->string('payment_provider');
            $table->string('gateway_refund_id')->nullable(); // Gateway's refund ID
            $table->string('gateway_transaction_id')->nullable(); // Original transaction ID at gateway
            $table->json('gateway_request')->nullable();
            $table->json('gateway_response')->nullable();
            
            // Processing timeline
            $table->timestamp('initiated_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('processing_time_seconds')->nullable(); // For analytics
            
            // Failure tracking
            $table->string('failure_reason')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            
            // Reconciliation and accounting
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciliation_reference')->nullable();
            $table->string('accounting_code')->nullable();
            
            // Compliance and audit
            $table->json('compliance_data')->nullable(); // ZATCA, tax information
            $table->json('audit_trail')->nullable(); // Track status changes
            $table->json('metadata')->nullable();
            
            // Notification tracking
            $table->boolean('customer_notified')->default(false);
            $table->timestamp('customer_notified_at')->nullable();
            $table->boolean('merchant_notified')->default(false);
            $table->timestamp('merchant_notified_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['payment_id']);
            $table->index(['original_payment_id']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['merchant_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_provider', 'gateway_refund_id']);
            $table->index(['reconciled', 'created_at']);
            $table->index(['initiated_at']);
            $table->index(['next_retry_at']); // For retry processing
            
            // Foreign key constraints
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('original_payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
