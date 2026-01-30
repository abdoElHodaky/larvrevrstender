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
        Schema::create('trims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('model_id');
            $table->string('name')->index();
            $table->string('engine_type', 100)->nullable();
            $table->string('transmission_type', 100)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->string('body_style', 100)->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('model_id')->references('id')->on('vehicle_models')->onDelete('cascade');

            // Indexes for performance
            $table->index('model_id');
            $table->index('name');
            $table->index(['model_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trims');
    }
};

