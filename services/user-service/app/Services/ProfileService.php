<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAvatar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Shared\Services\FileUploadService;

class ProfileService
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Upload and process user avatar.
     */
    public function uploadAvatar(User $user, UploadedFile $file, array $options = []): UserAvatar
    {
        // Validate file
        $this->validateAvatarFile($file);

        DB::beginTransaction();
        try {
            // Delete existing avatar if it exists
            if ($user->hasAvatar()) {
                $this->deleteAvatar($user, false); // Don't commit transaction yet
            }

            // Upload to cloud storage
            $uploadOptions = array_merge([
                'optimize' => true,
                'max_width' => 512,
                'max_height' => 512,
                'quality' => 90,
                'crop' => true,
            ], $options);

            $result = $this->fileUploadService->upload(
                $file,
                'user-service/avatars/'.$user->id,
                $uploadOptions
            );

            // Create avatar record
            $avatar = UserAvatar::create([
                'user_id' => $user->id,
                'file_path' => $result['path'],
                'file_name' => $result['filename'],
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $result['size'],
                'mime_type' => $file->getMimeType(),
                'storage_provider' => $result['provider'] ?? 's3',
                'url' => $result['url'],
            ]);

            // Update user's avatar_url field if column exists
            if ($this->hasAvatarUrlColumn()) {
                $user->update(['avatar_url' => $result['url']]);
            }

            DB::commit();

            // Fire avatar uploaded event
            event(new \App\Events\AvatarUploaded($user, $avatar));

            Log::info('Avatar uploaded successfully', [
                'user_id' => $user->id,
                'avatar_id' => $avatar->id,
                'file_size' => $result['size'],
                'storage_provider' => $result['provider'] ?? 's3',
            ]);

            return $avatar;

        } catch (\Exception $e) {
            DB::rollBack();

            // Attempt to clean up uploaded file
            if (isset($result['path'])) {
                try {
                    $this->fileUploadService->delete($result['path']);
                } catch (\Exception $cleanupException) {
                    Log::warning('Failed to cleanup uploaded file after avatar creation failure', [
                        'file_path' => $result['path'],
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            Log::error('Avatar upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete user avatar.
     */
    public function deleteAvatar(User $user, bool $commitTransaction = true): bool
    {
        $avatar = $user->avatar;

        if (! $avatar) {
            return false;
        }

        if ($commitTransaction) {
            DB::beginTransaction();
        }

        try {
            // Delete from cloud storage
            try {
                $this->fileUploadService->delete($avatar->file_path);
            } catch (\Exception $e) {
                Log::warning('Failed to delete avatar from cloud storage', [
                    'user_id' => $user->id,
                    'avatar_id' => $avatar->id,
                    'file_path' => $avatar->file_path,
                    'error' => $e->getMessage(),
                ]);
            }

            // Delete from database
            $avatar->delete();

            // Clear user's avatar_url field if column exists
            if ($this->hasAvatarUrlColumn()) {
                $user->update(['avatar_url' => null]);
            }

            if ($commitTransaction) {
                DB::commit();
            }

            // Fire avatar deleted event
            event(new \App\Events\AvatarDeleted($user, $avatar));

            Log::info('Avatar deleted successfully', [
                'user_id' => $user->id,
                'avatar_id' => $avatar->id,
            ]);

            return true;

        } catch (\Exception $e) {
            if ($commitTransaction) {
                DB::rollBack();
            }

            Log::error('Avatar deletion failed', [
                'user_id' => $user->id,
                'avatar_id' => $avatar->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get user avatar information.
     */
    public function getAvatar(User $user): ?UserAvatar
    {
        return $user->avatar;
    }

    /**
     * Get avatar URL with fallback to default.
     */
    public function getAvatarUrl(User $user, ?string $defaultUrl = null): ?string
    {
        if ($user->hasAvatar()) {
            return $user->avatar->cdn_url;
        }

        return $defaultUrl ?? $this->getDefaultAvatarUrl();
    }

    /**
     * Get default avatar URL.
     */
    public function getDefaultAvatarUrl(): string
    {
        return config('app.default_avatar_url', '/images/default-avatar.png');
    }

    /**
     * Validate avatar file.
     */
    protected function validateAvatarFile(UploadedFile $file): void
    {
        $validator = Validator::make(['avatar' => $file], [
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120', // 5MB max
                'dimensions:min_width=100,min_height=100,max_width=4096,max_height=4096',
            ],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Check if users table has avatar_url column.
     */
    protected function hasAvatarUrlColumn(): bool
    {
        return DB::getSchemaBuilder()->hasColumn('users', 'avatar_url');
    }

    /**
     * Get avatar statistics for user.
     */
    public function getAvatarStats(User $user): array
    {
        $avatar = $user->avatar;

        if (! $avatar) {
            return [
                'has_avatar' => false,
                'upload_date' => null,
                'file_size' => null,
                'storage_provider' => null,
            ];
        }

        return [
            'has_avatar' => true,
            'upload_date' => $avatar->created_at,
            'file_size' => $avatar->file_size,
            'formatted_file_size' => $avatar->formatted_file_size,
            'storage_provider' => $avatar->storage_provider,
            'mime_type' => $avatar->mime_type,
            'dimensions' => $this->getImageDimensions($avatar),
        ];
    }

    /**
     * Get image dimensions if available.
     */
    protected function getImageDimensions(UserAvatar $avatar): ?array
    {
        try {
            // This would require additional metadata storage or image analysis
            // For now, return null - could be enhanced to store dimensions during upload
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Bulk delete avatars for multiple users.
     */
    public function bulkDeleteAvatars(array $userIds): array
    {
        $results = [];

        foreach ($userIds as $userId) {
            try {
                $user = User::findOrFail($userId);
                $deleted = $this->deleteAvatar($user);
                $results[$userId] = ['success' => $deleted];
            } catch (\Exception $e) {
                $results[$userId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
