<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VinScan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'image_path',
        'original_filename',
        'vin_number',
        'confidence_score',
        'status',
        'processed_at',
        'user_id',
        'processing_time_ms',
        // Cloud storage integration fields
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'storage_provider',
        'url',
        'cdn_url',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'confidence_score' => 'decimal:4',
        'processed_at' => 'datetime',
        'processing_time_ms' => 'integer',
        'file_size' => 'integer',
    ];

    /**
     * Get the OCR results for this scan.
     */
    public function ocrResults(): HasMany
    {
        return $this->hasMany(OcrResult::class);
    }

    /**
     * Check if the scan was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed' &&
               ! empty($this->vin_number) &&
               $this->confidence_score >= 0.8;
    }

    /**
     * Get the formatted VIN number.
     */
    public function getFormattedVinAttribute(): string
    {
        return strtoupper(str_replace(['-', ' '], '', $this->vin_number ?? ''));
    }

    /**
     * Get the formatted file size in human readable format.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get the CDN URL for the image.
     */
    public function getCdnUrlAttribute(): ?string
    {
        if ($this->cdn_url) {
            return $this->cdn_url;
        }

        if ($this->url && str_contains($this->url, 'cdn.')) {
            return $this->url;
        }

        if ($this->file_path) {
            $cdnDomain = config('filesystems.cdn_domain', 'https://cdn.reversetender.com');

            return $cdnDomain.'/'.ltrim($this->file_path, '/');
        }

        return $this->url;
    }

    /**
     * Check if the scan has cloud storage metadata.
     */
    public function hasCloudStorageMetadata(): bool
    {
        return ! empty($this->file_path) && ! empty($this->storage_provider);
    }

    /**
     * Get the file extension from mime type.
     */
    public function getFileExtensionAttribute(): string
    {
        if (! $this->mime_type) {
            return 'unknown';
        }

        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
        ];

        return $mimeToExt[$this->mime_type] ?? 'unknown';
    }

    /**
     * Scope to get scans with cloud storage metadata.
     */
    public function scopeWithCloudStorage($query)
    {
        return $query->whereNotNull('file_path')
            ->whereNotNull('storage_provider');
    }

    /**
     * Scope to get scans by storage provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('storage_provider', $provider);
    }
}
