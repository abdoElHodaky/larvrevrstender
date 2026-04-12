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
        Schema::create('escrow_release_conditions', function (Blueprint $table) {
            $table->id();
            
            // Core relationship
            $table->unsignedBigInteger('escrow_id')->index();
            
            // Condition details
            $table->enum('condition_type', [
                'delivery_confirmed',
                'inspection_passed',
                'time_elapsed',
                'manual_approval'
            ])->index();
            
            $table->json('condition_data')->nullable();
            
            // Status tracking
            $table->boolean('is_met')->default(false)->index();
            $table->timestamp('met_at')->nullable();
            
            // Audit fields
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['escrow_id', 'condition_type']);
            $table->index(['escrow_id', 'is_met']);
            $table->index(['condition_type', 'is_met']);
            
            // Foreign key to escrows table
            $table->foreign('escrow_id')->references('id')->on('escrows')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_release_conditions');
    }
};

