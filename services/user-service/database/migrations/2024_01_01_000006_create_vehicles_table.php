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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('trim_id')->nullable();
            $table->integer('year');
            $table->string('vin', 17)->unique()->nullable()->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->string('custom_name')->nullable();
            $table->integer('mileage')->nullable();
            $table->string('engine_type', 100)->nullable();
            $table->string('transmission_type', 100)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->string('body_style', 100)->nullable();
            $table->decimal('vin_confidence', 3, 2)->default(0.00); // OCR confidence score
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('customer_id')->references('id')->on('customer_profiles')->onDelete('cascade');
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('model_id')->references('id')->on('vehicle_models');
            $table->foreign('trim_id')->references('id')->on('trims')->onDelete('set null');

            // Indexes for performance
            $table->index('customer_id');
            $table->index('brand_id');
            $table->index('model_id');
            $table->index('trim_id');
            $table->index('vin');
            $table->index('is_primary');
            $table->index(['customer_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

