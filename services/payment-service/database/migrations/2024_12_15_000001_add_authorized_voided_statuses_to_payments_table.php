<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, modify the enum to include the new statuses
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM(
            'pending',
            'processing', 
            'authorized',
            'completed',
            'failed',
            'cancelled',
            'voided',
            'refunded',
            'partially_refunded'
        ) DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the new statuses from the enum
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM(
            'pending',
            'processing',
            'completed',
            'failed',
            'cancelled',
            'refunded',
            'partially_refunded'
        ) DEFAULT 'pending'");
    }
};
