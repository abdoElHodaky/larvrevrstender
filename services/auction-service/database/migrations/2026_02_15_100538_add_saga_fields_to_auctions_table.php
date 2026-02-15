<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds fields required for auction saga workflows:
     * - ended_at: Timestamp when auction actually ended (vs scheduled ends_at)
     * - winner_user_id: ID of the winning bidder
     * - winning_bid_id: ID of the winning bid record
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            // Add ended_at timestamp for when auction actually ended
            $table->timestamp('ended_at')->nullable()->after('ends_at');
            
            // Add winner information fields
            $table->unsignedBigInteger('winner_user_id')->nullable()->after('created_by');
            $table->unsignedBigInteger('winning_bid_id')->nullable()->after('winner_user_id');
            
            // Update status enum to include 'ended' status used by saga
            $table->dropColumn('status');
        });
        
        // Re-add status column with updated enum values
        Schema::table('auctions', function (Blueprint $table) {
            $table->enum('status', [
                'draft', 
                'scheduled', 
                'active', 
                'ended',        // New status used by AuctionEndingSaga
                'completed',    // Keep existing for backward compatibility
                'cancelled'
            ])->default('draft')->after('current_highest_bid');
        });
        
        // Add indexes for new fields to improve query performance
        Schema::table('auctions', function (Blueprint $table) {
            $table->index(['winner_user_id']);
            $table->index(['winning_bid_id']);
            $table->index(['ended_at']);
            $table->index(['status', 'ended_at']); // Composite index for ended auctions
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            // Drop the new indexes
            $table->dropIndex(['winner_user_id']);
            $table->dropIndex(['winning_bid_id']);
            $table->dropIndex(['ended_at']);
            $table->dropIndex(['status', 'ended_at']);
            
            // Drop the new columns
            $table->dropColumn(['ended_at', 'winner_user_id', 'winning_bid_id']);
            
            // Revert status enum to original values
            $table->dropColumn('status');
        });
        
        // Re-add original status column
        Schema::table('auctions', function (Blueprint $table) {
            $table->enum('status', ['draft', 'scheduled', 'active', 'completed', 'cancelled'])
                  ->default('draft')
                  ->after('current_highest_bid');
        });
    }
};

