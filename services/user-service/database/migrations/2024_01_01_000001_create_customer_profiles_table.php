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
            $table->string('national_id', 20)->nullable()->index(); // Saudi National ID for ZATCA
            $table->text('national_address')->nullable();
            $table->json('default_location')->nullable(); // GPS coordinates, address details
            $table->json('preferences')->nullable(); // Notification preferences, language, etc.
            $table->timestamps();

            // Foreign key constraint (references users table in auth-service)
            $table->index('user_id');
            $table->index('national_id');
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

