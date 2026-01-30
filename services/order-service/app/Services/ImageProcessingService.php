<?php

namespace App\Services;

use App\Services\Contracts\ImageProcessingServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

/**
 * Image Processing Service
 * 
 * Handles image upload, processing, and optimization for orders
 */
class ImageProcessingService implements ImageProcessingServiceInterface
{
    protected array $config;
    protected array $allowedTypes;
    protected int $maxFileSize;
    protected int $thumbnailSize;
    protected int $largeSize;
    protected int $quality;

    public function __construct(array $storageConfig, array $processingConfig)
    {
        $this->config = $storageConfig;
        $this->allowedTypes = $processingConfig['allowed_types'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $this->maxFileSize = $processingConfig['max_file_size'] ?? 10240; // 10MB in KB
        $this->thumbnailSize = $processingConfig['thumbnail_size'] ?? 300;
        $this->largeSize = $processingConfig['large_size'] ?? 1200;
        $this->quality = $processingConfig['quality'] ?? 85;
    }

    /**
     * Process order image
     */
    public function processOrderImage(UploadedFile $file, string $imageType): array
    {
        // Validate file
        $this->validateImageInternal($file);

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
     * Validate image file
     */
    public function validateImage(UploadedFile $file): bool
    {
        try {
            $this->validateImageInternal($file);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate uploaded image (internal)
     */
    protected function validateImageInternal(UploadedFile $file): void
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

    /**
     * Get supported image formats
     */
    public function getSupportedFormats(): array
    {
        return $this->allowedTypes;
    }

    /**
     * Get maximum file size
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * Resize image to specific dimensions
     */
    public function resizeImage(string $imagePath, int $width, int $height): string
    {
        $image = Image::make(Storage::disk('s3')->get($imagePath))
            ->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->encode('jpg', $this->quality);

        $resizedPath = str_replace('.', "_resized_{$width}x{$height}.", $imagePath);
        Storage::disk('s3')->put($resizedPath, $image->stream());

        return $resizedPath;
    }

    /**
     * Generate image thumbnail
     */
    public function generateThumbnail(string $imagePath, int $size = 300): string
    {
        $image = Image::make(Storage::disk('s3')->get($imagePath))
            ->fit($size, $size)
            ->encode('jpg', $this->quality);

        $thumbnailPath = str_replace('.', "_thumb_{$size}.", $imagePath);
        Storage::disk('s3')->put($thumbnailPath, $image->stream());

        return $thumbnailPath;
    }
}
