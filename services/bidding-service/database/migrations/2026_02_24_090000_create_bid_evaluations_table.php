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
        Schema::create('bid_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bid_id');
            $table->unsignedBigInteger('auction_id');
            $table->unsignedBigInteger('evaluator_id')->nullable(); // System or manual evaluator
            
            // Evaluation criteria scores (0-100 scale)
            $table->decimal('price_score', 5, 2)->default(0.00);
            $table->decimal('delivery_score', 5, 2)->default(0.00);
            $table->decimal('quality_score', 5, 2)->default(0.00);
            $table->decimal('supplier_score', 5, 2)->default(0.00);
            $table->decimal('technical_score', 5, 2)->default(0.00);
            $table->decimal('compliance_score', 5, 2)->default(0.00);
            
            // Weighted composite score
            $table->decimal('composite_score', 5, 2)->default(0.00);
            $table->integer('rank')->nullable(); // Final ranking
            
            // Evaluation metadata
            $table->json('evaluation_criteria')->nullable(); // Criteria weights and rules
            $table->json('score_breakdown')->nullable(); // Detailed scoring breakdown
            $table->text('evaluation_notes')->nullable();
            $table->enum('evaluation_status', ['pending', 'completed', 'reviewed', 'approved'])->default('pending');
            
            // Timestamps
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('bid_id')->references('id')->on('bids')->onDelete('cascade');
            $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['auction_id', 'composite_score']);
            $table->index(['bid_id']);
            $table->index(['rank']);
            $table->index(['evaluation_status']);
            $table->index(['evaluated_at']);
            
            // Unique constraint to prevent duplicate evaluations
            $table->unique(['bid_id', 'auction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_evaluations');
    }
};
