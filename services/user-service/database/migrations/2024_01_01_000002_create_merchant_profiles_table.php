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
        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('business_name')->index();
            $table->string('business_license', 100)->nullable();
            $table->string('tax_number', 50)->nullable()->index(); // For ZATCA integration
            $table->json('specializations')->nullable(); // Auto parts categories they specialize in
            $table->decimal('rating', 3, 2)->default(0.00)->index();
            $table->integer('total_reviews')->default(0);
            $table->boolean('verified')->default(false)->index();
            $table->json('verification_documents')->nullable();
            $table->json('business_hours')->nullable(); // Operating hours
            $table->json('service_areas')->nullable(); // Geographic areas they serve
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('business_name');
            $table->index('verified');
            $table->index('rating');
            $table->index('tax_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_profiles');
    }
};

