<?php

namespace Shared\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Exception;

class FileUploadService
{
    protected $disk;
    protected $serviceName;
    protected $config;

    public function __construct(string $serviceName = 'default')
    {
        $this->serviceName = $serviceName;
        $this->disk = config('filesystems.default', 's3');
        $this->config = config('filesystems.upload.services.' . $serviceName, config('filesystems.upload'));
    }

    /**
     * Upload a file to cloud storage
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function upload(UploadedFile $file, string $folder = '', array $options = []): array
    {
        // Validate file
        $this->validateFile($file);

        // Generate unique filename
        $filename = $this->generateFilename($file, $options);

        // Determine storage path
        $path = $this->buildPath($folder, $filename);

        // Process file if it's an image
        if ($this->isImage($file) && ($options['optimize'] ?? true)) {
            $file = $this->optimizeImage($file, $options);
        }

        // Upload to storage
        $uploaded = Storage::disk($this->disk)->putFileAs(
            dirname($path),
            $file,
            basename($path),
            $this->getStorageOptions($options)
        );

        if (!$uploaded) {
            throw new Exception('Failed to upload file to storage');
        }

        // Generate URLs
        $urls = $this->generateUrls($uploaded);

        return [
            'success' => true,
            'path' => $uploaded,
            'filename' => basename($uploaded),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'urls' => $urls,
            'metadata' => $this->getFileMetadata($file, $options),
        ];
    }

    /**
     * Upload multiple files
     *
     * @param array $files
     * @param string $folder
     * @param array $options
     * @return array
     */
    public function uploadMultiple(array $files, string $folder = '', array $options = []): array
    {
        $results = [];
        $errors = [];

        foreach ($files as $index => $file) {
            try {
                $results[] = $this->upload($file, $folder, $options);
            } catch (Exception $e) {
                $errors[$index] = $e->getMessage();
            }
        }

        return [
            'success' => empty($errors),
            'uploaded' => $results,
            'errors' => $errors,
            'total' => count($files),
            'successful' => count($results),
            'failed' => count($errors),
        ];
    }

    /**
     * Delete a file from storage
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Get file URL
     *
     * @param string $path
     * @param bool $temporary
     * @param int $expiration
     * @return string
     */
    public function getUrl(string $path, bool $temporary = false, int $expiration = 3600): string
    {
        if ($temporary) {
            return Storage::disk($this->disk)->temporaryUrl($path, now()->addSeconds($expiration));
        }

        // Use CDN URL if available
        if (config('filesystems.optimization.cdn_enabled')) {
            $cdnEndpoint = config('filesystems.optimization.cdn_endpoint');
            return rtrim($cdnEndpoint, '/') . '/' . ltrim($path, '/');
        }

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Check if file exists
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Get file size
     *
     * @param string $path
     * @return int
     */
    public function size(string $path): int
    {
        return Storage::disk($this->disk)->size($path);
    }

    /**
     * Copy file to backup storage
     *
     * @param string $path
     * @return bool
     */
    public function backup(string $path): bool
    {
        if (!$this->exists($path)) {
            return false;
        }

        $backupPath = 'backups/' . date('Y/m/d') . '/' . $path;
        
        return Storage::disk('backup')->put(
            $backupPath,
            Storage::disk($this->disk)->get($path)
        );
    }

    /**
     * Validate uploaded file
     *
     * @param UploadedFile $file
     * @throws Exception
     */
    protected function validateFile(UploadedFile $file): void
    {
        $maxSize = ($this->config['max_size'] ?? 10240) * 1024; // Convert KB to bytes
        $allowedTypes = $this->config['allowed_types'] ?? ['jpg', 'jpeg', 'png', 'pdf'];

        $validator = Validator::make([
            'file' => $file
        ], [
            'file' => [
                'required',
                'file',
                'max:' . ($maxSize / 1024), // Laravel expects KB
                'mimes:' . implode(',', $allowedTypes)
            ]
        ]);

        if ($validator->fails()) {
            throw new Exception('File validation failed: ' . implode(', ', $validator->errors()->all()));
        }
    }

    /**
     * Generate unique filename
     *
     * @param UploadedFile $file
     * @param array $options
     * @return string
     */
    protected function generateFilename(UploadedFile $file, array $options = []): string
    {
        if (isset($options['filename'])) {
            return $options['filename'] . '.' . $file->getClientOriginalExtension();
        }

        $prefix = $options['prefix'] ?? '';
        $timestamp = $options['include_timestamp'] ?? true ? '_' . time() : '';
        $random = Str::random(8);
        
        return $prefix . $random . $timestamp . '.' . $file->getClientOriginalExtension();
    }

    /**
     * Build storage path
     *
     * @param string $folder
     * @param string $filename
     * @return string
     */
    protected function buildPath(string $folder, string $filename): string
    {
        $servicePrefix = $this->config['path_prefix'] ?? $this->serviceName . '/';
        $datePath = date('Y/m/d') . '/';
        
        $path = $servicePrefix . $datePath;
        
        if (!empty($folder)) {
            $path .= trim($folder, '/') . '/';
        }
        
        return $path . $filename;
    }

    /**
     * Check if file is an image
     *
     * @param UploadedFile $file
     * @return bool
     */
    protected function isImage(UploadedFile $file): bool
    {
        $imageTypes = config('filesystems.upload.image_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        return in_array(strtolower($file->getClientOriginalExtension()), $imageTypes);
    }

    /**
     * Optimize image
     *
     * @param UploadedFile $file
     * @param array $options
     * @return UploadedFile
     */
    protected function optimizeImage(UploadedFile $file, array $options = []): UploadedFile
    {
        if (!config('filesystems.optimization.image_optimization', true)) {
            return $file;
        }

        try {
            $image = Image::make($file->getRealPath());
            
            // Resize if dimensions are specified
            if (isset($options['width']) || isset($options['height'])) {
                $image->resize(
                    $options['width'] ?? null,
                    $options['height'] ?? null,
                    function ($constraint) use ($options) {
                        if ($options['maintain_aspect_ratio'] ?? true) {
                            $constraint->aspectRatio();
                        }
                        if ($options['prevent_upsizing'] ?? true) {
                            $constraint->upsize();
                        }
                    }
                );
            }

            // Apply quality compression
            $quality = $options['quality'] ?? 85;
            $image->save(null, $quality);

            return $file;
        } catch (Exception $e) {
            // If image optimization fails, return original file
            return $file;
        }
    }

    /**
     * Get storage options
     *
     * @param array $options
     * @return array
     */
    protected function getStorageOptions(array $options = []): array
    {
        $storageOptions = [];

        // Set visibility
        if (isset($options['public']) && $options['public']) {
            $storageOptions['visibility'] = 'public';
        }

        // Set cache control
        if (config('filesystems.optimization.cache_control')) {
            $storageOptions['CacheControl'] = config('filesystems.optimization.cache_control');
        }

        // Set content type
        if (isset($options['content_type'])) {
            $storageOptions['ContentType'] = $options['content_type'];
        }

        return $storageOptions;
    }

    /**
     * Generate URLs for the uploaded file
     *
     * @param string $path
     * @return array
     */
    protected function generateUrls(string $path): array
    {
        $urls = [
            'private' => $this->getUrl($path),
            'temporary' => $this->getUrl($path, true, 3600),
        ];

        // Add CDN URL if enabled
        if (config('filesystems.optimization.cdn_enabled')) {
            $urls['cdn'] = $this->getUrl($path);
        }

        // Add public URL if file is public
        if (Storage::disk($this->disk)->getVisibility($path) === 'public') {
            $urls['public'] = Storage::disk('s3-public')->url($path);
        }

        return $urls;
    }

    /**
     * Get file metadata
     *
     * @param UploadedFile $file
     * @param array $options
     * @return array
     */
    protected function getFileMetadata(UploadedFile $file, array $options = []): array
    {
        $metadata = [
            'service' => $this->serviceName,
            'uploaded_at' => now()->toISOString(),
            'storage_provider' => env('STORAGE_PROVIDER', 'unknown'),
            'disk' => $this->disk,
        ];

        // Add image metadata if it's an image
        if ($this->isImage($file)) {
            try {
                $imageInfo = getimagesize($file->getRealPath());
                $metadata['image'] = [
                    'width' => $imageInfo[0] ?? null,
                    'height' => $imageInfo[1] ?? null,
                    'type' => $imageInfo['mime'] ?? null,
                ];
            } catch (Exception $e) {
                // Ignore if we can't get image info
            }
        }

        return $metadata;
    }
}
