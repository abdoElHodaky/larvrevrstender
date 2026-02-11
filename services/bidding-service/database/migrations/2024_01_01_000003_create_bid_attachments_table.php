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
        Schema::create('bid_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bid_id');
            $table->enum('attachment_type', ['document', 'image', 'certificate', 'proof_of_funds', 'technical_spec', 'other']);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->string('storage_provider')->default('local');
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('bid_id')->references('id')->on('bids')->onDelete('cascade');
            $table->index(['bid_id', 'attachment_type']);
            $table->index(['attachment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_attachments');
    }
};
