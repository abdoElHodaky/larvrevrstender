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
        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->id();
            $table->string('reconciliation_reference')->unique();
            $table->string('batch_id')->nullable(); // For batch reconciliation
            
            // Source information
            $table->enum('source_type', ['bank_statement', 'gateway_report', 'manual_entry', 'automated_sync']);
            $table->string('source_reference')->nullable(); // Bank statement ID, gateway report ID, etc.
            $table->string('source_file_path')->nullable(); // Path to uploaded file
            $table->json('source_metadata')->nullable(); // Additional source information
            
            // Related entities
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('external_transaction_id')->nullable(); // Gateway transaction ID
            
            // Reconciliation details
            $table->enum('status', ['pending', 'matched', 'unmatched', 'disputed', 'resolved', 'ignored']);
            $table->enum('match_type', ['exact', 'fuzzy', 'manual', 'system_generated'])->nullable();
            $table->decimal('match_confidence', 5, 2)->nullable(); // 0-100% confidence score
            
            // Financial data
            $table->decimal('expected_amount', 10, 2)->nullable(); // Amount from our records
            $table->decimal('actual_amount', 10, 2); // Amount from bank/gateway
            $table->decimal('variance_amount', 10, 2)->nullable(); // Difference
            $table->string('currency', 3);
            $table->decimal('fees_expected', 10, 2)->default(0);
            $table->decimal('fees_actual', 10, 2)->default(0);
            
            // Timing information
            $table->date('transaction_date'); // Date of the actual transaction
            $table->date('settlement_date')->nullable(); // Date funds were settled
            $table->date('reconciliation_date'); // Date reconciliation was performed
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            
            // Gateway/Bank information
            $table->string('payment_provider')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_reference')->nullable();
            $table->text('bank_description')->nullable();
            
            // Discrepancy handling
            $table->enum('discrepancy_type', [
                'amount_mismatch',
                'missing_payment',
                'duplicate_payment',
                'timing_difference',
                'fee_variance',
                'currency_mismatch',
                'other'
            ])->nullable();
            $table->text('discrepancy_notes')->nullable();
            $table->string('assigned_to')->nullable(); // User assigned to resolve discrepancy
            $table->json('resolution_actions')->nullable(); // Actions taken to resolve
            
            // Matching criteria and rules
            $table->json('matching_criteria')->nullable(); // Criteria used for matching
            $table->json('matching_rules_applied')->nullable(); // Which rules were applied
            $table->json('alternative_matches')->nullable(); // Other possible matches
            
            // Audit and workflow
            $table->string('reconciled_by')->nullable(); // User or system that performed reconciliation
            $table->enum('reconciliation_method', ['automatic', 'semi_automatic', 'manual']);
            $table->json('audit_trail')->nullable(); // Track status changes and actions
            $table->json('approval_workflow')->nullable(); // Approval process tracking
            $table->boolean('requires_approval')->default(false);
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            // Reporting and analytics
            $table->string('reporting_period')->nullable(); // Monthly, weekly, etc.
            $table->json('tags')->nullable(); // For categorization and reporting
            $table->json('custom_fields')->nullable(); // Extensible custom data
            
            // Integration with accounting systems
            $table->string('accounting_entry_id')->nullable(); // Reference to accounting system
            $table->boolean('exported_to_accounting')->default(false);
            $table->timestamp('exported_at')->nullable();
            $table->json('accounting_metadata')->nullable();
            
            // Compliance and regulatory
            $table->json('compliance_data')->nullable(); // Regulatory compliance information
            $table->boolean('flagged_for_review')->default(false);
            $table->text('review_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance and querying
            $table->index(['payment_id']);
            $table->index(['transaction_id']);
            $table->index(['payment_reference']);
            $table->index(['external_transaction_id']);
            $table->index(['status', 'reconciliation_date']);
            $table->index(['transaction_date']);
            $table->index(['settlement_date']);
            $table->index(['reconciliation_date']);
            $table->index(['payment_provider']);
            $table->index(['bank_name', 'bank_account']);
            $table->index(['batch_id']);
            $table->index(['source_type', 'source_reference']);
            $table->index(['discrepancy_type']);
            $table->index(['requires_approval', 'approved_at']);
            $table->index(['flagged_for_review']);
            $table->index(['exported_to_accounting']);
            $table->index(['match_type', 'match_confidence']);
            
            // Foreign key constraints
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_records');
    }
};
