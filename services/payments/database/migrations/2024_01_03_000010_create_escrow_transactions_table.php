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
        Schema::create('escrow_transactions', function (Blueprint $table) {
            $table->id();
            
            // Core relationship
            $table->unsignedBigInteger('escrow_id')->index();
            
            // Transaction details
            $table->enum('type', [
                'hold',
                'release',
                'partial_release',
                'dispute',
                'cancel'
            ])->index();
            
            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            
            // Processing details
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->useCurrent();
            
            // External system references
            $table->string('external_reference')->nullable()->index();
            $table->json('metadata')->nullable();
            
            // Audit fields
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['escrow_id', 'type']);
            $table->index(['processed_at', 'type']);
            
            // Foreign key to escrows table
            $table->foreign('escrow_id')->references('id')->on('escrows')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_transactions');
    }
};

