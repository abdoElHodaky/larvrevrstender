# Multi-Tier Caching with Upstash Redis and MongoDB Atlas

This configuration creates a robust multi-tier architecture using Varnish → Upstash Redis for caching, and Redis → MongoDB Atlas for jobs/queues/sessions with shared configurations.

## 🏗️ **Architecture Overview**

### **Caching Tier:**
```
Varnish (L1) → Upstash Redis (L2) → Application
```

### **Jobs/Queues/Sessions Tier:**
```
Upstash Redis (Primary) → MongoDB Atlas (Fallback) → Application
```

---

## 🔧 **Laravel Configuration**

### **Environment Variables (.env)**

```env
# Application
APP_NAME="Reverse Tender"
APP_ENV=production
APP_DEBUG=false

# Primary Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reverse_tender
DB_USERNAME=app_user
DB_PASSWORD=secure-password

# MongoDB Atlas Configuration
MONGODB_DSN="mongodb+srv://YOUR_USERNAME:YOUR_PASSWORD@YOUR_CLUSTER.mongodb.net/reverse_tender?retryWrites=true&w=majority"
MONGODB_DATABASE=reverse_tender

# Upstash Redis (Primary for all Redis operations)
UPSTASH_REDIS_URL="rediss://default:YOUR_UPSTASH_PASSWORD@YOUR_REGION-redis.upstash.io:6380"
UPSTASH_REDIS_HOST=YOUR_REGION-redis.upstash.io
UPSTASH_REDIS_PASSWORD=YOUR_UPSTASH_PASSWORD
UPSTASH_REDIS_PORT=6380

# Redis Configuration (using Upstash)
REDIS_HOST=YOUR_REGION-redis.upstash.io
REDIS_PASSWORD=YOUR_UPSTASH_PASSWORD
REDIS_PORT=6380
REDIS_DB=0

# Cache Configuration
CACHE_DRIVER=multi_tier
CACHE_PREFIX=rt_cache

# Queue Configuration
QUEUE_CONNECTION=multi_tier
QUEUE_FAILED_DRIVER=mongodb

# Session Configuration
SESSION_DRIVER=multi_tier
SESSION_LIFETIME=120
SESSION_ENCRYPT=true

# Broadcasting
BROADCAST_DRIVER=redis
```

### **Database Configuration (config/database.php)**

```php
<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mongodb' => [
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_DSN'),
            'database' => env('MONGODB_DATABASE', 'reverse_tender'),
            'options' => [
                'retryWrites' => true,
                'w' => 'majority',
                'readPreference' => 'primary',
                'maxPoolSize' => 10,
                'serverSelectionTimeoutMS' => 5000,
                'connectTimeoutMS' => 10000,
                'socketTimeoutMS' => 30000,
            ],
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        // Upstash Redis (Primary)
        'default' => [
            'url' => env('UPSTASH_REDIS_URL'),
            'host' => env('REDIS_HOST', env('UPSTASH_REDIS_HOST')),
            'password' => env('REDIS_PASSWORD', env('UPSTASH_REDIS_PASSWORD')),
            'port' => env('REDIS_PORT', env('UPSTASH_REDIS_PORT', 6380)),
            'database' => env('REDIS_DB', '0'),
            'read_write_timeout' => 60,
            'options' => [
                'stream' => [
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ],
                ],
            ],
        ],

        // Upstash Redis (Alternative connection)
        'upstash' => [
            'url' => env('UPSTASH_REDIS_URL'),
            'host' => env('UPSTASH_REDIS_HOST'),
            'password' => env('UPSTASH_REDIS_PASSWORD'),
            'port' => env('UPSTASH_REDIS_PORT', 6380),
            'database' => 0,
            'read_write_timeout' => 60,
            'options' => [
                'stream' => [
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ],
                ],
            ],
        ],

        // Cache-specific Redis connections (using Upstash)
        'cache' => [
            'url' => env('UPSTASH_REDIS_URL'),
            'host' => env('REDIS_HOST', env('UPSTASH_REDIS_HOST')),
            'password' => env('REDIS_PASSWORD', env('UPSTASH_REDIS_PASSWORD')),
            'port' => env('REDIS_PORT', env('UPSTASH_REDIS_PORT', 6380)),
            'database' => env('REDIS_CACHE_DB', '1'),
            'options' => [
                'stream' => [
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ],
                ],
            ],
        ],

        'session' => [
            'url' => env('UPSTASH_REDIS_URL'),
            'host' => env('REDIS_HOST', env('UPSTASH_REDIS_HOST')),
            'password' => env('REDIS_PASSWORD', env('UPSTASH_REDIS_PASSWORD')),
            'port' => env('REDIS_PORT', env('UPSTASH_REDIS_PORT', 6380)),
            'database' => env('REDIS_SESSION_DB', '2'),
            'options' => [
                'stream' => [
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ],
                ],
            ],
        ],
    ],
];
```

### **Cache Configuration (config/cache.php)**

```php
<?php

return [
    'default' => env('CACHE_DRIVER', 'multi_tier'),

    'stores' => [
        'multi_tier' => [
            'driver' => 'multi_tier',
            'primary' => 'redis',
            'fallback' => 'upstash',
            'prefix' => env('CACHE_PREFIX', 'rt_cache'),
            'serializer' => 'igbinary',
            'compression' => 'lz4',
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',  # Now points to Upstash
            'lock_connection' => 'default',
        ],

        'upstash' => [
            'driver' => 'redis',
            'connection' => 'upstash',
            'lock_connection' => 'upstash',
        ],

        'varnish' => [
            'driver' => 'varnish',
            'host' => env('VARNISH_HOST', '127.0.0.1'),
            'port' => env('VARNISH_PORT', 6081),
            'secret' => env('VARNISH_SECRET'),
            'timeout' => 5,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'),
];
```

### **Queue Configuration (config/queue.php)**

```php
<?php

return [
    'default' => env('QUEUE_CONNECTION', 'multi_tier'),

    'connections' => [
        'multi_tier' => [
            'driver' => 'multi_tier',
            'primary' => 'upstash',  # Use Upstash as primary
            'fallback' => 'mongodb',
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',  # Points to Upstash
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

        'mongodb' => [
            'driver' => 'mongodb',
            'connection' => 'mongodb',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'upstash' => [
            'driver' => 'redis',
            'connection' => 'upstash',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

        // Priority queues
        'high_priority' => [
            'driver' => 'multi_tier',
            'primary' => 'upstash',  # Use Upstash as primary
            'fallback' => 'mongodb',
            'queue' => 'high',
            'retry_after' => 30,
        ],

        'low_priority' => [
            'driver' => 'multi_tier',
            'primary' => 'upstash',  # Use Upstash as primary
            'fallback' => 'mongodb',
            'queue' => 'low',
            'retry_after' => 300,
        ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'mongodb'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
```

### **Session Configuration (config/session.php)**

```php
<?php

return [
    'driver' => env('SESSION_DRIVER', 'multi_tier'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => env('SESSION_ENCRYPT', true),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_').'_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,
    'same_site' => 'lax',

    // Multi-tier session configuration
    'multi_tier' => [
        'primary' => 'upstash',  # Use Upstash as primary
        'fallback' => 'mongodb',
        'sync_interval' => 300, // 5 minutes
        'fallback_threshold' => 3, // Switch to fallback after 3 failures
    ],
];
```

---

## 🔌 **Custom Service Providers**

### **Multi-Tier Cache Service Provider**

Create `app/Providers/MultiTierCacheServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\CacheManager;
use App\Cache\MultiTierStore;

class MultiTierCacheServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->resolving('cache', function (CacheManager $cache) {
            $cache->extend('multi_tier', function ($app, $config) {
                $primary = $cache->store($config['primary']);
                $fallback = $cache->store($config['fallback']);
                
                return new MultiTierStore($primary, $fallback, $config);
            });
        });
    }

    public function boot()
    {
        //
    }
}
```

### **Multi-Tier Queue Service Provider**

Create `app/Providers/MultiTierQueueServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\QueueManager;
use App\Queue\MultiTierConnector;

class MultiTierQueueServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->resolving('queue', function (QueueManager $queue) {
            $queue->addConnector('multi_tier', function () {
                return new MultiTierConnector();
            });
        });
    }

    public function boot()
    {
        //
    }
}
```

---

## 🏪 **Multi-Tier Cache Store Implementation**

Create `app/Cache/MultiTierStore.php`:

```php
<?php

namespace App\Cache;

use Illuminate\Contracts\Cache\Store;
use Illuminate\Cache\TaggableStore;
use Exception;
use Illuminate\Support\Facades\Log;

class MultiTierStore extends TaggableStore implements Store
{
    protected $primary;
    protected $fallback;
    protected $config;
    protected $failureCount = 0;
    protected $maxFailures = 3;

    public function __construct($primary, $fallback, $config = [])
    {
        $this->primary = $primary;
        $fallback = $fallback;
        $this->config = $config;
        $this->maxFailures = $config['fallback_threshold'] ?? 3;
    }

    public function get($key)
    {
        try {
            // Try primary cache first
            if ($this->failureCount < $this->maxFailures) {
                $value = $this->primary->get($key);
                if ($value !== null) {
                    $this->resetFailureCount();
                    return $value;
                }
            }

            // Fallback to secondary cache
            $value = $this->fallback->get($key);
            if ($value !== null && $this->failureCount < $this->maxFailures) {
                // Sync back to primary cache
                $this->syncToPrimary($key, $value);
            }

            return $value;
        } catch (Exception $e) {
            Log::warning('Multi-tier cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function many(array $keys)
    {
        $values = [];
        
        try {
            if ($this->failureCount < $this->maxFailures) {
                $values = $this->primary->many($keys);
                $missingKeys = array_diff($keys, array_keys(array_filter($values)));
            } else {
                $missingKeys = $keys;
            }

            if (!empty($missingKeys)) {
                $fallbackValues = $this->fallback->many($missingKeys);
                $values = array_merge($values, $fallbackValues);
                
                // Sync missing values back to primary
                if ($this->failureCount < $this->maxFailures) {
                    $this->primary->putMany($fallbackValues, 3600);
                }
            }

            $this->resetFailureCount();
            return $values;
        } catch (Exception $e) {
            $this->incrementFailureCount();
            Log::warning('Multi-tier cache many failed', [
                'keys' => $keys,
                'error' => $e->getMessage()
            ]);
            return array_fill_keys($keys, null);
        }
    }

    public function put($key, $value, $seconds)
    {
        $success = false;

        try {
            // Always try to write to primary first
            if ($this->failureCount < $this->maxFailures) {
                $success = $this->primary->put($key, $value, $seconds);
                if ($success) {
                    $this->resetFailureCount();
                }
            }

            // Also write to fallback for redundancy
            $fallbackSuccess = $this->fallback->put($key, $value, $seconds);
            
            return $success || $fallbackSuccess;
        } catch (Exception $e) {
            $this->incrementFailureCount();
            Log::warning('Multi-tier cache put failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function putMany(array $values, $seconds)
    {
        try {
            $primarySuccess = false;
            if ($this->failureCount < $this->maxFailures) {
                $primarySuccess = $this->primary->putMany($values, $seconds);
                if ($primarySuccess) {
                    $this->resetFailureCount();
                }
            }

            $fallbackSuccess = $this->fallback->putMany($values, $seconds);
            
            return $primarySuccess || $fallbackSuccess;
        } catch (Exception $e) {
            $this->incrementFailureCount();
            Log::warning('Multi-tier cache putMany failed', [
                'count' => count($values),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function increment($key, $value = 1)
    {
        try {
            if ($this->failureCount < $this->maxFailures) {
                $result = $this->primary->increment($key, $value);
                if ($result !== false) {
                    // Sync to fallback
                    $this->fallback->put($key, $result, 3600);
                    return $result;
                }
            }

            return $this->fallback->increment($key, $value);
        } catch (Exception $e) {
            $this->incrementFailureCount();
            Log::warning('Multi-tier cache increment failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function decrement($key, $value = 1)
    {
        try {
            if ($this->failureCount < $this->maxFailures) {
                $result = $this->primary->decrement($key, $value);
                if ($result !== false) {
                    // Sync to fallback
                    $this->fallback->put($key, $result, 3600);
                    return $result;
                }
            }

            return $this->fallback->decrement($key, $value);
        } catch (Exception $e) {
            $this->incrementFailureCount();
            Log::warning('Multi-tier cache decrement failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function forever($key, $value)
    {
        try {
            $primarySuccess = false;
            if ($this->failureCount < $this->maxFailures) {
                $primarySuccess = $this->primary->forever($key, $value);
            }

            $fallbackSuccess = $this->fallback->forever($key, $value);
            
            return $primarySuccess || $fallbackSuccess;
        } catch (Exception $e) {
            $this->incrementFailureCount();
            Log::warning('Multi-tier cache forever failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function forget($key)
    {
        $primarySuccess = false;
        $fallbackSuccess = false;

        try {
            if ($this->failureCount < $this->maxFailures) {
                $primarySuccess = $this->primary->forget($key);
            }
        } catch (Exception $e) {
            Log::warning('Primary cache forget failed', ['key' => $key, 'error' => $e->getMessage()]);
        }

        try {
            $fallbackSuccess = $this->fallback->forget($key);
        } catch (Exception $e) {
            Log::warning('Fallback cache forget failed', ['key' => $key, 'error' => $e->getMessage()]);
        }

        return $primarySuccess || $fallbackSuccess;
    }

    public function flush()
    {
        $primarySuccess = false;
        $fallbackSuccess = false;

        try {
            if ($this->failureCount < $this->maxFailures) {
                $primarySuccess = $this->primary->flush();
            }
        } catch (Exception $e) {
            Log::warning('Primary cache flush failed', ['error' => $e->getMessage()]);
        }

        try {
            $fallbackSuccess = $this->fallback->flush();
        } catch (Exception $e) {
            Log::warning('Fallback cache flush failed', ['error' => $e->getMessage()]);
        }

        return $primarySuccess || $fallbackSuccess;
    }

    public function getPrefix()
    {
        return $this->config['prefix'] ?? '';
    }

    protected function syncToPrimary($key, $value)
    {
        try {
            $this->primary->put($key, $value, 3600);
        } catch (Exception $e) {
            Log::debug('Failed to sync to primary cache', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function incrementFailureCount()
    {
        $this->failureCount++;
        if ($this->failureCount >= $this->maxFailures) {
            Log::warning('Multi-tier cache switched to fallback mode', [
                'failure_count' => $this->failureCount
            ]);
        }
    }

    protected function resetFailureCount()
    {
        if ($this->failureCount > 0) {
            Log::info('Multi-tier cache primary restored', [
                'previous_failures' => $this->failureCount
            ]);
            $this->failureCount = 0;
        }
    }
}
```
