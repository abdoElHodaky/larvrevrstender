<?php

namespace App\Http\Controllers;

use Shared\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $avatar = $request->file('avatar');

            // Upload avatar to cloud storage
            $result = $this->fileUploadService->upload(
                $avatar,
                'user-service/avatars/' . $user->id,
                [
                    'optimize' => true,
                    'max_width' => 512,
                    'max_height' => 512,
                    'quality' => 90,
                    'crop' => true // Square crop for avatars
                ]
            );

            // Store avatar metadata in database
            $avatarData = [
                'user_id' => $user->id,
                'file_path' => $result['path'],
                'file_name' => $result['filename'],
                'original_name' => $avatar->getClientOriginalName(),
                'file_size' => $result['size'],
                'mime_type' => $avatar->getMimeType(),
                'storage_provider' => $result['provider'] ?? 's3',
                'url' => $result['url'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Update or create avatar record
            DB::table('user_avatars')->updateOrInsert(
                ['user_id' => $user->id],
                $avatarData
            );

            // Update user's avatar_url field if it exists
            if (DB::getSchemaBuilder()->hasColumn('users', 'avatar_url')) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['avatar_url' => $result['url']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar_url' => $result['url'],
                    'file_size' => $result['size'],
                    'dimensions' => $result['dimensions'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Avatar upload failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'file_name' => $avatar->getClientOriginalName() ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Avatar upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete user avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Get current avatar record
            $avatar = DB::table('user_avatars')
                ->where('user_id', $user->id)
                ->first();

            if (!$avatar) {
                return response()->json([
                    'success' => false,
                    'message' => 'No avatar found to delete',
                ], 404);
            }

            // Delete from cloud storage
            try {
                $this->fileUploadService->delete($avatar->file_path);
            } catch (\Exception $e) {
                Log::warning('Failed to delete avatar from cloud storage: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'file_path' => $avatar->file_path,
                ]);
            }

            // Delete from database
            DB::table('user_avatars')->where('user_id', $user->id)->delete();

            // Clear user's avatar_url field if it exists
            if (DB::getSchemaBuilder()->hasColumn('users', 'avatar_url')) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['avatar_url' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Avatar deletion failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Avatar deletion failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get user avatar information
     */
    public function getAvatar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $avatar = DB::table('user_avatars')
                ->where('user_id', $user->id)
                ->first();

            if (!$avatar) {
                return response()->json([
                    'success' => true,
                    'message' => 'No avatar found',
                    'data' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'avatar_url' => $avatar->url,
                    'file_size' => $avatar->file_size,
                    'uploaded_at' => $avatar->created_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get avatar failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve avatar information',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
