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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference')->unique()->index(); // Unique payment reference
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('merchant_id');
            
            // Payment details
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->enum('type', ['payment', 'refund', 'partial_refund'])->default('payment')->index();
            
            // Payment method details
            $table->string('payment_method')->index(); // card, bank_transfer, wallet, cash
            $table->string('payment_provider')->nullable(); // stripe, paypal, mada, stc_pay, etc.
            $table->string('provider_transaction_id')->nullable()->index();
            $table->json('payment_details')->nullable(); // Provider-specific details
            
            // Card details (if applicable, encrypted/tokenized)
            $table->string('card_last_four')->nullable();
            $table->string('card_brand')->nullable(); // visa, mastercard, mada
            $table->string('card_token')->nullable(); // For future payments
            
            // Status tracking
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'refunded',
                'partially_refunded'
            ])->default('pending')->index();
            
            // Timestamps
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Failure information
            $table->string('failure_reason')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            
            // Refund information
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();
            
            // Security and fraud detection
            $table->json('risk_assessment')->nullable(); // Fraud detection results
            $table->boolean('requires_3ds')->default(false); // 3D Secure requirement
            $table->string('3ds_status')->nullable(); // 3D Secure status
            
            // Gateway response data
            $table->json('gateway_request')->nullable(); // Request sent to gateway
            $table->json('gateway_response')->nullable(); // Response from gateway
            $table->json('webhook_data')->nullable(); // Webhook notifications
            
            // Fees and charges
            $table->decimal('gateway_fee', 8, 2)->default(0);
            $table->decimal('platform_fee', 8, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->nullable(); // Amount after fees
            
            // Reconciliation
            $table->boolean('reconciled')->default(false)->index();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciliation_reference')->nullable();
            
            // ZATCA compliance for payments
            $table->string('zatca_payment_reference')->nullable();
            $table->json('zatca_payment_data')->nullable();
            
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index(['merchant_id', 'status']);
            $table->index(['payment_method', 'status']);
            $table->index(['payment_provider', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('completed_at');
            $table->index('reconciled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

