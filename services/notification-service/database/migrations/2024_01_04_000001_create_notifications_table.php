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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notification_id')->unique()->index(); // UUID for external reference
            $table->unsignedBigInteger('user_id'); // Recipient user ID
            $table->string('user_type')->default('customer'); // customer, merchant, admin
            
            // Notification content
            $table->string('type')->index(); // bid_received, order_status, payment_reminder, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional notification data
            
            // Delivery channels
            $table->json('channels'); // email, sms, push, in_app
            $table->json('channel_status')->nullable(); // Status per channel
            
            // Priority and scheduling
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->index();
            $table->timestamp('scheduled_at')->nullable()->index(); // For scheduled notifications
            $table->timestamp('expires_at')->nullable()->index(); // Notification expiration
            
            // Status tracking
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed', 'cancelled'])->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Delivery attempts
            $table->integer('delivery_attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            
            // Related entities
            $table->string('related_type')->nullable(); // order, bid, payment, etc.
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('related_data')->nullable(); // Snapshot of related entity
            
            // Failure information
            $table->json('failure_reasons')->nullable(); // Reasons for delivery failures
            $table->text('last_error')->nullable();
            
            // Personalization
            $table->string('language', 5)->default('en')->index(); // Notification language
            $table->string('timezone')->nullable(); // User timezone
            $table->json('personalization_data')->nullable(); // User-specific data
            
            // Tracking and analytics
            $table->json('tracking_data')->nullable(); // Click tracking, open rates, etc.
            $table->boolean('is_bulk')->default(false)->index(); // Part of bulk notification
            $table->string('campaign_id')->nullable()->index(); // Marketing campaign ID
            
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index(['type', 'status']);
            $table->index(['priority', 'scheduled_at']);
            $table->index(['related_type', 'related_id']);
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

