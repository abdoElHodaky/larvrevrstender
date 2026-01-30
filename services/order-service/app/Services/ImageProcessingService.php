<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

/**
 * Image Processing Service
 * 
 * Handles image upload, processing, and optimization for orders
 */
class ImageProcessingService
{
    protected array $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    protected int $maxFileSize = 10240; // 10MB in KB
    protected int $thumbnailSize = 300;
    protected int $largeSize = 1200;
    protected int $quality = 85;

    /**
     * Process order image
     */
    public function processOrderImage(UploadedFile $file, string $imageType): array
    {
        // Validate file
        $this->validateImage($file);

        // Generate unique filename
        $filename = $this->generateFilename($file);
        
        // Create different sizes
        $originalPath = $this->storeOriginal($file, $filename, $imageType);
        $thumbnailPath = $this->createThumbnail($file, $filename, $imageType);
        $largePath = $this->createLargeImage($file, $filename, $imageType);

        // Extract metadata
        $metadata = $this->extractMetadata($file);

        return [
            'path' => $originalPath,
            'thumbnail_path' => $thumbnailPath,
            'large_path' => $largePath,
            'metadata' => $metadata
        ];
    }

    /**
     * Validate uploaded image
     */
    protected function validateImage(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize * 1024) {
            throw new \Exception("File size exceeds maximum allowed size of {$this->maxFileSize}KB");
        }

        // Check file type
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedTypes)) {
            throw new \Exception("File type '{$extension}' is not allowed. Allowed types: " . implode(', ', $this->allowedTypes));
        }

        // Check if file is actually an image
        if (!getimagesize($file->getPathname())) {
            throw new \Exception('File is not a valid image');
        }
    }

    /**
     * Generate unique filename
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        return uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Store original image
     */
    protected function storeOriginal(UploadedFile $file, string $filename, string $imageType): string
    {
        $path = "orders/{$imageType}/original/{$filename}";
        Storage::disk('s3')->putFileAs("orders/{$imageType}/original", $file, $filename);
        return $path;
    }

    /**
     * Create thumbnail
     */
    protected function createThumbnail(UploadedFile $file, string $filename, string $imageType): string
    {
        $image = Image::make($file->getPathname())
            ->fit($this->thumbnailSize, $this->thumbnailSize)
            ->encode('jpg', $this->quality);

        $thumbnailFilename = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $path = "orders/{$imageType}/thumbnails/{$thumbnailFilename}";
        
        Storage::disk('s3')->put($path, $image->stream());
        
        return $path;
    }

    /**
     * Create large image
     */
    protected function createLargeImage(UploadedFile $file, string $filename, string $imageType): string
    {
        $image = Image::make($file->getPathname())
            ->resize($this->largeSize, $this->largeSize, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->encode('jpg', $this->quality);

        $largeFilename = 'large_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $path = "orders/{$imageType}/large/{$largeFilename}";
        
        Storage::disk('s3')->put($path, $image->stream());
        
        return $path;
    }

    /**
     * Extract image metadata
     */
    protected function extractMetadata(UploadedFile $file): array
    {
        $imageInfo = getimagesize($file->getPathname());
        
        return [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $imageInfo[0] ?? null,
            'height' => $imageInfo[1] ?? null,
            'uploaded_at' => now()->toISOString()
        ];
    }

    /**
     * Delete image files
     */
    public function deleteOrderImages(array $imagePaths): void
    {
        foreach ($imagePaths as $path) {
            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }
    }

    /**
     * Get image URL
     */
    public function getImageUrl(string $path): string
    {
        return Storage::disk('s3')->url($path);
    }
}
