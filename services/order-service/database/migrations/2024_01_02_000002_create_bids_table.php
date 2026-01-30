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
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('part_request_id');
            $table->unsignedBigInteger('merchant_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->text('description')->nullable();
            $table->string('part_condition')->nullable(); // new, used, refurbished
            $table->string('brand')->nullable();
            $table->string('part_number')->nullable();
            $table->integer('warranty_months')->nullable();
            $table->json('images')->nullable(); // Array of part images
            $table->json('specifications')->nullable(); // Part specifications
            $table->integer('delivery_days')->nullable(); // Estimated delivery time
            $table->decimal('delivery_cost', 8, 2)->nullable();
            $table->json('delivery_options')->nullable(); // pickup, delivery, shipping
            $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn', 'expired'])->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('terms_conditions')->nullable(); // Additional terms
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('part_request_id')->references('id')->on('part_requests')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['part_request_id', 'status']);
            $table->index(['merchant_id', 'status']);
            $table->index(['amount', 'status']);
            $table->index('expires_at');
            $table->index('created_at');
            
            // Unique constraint to prevent duplicate bids from same merchant
            $table->unique(['part_request_id', 'merchant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};

