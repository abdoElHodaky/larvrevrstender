<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAvatar extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_avatars';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'file_path',
        'file_name',
        'original_name',
        'file_size',
        'mime_type',
        'storage_provider',
        'url',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
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
     * Get the user that owns the avatar.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Check if the avatar is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Get the file extension from mime type.
     */
    public function getFileExtensionAttribute(): string
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        return $mimeToExt[$this->mime_type] ?? 'unknown';
    }

    /**
     * Get the CDN URL for the avatar.
     */
    public function getCdnUrlAttribute(): string
    {
        // If URL already includes CDN domain, return as-is
        if (str_contains($this->url, 'cdn.')) {
            return $this->url;
        }

        // Otherwise, construct CDN URL
        $cdnDomain = config('filesystems.cdn_domain', 'https://cdn.reversetender.com');

        return $cdnDomain.'/'.ltrim($this->file_path, '/');
    }

    /**
     * Scope to get avatars by storage provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('storage_provider', $provider);
    }

    /**
     * Scope to get avatars larger than specified size.
     */
    public function scopeLargerThan($query, int $sizeInBytes)
    {
        return $query->where('file_size', '>', $sizeInBytes);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically delete from cloud storage when model is deleted
        static::deleting(function ($avatar) {
            if ($avatar->isForceDeleting()) {
                // Only attempt cloud deletion on force delete
                try {
                    app(\Shared\Services\FileUploadService::class)->delete($avatar->file_path);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'Failed to delete avatar from cloud storage during model deletion',
                        [
                            'avatar_id' => $avatar->id,
                            'file_path' => $avatar->file_path,
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            }
        });
    }
}
