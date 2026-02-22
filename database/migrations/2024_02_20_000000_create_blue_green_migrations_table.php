<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blue_green_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration_name')->index();
            $table->enum('environment_color', ['blue', 'green'])->index();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'rolled_back'])->index();
            $table->json('metadata')->nullable();
            $table->text('sql_forward')->nullable();
            $table->text('sql_backward')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->string('executed_by')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['environment_color', 'status']);
            $table->index(['migration_name', 'environment_color']);
            $table->index(['status', 'started_at']);
            
            // Unique constraint to prevent duplicate migrations per environment
            $table->unique(['migration_name', 'environment_color'], 'unique_migration_per_environment');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blue_green_migrations');
    }
};
