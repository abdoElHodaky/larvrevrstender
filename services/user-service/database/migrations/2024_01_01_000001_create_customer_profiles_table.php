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
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('company_name')->nullable();
            $table->string('industry')->nullable();
            $table->enum('company_size', ['startup', 'small', 'medium', 'large', 'enterprise'])->nullable();
            $table->decimal('annual_budget', 15, 2)->nullable();
            $table->json('preferred_categories')->nullable();
            $table->json('delivery_addresses')->nullable();
            $table->text('payment_terms')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->json('verification_documents')->nullable();
            $table->json('preferences')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('verification_status');
            $table->index('industry');
            $table->index('company_size');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};

