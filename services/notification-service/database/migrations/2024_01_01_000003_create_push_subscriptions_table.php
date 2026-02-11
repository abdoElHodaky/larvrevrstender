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
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            
            // User relationship
            $table->unsignedBigInteger('user_id')->index();
            
            // Push subscription data
            $table->string('endpoint', 500);
            $table->text('public_key')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            
            // Device and browser information
            $table->string('user_agent', 500)->nullable();
            $table->string('device_type', 50)->nullable(); // mobile, desktop, tablet
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            
            // Subscription metadata
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Notification preferences for this subscription
            $table->json('notification_preferences')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'is_active']);
            $table->index(['endpoint', 'is_active']);
            $table->index('last_used_at');
            $table->index('expires_at');
            
            // Unique constraint to prevent duplicate subscriptions
            $table->unique(['user_id', 'endpoint'], 'unique_user_endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
