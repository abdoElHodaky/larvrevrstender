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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Template identifier
            $table->string('type'); // email, sms, push, in_app
            $table->string('subject')->nullable(); // For email notifications
            $table->text('content'); // Template content with placeholders
            $table->json('variables')->nullable(); // Required variables for template
            $table->json('metadata')->nullable(); // Additional template configuration
            $table->boolean('is_active')->default(true);
            $table->string('language', 5)->default('en'); // Language code
            $table->timestamps();

            // Indexes
            $table->index(['name', 'type']);
            $table->index(['type', 'is_active']);
            $table->index(['language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
