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
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id')->unique(); // Unique identifier for this webhook event
            $table->string('provider'); // stripe, paypal, mada, stc_pay, etc.
            $table->string('event_type'); // payment.succeeded, charge.failed, etc.
            $table->string('event_id')->nullable(); // Provider's event ID
            
            // Related entities
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('payment_reference')->nullable(); // For lookup before payment_id is resolved
            $table->unsignedBigInteger('refund_id')->nullable();
            $table->string('external_transaction_id')->nullable(); // Gateway transaction ID
            
            // Webhook data
            $table->json('headers'); // HTTP headers from webhook request
            $table->longText('payload'); // Raw webhook payload
            $table->json('parsed_data')->nullable(); // Parsed and structured data
            $table->string('signature')->nullable(); // Webhook signature for verification
            $table->string('signature_algorithm')->nullable(); // HMAC-SHA256, etc.
            
            // Processing status
            $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'ignored', 'duplicate']);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            
            // Verification and security
            $table->boolean('signature_verified')->default(false);
            $table->timestamp('signature_verified_at')->nullable();
            $table->string('source_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_test_event')->default(false);
            
            // Processing results
            $table->text('processing_result')->nullable(); // Success message or error details
            $table->json('processing_errors')->nullable(); // Detailed error information
            $table->json('actions_taken')->nullable(); // What actions were performed
            $table->boolean('requires_manual_review')->default(false);
            
            // Duplicate detection
            $table->string('idempotency_key')->nullable(); // For duplicate detection
            $table->string('content_hash')->nullable(); // Hash of payload for duplicate detection
            $table->unsignedBigInteger('duplicate_of_webhook_id')->nullable(); // Reference to original webhook
            
            // Workflow integration
            $table->string('workflow_id')->nullable(); // Associated workflow/saga ID
            $table->string('workflow_step')->nullable(); // Current step in workflow
            $table->json('workflow_context')->nullable(); // Workflow state data
            
            // Notification and alerting
            $table->boolean('alert_sent')->default(false);
            $table->timestamp('alert_sent_at')->nullable();
            $table->json('notification_recipients')->nullable(); // Who was notified
            
            // Audit and compliance
            $table->json('audit_trail')->nullable(); // Track processing steps
            $table->json('compliance_data')->nullable(); // Regulatory compliance data
            $table->json('metadata')->nullable();
            
            // Response tracking
            $table->integer('response_status_code')->nullable(); // HTTP status returned to provider
            $table->text('response_body')->nullable(); // Response sent back to provider
            $table->timestamp('response_sent_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance and querying
            $table->index(['provider', 'event_type']);
            $table->index(['payment_id']);
            $table->index(['payment_reference']);
            $table->index(['refund_id']);
            $table->index(['external_transaction_id']);
            $table->index(['status', 'received_at']);
            $table->index(['received_at']);
            $table->index(['next_retry_at']); // For retry processing
            $table->index(['signature_verified']);
            $table->index(['is_test_event']);
            $table->index(['requires_manual_review']);
            $table->index(['content_hash']); // For duplicate detection
            $table->index(['idempotency_key']);
            $table->index(['workflow_id']);
            $table->index(['duplicate_of_webhook_id']);
            
            // Foreign key constraints
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->foreign('refund_id')->references('id')->on('refunds')->onDelete('set null');
            $table->foreign('duplicate_of_webhook_id')->references('id')->on('payment_webhooks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
