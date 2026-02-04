<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;

class UtilityProcedure extends BaseProcedure
{
    /**
     * Generate UUID
     * 
     * @param array $params
     * @return array
     */
    public function generateUuid(array $params = []): array
    {
        return $this->executeWithLogging('Utility@generateUuid', $params, function () use ($params) {
            $version = $params['version'] ?? 4;
            $count = $params['count'] ?? 1;
            
            if ($count > 100) {
                throw new \InvalidArgumentException('Maximum 100 UUIDs can be generated at once');
            }
            
            $uuids = [];
            for ($i = 0; $i < $count; $i++) {
                $uuids[] = match($version) {
                    1 => Str::uuid1()->toString(),
                    4 => Str::uuid()->toString(),
                    default => Str::uuid()->toString(),
                };
            }
            
            return [
                'uuids' => $count === 1 ? $uuids[0] : $uuids,
                'count' => $count,
                'version' => $version,
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Hash password
     * 
     * @param array $params
     * @return array
     */
    public function hashPassword(array $params): array
    {
        $this->validate($params, [
            'password' => 'required|string|min:8|max:255',
            'algorithm' => 'sometimes|string|in:bcrypt,argon2i,argon2id',
        ]);

        return $this->executeWithLogging('Utility@hashPassword', $params, function () use ($params) {
            $algorithm = $params['algorithm'] ?? 'bcrypt';
            
            $hash = match($algorithm) {
                'argon2i' => Hash::driver('argon2i')->make($params['password']),
                'argon2id' => Hash::driver('argon2id')->make($params['password']),
                default => Hash::make($params['password']),
            };
            
            return [
                'hash' => $hash,
                'algorithm' => $algorithm,
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Verify password hash
     * 
     * @param array $params
     * @return array
     */
    public function verifyPassword(array $params): array
    {
        $this->validate($params, [
            'password' => 'required|string',
            'hash' => 'required|string',
        ]);

        return $this->executeWithLogging('Utility@verifyPassword', $params, function () use ($params) {
            $isValid = Hash::check($params['password'], $params['hash']);
            
            return [
                'valid' => $isValid,
                'verified_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Generate random string
     * 
     * @param array $params
     * @return array
     */
    public function generateRandomString(array $params): array
    {
        $this->validate($params, [
            'length' => 'required|integer|min:1|max:1000',
            'type' => 'sometimes|string|in:alphanumeric,alphabetic,numeric,mixed,hex,base64',
            'count' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('Utility@generateRandomString', $params, function () use ($params) {
            $type = $params['type'] ?? 'alphanumeric';
            $length = $params['length'];
            $count = $params['count'] ?? 1;
            
            $strings = [];
            for ($i = 0; $i < $count; $i++) {
                $strings[] = $this->generateString($length, $type);
            }
            
            return [
                'strings' => $count === 1 ? $strings[0] : $strings,
                'count' => $count,
                'length' => $length,
                'type' => $type,
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Encrypt data
     * 
     * @param array $params
     * @return array
     */
    public function encrypt(array $params): array
    {
        $this->validate($params, [
            'data' => 'required|string|max:10000',
            'serialize' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Utility@encrypt', $params, function () use ($params) {
            $serialize = $params['serialize'] ?? false;
            
            $encrypted = $serialize 
                ? Crypt::encrypt($params['data'])
                : Crypt::encryptString($params['data']);
            
            return [
                'encrypted' => $encrypted,
                'serialized' => $serialize,
                'encrypted_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Decrypt data
     * 
     * @param array $params
     * @return array
     */
    public function decrypt(array $params): array
    {
        $this->validate($params, [
            'encrypted_data' => 'required|string',
            'serialize' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Utility@decrypt', $params, function () use ($params) {
            $serialize = $params['serialize'] ?? false;
            
            try {
                $decrypted = $serialize 
                    ? Crypt::decrypt($params['encrypted_data'])
                    : Crypt::decryptString($params['encrypted_data']);
                
                return [
                    'decrypted' => $decrypted,
                    'serialized' => $serialize,
                    'decrypted_at' => now()->toISOString(),
                ];
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Failed to decrypt data: ' . $e->getMessage());
            }
        });
    }

    /**
     * Generate slug from text
     * 
     * @param array $params
     * @return array
     */
    public function generateSlug(array $params): array
    {
        $this->validate($params, [
            'text' => 'required|string|max:500',
            'separator' => 'sometimes|string|max:5',
            'language' => 'sometimes|string|max:5',
        ]);

        return $this->executeWithLogging('Utility@generateSlug', $params, function () use ($params) {
            $separator = $params['separator'] ?? '-';
            $language = $params['language'] ?? 'en';
            
            $slug = Str::slug($params['text'], $separator, $language);
            
            return [
                'slug' => $slug,
                'original_text' => $params['text'],
                'separator' => $separator,
                'language' => $language,
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Format text with various options
     * 
     * @param array $params
     * @return array
     */
    public function formatText(array $params): array
    {
        $this->validate($params, [
            'text' => 'required|string|max:10000',
            'operations' => 'required|array',
            'operations.*' => 'string|in:upper,lower,title,camel,snake,kebab,studly,plural,singular,limit,trim',
            'limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        return $this->executeWithLogging('Utility@formatText', $params, function () use ($params) {
            $text = $params['text'];
            $operations = $params['operations'];
            $limit = $params['limit'] ?? null;
            
            foreach ($operations as $operation) {
                $text = match($operation) {
                    'upper' => Str::upper($text),
                    'lower' => Str::lower($text),
                    'title' => Str::title($text),
                    'camel' => Str::camel($text),
                    'snake' => Str::snake($text),
                    'kebab' => Str::kebab($text),
                    'studly' => Str::studly($text),
                    'plural' => Str::plural($text),
                    'singular' => Str::singular($text),
                    'limit' => $limit ? Str::limit($text, $limit) : $text,
                    'trim' => trim($text),
                    default => $text,
                };
            }
            
            return [
                'formatted_text' => $text,
                'original_text' => $params['text'],
                'operations' => $operations,
                'formatted_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Cache data with TTL
     * 
     * @param array $params
     * @return array
     */
    public function cacheData(array $params): array
    {
        $this->validate($params, [
            'key' => 'required|string|max:250',
            'data' => 'required',
            'ttl' => 'sometimes|integer|min:1|max:86400', // Max 24 hours
        ]);

        return $this->executeWithLogging('Utility@cacheData', $params, function () use ($params) {
            $key = $params['key'];
            $data = $params['data'];
            $ttl = $params['ttl'] ?? 3600; // Default 1 hour
            
            Cache::put($key, $data, $ttl);
            
            return [
                'cached' => true,
                'key' => $key,
                'ttl' => $ttl,
                'expires_at' => now()->addSeconds($ttl)->toISOString(),
                'cached_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Retrieve cached data
     * 
     * @param array $params
     * @return array
     */
    public function getCachedData(array $params): array
    {
        $this->validate($params, [
            'key' => 'required|string|max:250',
            'default' => 'sometimes',
        ]);

        return $this->executeWithLogging('Utility@getCachedData', $params, function () use ($params) {
            $key = $params['key'];
            $default = $params['default'] ?? null;
            
            $data = Cache::get($key, $default);
            $exists = Cache::has($key);
            
            return [
                'data' => $data,
                'exists' => $exists,
                'key' => $key,
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Generate string based on type
     * 
     * @param int $length
     * @param string $type
     * @return string
     */
    private function generateString(int $length, string $type): string
    {
        return match($type) {
            'alphabetic' => $this->randomString($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'),
            'numeric' => $this->randomString($length, '0123456789'),
            'alphanumeric' => $this->randomString($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
            'mixed' => $this->randomString($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'),
            'hex' => bin2hex(random_bytes(ceil($length / 2))),
            'base64' => substr(base64_encode(random_bytes($length)), 0, $length),
            default => $this->randomString($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
        };
    }

    /**
     * Generate random string from character set
     * 
     * @param int $length
     * @param string $characters
     * @return string
     */
    private function randomString(int $length, string $characters): string
    {
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
}
