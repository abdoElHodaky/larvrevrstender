<?php

namespace App\Services\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Image Processing Service Interface
 * 
 * Defines the contract for image processing service implementations
 * Handles image upload, processing, and optimization
 */
interface ImageProcessingServiceInterface
{
    /**
     * Process order image
     */
    public function processOrderImage(UploadedFile $file, string $imageType): array;

    /**
     * Delete image files
     */
    public function deleteOrderImages(array $imagePaths): void;

    /**
     * Get image URL
     */
    public function getImageUrl(string $path): string;

    /**
     * Validate image file
     */
    public function validateImage(UploadedFile $file): bool;

    /**
     * Get supported image formats
     */
    public function getSupportedFormats(): array;

    /**
     * Get maximum file size
     */
    public function getMaxFileSize(): int;

    /**
     * Resize image to specific dimensions
     */
    public function resizeImage(string $imagePath, int $width, int $height): string;

    /**
     * Generate image thumbnail
     */
    public function generateThumbnail(string $imagePath, int $size = 300): string;
}
