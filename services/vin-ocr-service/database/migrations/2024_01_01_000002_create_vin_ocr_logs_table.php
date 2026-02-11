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
        Schema::create('vin_ocr_logs', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            
            // Image paths
            $table->string('original_image_path')->nullable();
            $table->string('processed_image_path')->nullable();
            
            // OCR results
            $table->string('extracted_vin', 17)->nullable()->index();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->json('ocr_metadata')->nullable();
            $table->json('validation_result')->nullable();
            
            // Processing information
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->integer('processing_time_ms')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['vehicle_id', 'status']);
            $table->index(['extracted_vin', 'status']);
            $table->index(['confidence_score', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vin_ocr_logs');
    }
};
