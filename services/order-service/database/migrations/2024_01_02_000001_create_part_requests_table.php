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
        Schema::create('part_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('part_category')->index();
            $table->string('part_number')->nullable()->index();
            $table->string('brand_preference')->nullable();
            $table->enum('condition_preference', ['new', 'used', 'refurbished', 'any'])->default('any');
            $table->decimal('budget_min', 10, 2)->nullable();
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->enum('urgency', ['low', 'medium', 'high', 'urgent'])->default('medium')->index();
            $table->json('images')->nullable(); // Array of image URLs
            $table->json('specifications')->nullable(); // Additional part specifications
            $table->json('location_preferences')->nullable(); // Preferred pickup/delivery locations
            $table->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('draft')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->integer('bid_count')->default(0);
            $table->decimal('lowest_bid', 10, 2)->nullable();
            $table->decimal('highest_bid', 10, 2)->nullable();
            $table->json('metadata')->nullable(); // Additional request metadata
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index(['part_category', 'status']);
            $table->index(['urgency', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part_requests');
    }
};

