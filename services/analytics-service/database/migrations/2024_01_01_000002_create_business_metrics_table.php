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
        Schema::create('business_metrics', function (Blueprint $table) {
            $table->id();
            
            // Metric information
            $table->date('metric_date')->index();
            $table->string('metric_type')->index();
            $table->decimal('value', 15, 2)->default(0);
            $table->json('breakdown')->nullable();
            
            $table->timestamps();
            
            // Unique constraint to prevent duplicate metrics for same date/type
            $table->unique(['metric_date', 'metric_type']);
            
            // Indexes for performance
            $table->index(['metric_type', 'metric_date']);
            $table->index(['metric_date', 'value']);
            $table->index('created_at');
            
            // Composite indexes for common queries
            $table->index(['metric_type', 'value', 'metric_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_metrics');
    }
};
