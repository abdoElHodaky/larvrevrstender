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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique()->index(); // Human-readable order number
            $table->unsignedBigInteger('part_request_id');
            $table->unsignedBigInteger('winning_bid_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('merchant_id');
            
            // Order details
            $table->decimal('total_amount', 10, 2);
            $table->decimal('part_cost', 10, 2);
            $table->decimal('delivery_cost', 8, 2)->default(0);
            $table->decimal('tax_amount', 8, 2)->default(0);
            $table->decimal('platform_fee', 8, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            
            // Status tracking
            $table->enum('status', [
                'pending_payment',
                'payment_confirmed', 
                'processing',
                'shipped',
                'delivered',
                'completed',
                'cancelled',
                'refunded',
                'disputed'
            ])->default('pending_payment')->index();
            
            // Delivery information
            $table->json('delivery_address');
            $table->string('delivery_method')->nullable(); // pickup, delivery, shipping
            $table->string('tracking_number')->nullable()->index();
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('actual_delivery')->nullable();
            
            // Payment information
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable()->index();
            $table->timestamp('payment_due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Communication
            $table->json('notes')->nullable(); // Order notes and communications
            $table->json('status_history')->nullable(); // Status change history
            
            // Quality and feedback
            $table->integer('customer_rating')->nullable(); // 1-5 rating
            $table->text('customer_feedback')->nullable();
            $table->integer('merchant_rating')->nullable(); // 1-5 rating
            $table->text('merchant_feedback')->nullable();
            
            // ZATCA compliance
            $table->string('zatca_invoice_hash')->nullable()->index();
            $table->json('zatca_metadata')->nullable();
            
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('part_request_id')->references('id')->on('part_requests')->onDelete('cascade');
            $table->foreign('winning_bid_id')->references('id')->on('bids')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index(['merchant_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('estimated_delivery');
            $table->index('payment_due_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

