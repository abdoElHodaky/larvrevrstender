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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_reference')->unique();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('merchant_id');
            
            // Transaction details
            $table->enum('type', ['payment', 'refund', 'partial_refund', 'fee', 'chargeback', 'adjustment']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'reversed']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            
            // Transaction metadata
            $table->string('description')->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            
            // Gateway information
            $table->string('payment_provider')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            
            // Accounting and reconciliation
            $table->string('accounting_code')->nullable();
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciliation_reference')->nullable();
            
            // Audit trail
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('processed_by')->nullable(); // User or system that processed
            
            // Relationships and references
            $table->unsignedBigInteger('parent_transaction_id')->nullable(); // For refunds/adjustments
            $table->string('external_reference')->nullable(); // External system reference
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'created_at']);
            $table->index(['merchant_id', 'created_at']);
            $table->index(['payment_id']);
            $table->index(['invoice_id']);
            $table->index(['order_id']);
            $table->index(['type', 'status']);
            $table->index(['reconciled', 'created_at']);
            $table->index(['payment_provider', 'gateway_transaction_id']);
            
            // Foreign key constraints
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('parent_transaction_id')->references('id')->on('transactions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
