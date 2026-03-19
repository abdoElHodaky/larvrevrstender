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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method_reference')->unique();
            $table->unsignedBigInteger('customer_id');
            
            // Payment method details
            $table->enum('type', ['card', 'bank_account', 'wallet', 'cash']);
            $table->string('provider'); // stripe, paypal, mada, stc_pay, etc.
            $table->string('provider_method_id')->nullable(); // Gateway's payment method ID
            $table->string('provider_customer_id')->nullable(); // Gateway's customer ID
            
            // Card-specific fields
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand')->nullable(); // visa, mastercard, mada, etc.
            $table->string('card_type')->nullable(); // credit, debit
            $table->string('card_country')->nullable();
            $table->string('card_fingerprint')->nullable(); // For duplicate detection
            $table->integer('card_exp_month')->nullable();
            $table->integer('card_exp_year')->nullable();
            
            // Bank account fields
            $table->string('bank_name')->nullable();
            $table->string('bank_account_type')->nullable(); // checking, savings
            $table->string('bank_account_last_four', 4)->nullable();
            $table->string('bank_routing_number')->nullable();
            $table->string('bank_country')->nullable();
            
            // Wallet fields
            $table->string('wallet_type')->nullable(); // apple_pay, google_pay, samsung_pay
            $table->string('wallet_account_id')->nullable();
            
            // Security and tokenization
            $table->string('token')->nullable(); // Encrypted token for secure storage
            $table->json('encrypted_data')->nullable(); // Additional encrypted payment data
            $table->string('fingerprint_hash')->nullable(); // For duplicate detection
            
            // Status and verification
            $table->enum('status', ['active', 'inactive', 'expired', 'failed_verification']);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            
            // Billing address
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postal_code')->nullable();
            $table->string('billing_country')->nullable();
            
            // Metadata and audit
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable(); // User or system that created
            $table->string('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable(); // Soft delete for security
            
            $table->timestamps();
            
            // Indexes for performance and security
            $table->index(['customer_id', 'is_default']);
            $table->index(['customer_id', 'status']);
            $table->index(['provider', 'provider_method_id']);
            $table->index(['card_fingerprint']);
            $table->index(['fingerprint_hash']);
            $table->index(['expires_at']);
            $table->index(['last_used_at']);
            $table->index(['deleted_at']); // For soft delete queries
            
            // Unique constraints
            $table->unique(['customer_id', 'provider', 'provider_method_id'], 'unique_customer_provider_method');
            
            // Ensure only one default payment method per customer
            $table->unique(['customer_id', 'is_default'], 'unique_customer_default')
                  ->where('is_default', true)
                  ->where('deleted_at', null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
