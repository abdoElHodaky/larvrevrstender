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
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auction_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn', 'outbid'])->default('pending');
            $table->timestamp('submitted_at');
            $table->text('notes')->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->decimal('bid_increment', 15, 2)->nullable();
            $table->boolean('is_automatic')->default(false);
            $table->decimal('max_amount', 15, 2)->nullable(); // For automatic bidding
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('cascade');
            $table->index(['auction_id', 'amount']);
            $table->index(['user_id', 'status']);
            $table->index(['submitted_at']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
