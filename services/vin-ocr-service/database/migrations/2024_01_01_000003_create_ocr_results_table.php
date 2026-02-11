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
        Schema::create('ocr_results', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to vin_scans
            $table->foreignId('vin_scan_id')->constrained('vin_scans')->onDelete('cascade');
            
            // OCR detection results
            $table->text('detected_text')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->json('bounding_box')->nullable(); // x, y, width, height coordinates
            $table->integer('character_position')->nullable(); // Position in the VIN string
            $table->enum('validation_status', ['valid', 'invalid', 'uncertain'])->default('uncertain');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['vin_scan_id', 'character_position']);
            $table->index(['confidence_score', 'validation_status']);
            $table->index('validation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocr_results');
    }
};
