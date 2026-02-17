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
        // Check if we're using SQLite (for testing) or MySQL/PostgreSQL (for production)
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite doesn't support MODIFY COLUMN for ENUM, but it's flexible with string values
            // The original table creation already allows any string values, so no changes needed
            // This migration is essentially a no-op for SQLite
            return;
        }
        
        if ($driver === 'mysql') {
            // MySQL-specific syntax for modifying ENUM
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
        } elseif ($driver === 'pgsql') {
            // PostgreSQL doesn't use ENUM in the same way, but we can add a check constraint
            // First drop existing constraint if it exists
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check");
            
            // Add new constraint with additional values
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check 
                CHECK (status IN (
                    'pending', 'processing', 'authorized', 'completed', 
                    'failed', 'cancelled', 'voided', 'refunded', 'partially_refunded'
                ))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check database driver
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite doesn't need rollback for this change
            return;
        }
        
        if ($driver === 'mysql') {
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
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: restore original constraint
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check");
            
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check 
                CHECK (status IN (
                    'pending', 'processing', 'completed', 
                    'failed', 'cancelled', 'refunded', 'partially_refunded'
                ))");
        }
    }
};
