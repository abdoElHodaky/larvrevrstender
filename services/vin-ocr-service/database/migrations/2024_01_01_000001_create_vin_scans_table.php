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
        Schema::create('vin_scans', function (Blueprint $table) {
            $table->id();
            
            // Basic scan information
            $table->string('image_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('vin_number', 17)->nullable()->index();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->integer('processing_time_ms')->nullable();
            
            // Cloud storage integration fields
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('storage_provider')->nullable();
            $table->text('url')->nullable();
            $table->text('cdn_url')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['confidence_score', 'status']);
            $table->index('storage_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vin_scans');
    }
};
