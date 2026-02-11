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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // stripe, paypal, mada, stc_pay, etc.
            $table->string('display_name'); // Human-readable name
            $table->text('description')->nullable();
            
            // Gateway configuration
            $table->enum('status', ['active', 'inactive', 'maintenance', 'deprecated']);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_test_mode')->default(false);
            $table->integer('priority')->default(100); // Lower number = higher priority
            
            // Supported features
            $table->json('supported_payment_methods'); // ['card', 'bank_transfer', 'wallet']
            $table->json('supported_currencies'); // ['USD', 'SAR', 'EUR']
            $table->json('supported_countries')->nullable(); // Geographic restrictions
            $table->boolean('supports_refunds')->default(true);
            $table->boolean('supports_partial_refunds')->default(true);
            $table->boolean('supports_3ds')->default(false);
            $table->boolean('supports_webhooks')->default(true);
            $table->boolean('supports_recurring')->default(false);
            
            // Fee structure
            $table->decimal('fee_percentage', 5, 4)->default(0); // e.g., 2.9% = 0.0290
            $table->decimal('fee_fixed', 10, 2)->default(0); // Fixed fee per transaction
            $table->decimal('refund_fee_percentage', 5, 4)->default(0);
            $table->decimal('refund_fee_fixed', 10, 2)->default(0);
            $table->decimal('minimum_amount', 10, 2)->default(0);
            $table->decimal('maximum_amount', 10, 2)->nullable();
            
            // API configuration
            $table->string('api_endpoint')->nullable();
            $table->string('webhook_endpoint')->nullable();
            $table->string('api_version')->nullable();
            $table->json('api_credentials')->nullable(); // Encrypted credentials
            $table->json('webhook_config')->nullable(); // Webhook-specific settings
            
            // Health monitoring
            $table->enum('health_status', ['healthy', 'degraded', 'unhealthy', 'unknown'])->default('unknown');
            $table->timestamp('last_health_check')->nullable();
            $table->decimal('success_rate', 5, 2)->nullable(); // Last 24h success rate
            $table->integer('avg_response_time_ms')->nullable(); // Average response time
            $table->json('health_metrics')->nullable(); // Additional health data
            
            // Rate limiting
            $table->integer('rate_limit_per_minute')->nullable();
            $table->integer('rate_limit_per_hour')->nullable();
            $table->integer('rate_limit_per_day')->nullable();
            $table->timestamp('rate_limit_reset_at')->nullable();
            $table->integer('current_usage_count')->default(0);
            
            // Maintenance and downtime
            $table->timestamp('maintenance_start')->nullable();
            $table->timestamp('maintenance_end')->nullable();
            $table->text('maintenance_reason')->nullable();
            $table->json('downtime_history')->nullable(); // Track historical downtime
            
            // Integration settings
            $table->json('integration_config')->nullable(); // Gateway-specific settings
            $table->json('ui_config')->nullable(); // Frontend display configuration
            $table->string('logo_url')->nullable();
            $table->string('documentation_url')->nullable();
            
            // Audit and metadata
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('last_used_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'is_enabled']);
            $table->index(['priority', 'status']);
            $table->index(['health_status']);
            $table->index(['last_health_check']);
            $table->index(['is_test_mode']);
            $table->index(['last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
