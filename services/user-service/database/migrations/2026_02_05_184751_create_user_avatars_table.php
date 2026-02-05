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
        Schema::create('user_avatars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('file_path', 255);
            $table->string('file_name', 255);
            $table->string('original_name', 255);
            $table->unsignedInteger('file_size');
            $table->string('mime_type', 100);
            $table->string('storage_provider', 50)->default('s3');
            $table->text('url');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            // Indexes
            $table->unique('user_id');
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
        Schema::dropIfExists('user_avatars');
    }
};
