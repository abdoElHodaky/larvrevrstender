<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'product_images';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'auction_id',
        'file_path',
        'file_name',
        'original_name',
        'file_size',
        'mime_type',
        'storage_provider',
        'url',
        'alt_text',
        'sort_order',
        'is_primary',
        'width',
        'height',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'file_path', // Hide internal storage path for security
    ];

    /**
     * Get the auction that owns the image.
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * Get the formatted file size in human readable format.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the CDN URL for the image.
     */
    public function getCdnUrlAttribute(): string
    {
        if (str_contains($this->url, 'cdn.')) {
            return $this->url;
        }

        $cdnDomain = config('filesystems.cdn_domain', 'https://cdn.reversetender.com');
        return $cdnDomain . '/' . ltrim($this->file_path, '/');
    }

    /**
     * Get the image dimensions as a string.
     */
    public function getDimensionsAttribute(): ?string
    {
        if ($this->width && $this->height) {
            return $this->width . 'x' . $this->height;
        }
        return null;
    }

    /**
     * Get the aspect ratio of the image.
     */
    public function getAspectRatioAttribute(): ?float
    {
        if ($this->width && $this->height && $this->height > 0) {
            return round($this->width / $this->height, 2);
        }
        return null;
    }

    /**
     * Check if the image is landscape orientation.
     */
    public function isLandscape(): bool
    {
        return $this->aspect_ratio && $this->aspect_ratio > 1;
    }

    /**
     * Check if the image is portrait orientation.
     */
    public function isPortrait(): bool
    {
        return $this->aspect_ratio && $this->aspect_ratio < 1;
    }

    /**
     * Check if the image is square.
     */
    public function isSquare(): bool
    {
        return $this->aspect_ratio && $this->aspect_ratio == 1;
    }

    /**
     * Scope to get primary images.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get images ordered by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Scope to get images by storage provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('storage_provider', $provider);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set default sort order
        static::creating(function ($image) {
            if (is_null($image->sort_order)) {
                $maxOrder = static::where('auction_id', $image->auction_id)->max('sort_order');
                $image->sort_order = $maxOrder ? $maxOrder + 1 : 1;
            }
        });

        // Automatically delete from cloud storage when model is deleted
        static::deleting(function ($image) {
            if ($image->isForceDeleting()) {
                try {
                    app(\Shared\Services\FileUploadService::class)->delete($image->file_path);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'Failed to delete product image from cloud storage during model deletion',
                        [
                            'image_id' => $image->id,
                            'file_path' => $image->file_path,
                            'error' => $e->getMessage()
                        ]
                    );
                }
            }
        });
    }
}

