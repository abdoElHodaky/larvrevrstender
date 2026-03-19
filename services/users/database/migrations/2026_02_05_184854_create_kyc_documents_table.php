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
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('document_type', [
                'identity', 'passport', 'drivers_license', 'proof_of_address',
                'business_registration', 'tax_certificate', 'bank_statement', 'utility_bill',
            ]);
            $table->string('file_path', 255);
            $table->string('file_name', 255);
            $table->string('original_name', 255);
            $table->unsignedInteger('file_size');
            $table->string('mime_type', 100);
            $table->string('storage_provider', 50)->default('s3');
            $table->text('url');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', [
                'pending', 'under_review', 'approved', 'rejected',
                'resubmission_required', 'superseded', 'deleted',
            ])->default('pending');
            $table->boolean('encryption_enabled')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            // Indexes
            $table->index(['user_id', 'document_type']);
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('version');
            $table->index('created_at');

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
