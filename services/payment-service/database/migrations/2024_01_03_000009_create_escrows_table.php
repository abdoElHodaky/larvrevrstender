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
        Schema::create('escrows', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('payment_id')->index();
            $table->unsignedBigInteger('buyer_id')->index();
            $table->unsignedBigInteger('seller_id')->index();
            
            // Financial details
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            
            // Status tracking
            $table->enum('status', [
                'created',
                'funded',
                'released',
                'disputed',
                'cancelled'
            ])->default('created')->index();
            
            // Timing controls
            $table->timestamp('hold_until')->nullable()->index();
            $table->timestamp('released_at')->nullable();
            
            // Audit fields
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'hold_until']);
            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            
            // Foreign key constraints would be added if cross-service references were supported
            // For now, we maintain referential integrity at the application level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrows');
    }
};

