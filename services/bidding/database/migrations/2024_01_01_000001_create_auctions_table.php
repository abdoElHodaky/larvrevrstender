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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('vehicle_id');
            $table->decimal('starting_price', 15, 2);
            $table->decimal('reserve_price', 15, 2)->nullable();
            $table->decimal('current_highest_bid', 15, 2)->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled', 'suspended'])->default('draft');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('winner_bid_id')->nullable();
            $table->decimal('winning_amount', 15, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['created_by']);
            $table->index(['vehicle_id']);
            $table->index(['winner_bid_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
