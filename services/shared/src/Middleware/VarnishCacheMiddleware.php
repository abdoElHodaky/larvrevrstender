<?php

namespace Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class VarnishCacheMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $ttl
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $ttl = null)
    {
        $response = $next($request);

        // Only apply caching to GET and HEAD requests
        if (!in_array($request->method(), ['GET', 'HEAD'])) {
            return $response;
        }

        // Don't cache if there are errors
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        // Don't cache if user is authenticated (has authorization header)
        if ($request->hasHeader('Authorization')) {
            return $response;
        }

        // Set cache headers
        $this->setCacheHeaders($response, $ttl);

        return $response;
    }

    /**
     * Set appropriate cache headers for Varnish.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  string|null  $ttl
     * @return void
     */
    private function setCacheHeaders(SymfonyResponse $response, ?string $ttl = null): void
    {
        $maxAge = $ttl ? (int) $ttl : (int) config('cache.varnish.ttl', 300);
        $isPublic = config('cache.varnish.public', true);

        // Set Cache-Control header
        $cacheControl = $isPublic ? 'public' : 'private';
        $cacheControl .= ", max-age={$maxAge}";
        
        // Add s-maxage for shared caches like Varnish
        if ($isPublic) {
            $cacheControl .= ", s-maxage={$maxAge}";
        }

        $response->headers->set('Cache-Control', $cacheControl);

        // Set Expires header
        $expires = now()->addSeconds($maxAge);
        $response->headers->set('Expires', $expires->toRfc7231String());

        // Set ETag if enabled
        if (config('cache.varnish.etag', true)) {
            $etag = md5($response->getContent());
            $response->headers->set('ETag', '"' . $etag . '"');
        }

        // Set Last-Modified if enabled
        if (config('cache.varnish.last_modified', true)) {
            $response->headers->set('Last-Modified', now()->toRfc7231String());
        }

        // Add Vary header for content negotiation
        $response->headers->set('Vary', 'Accept, Accept-Encoding, Accept-Language');

        // Add custom headers for Varnish debugging
        $response->headers->set('X-Cache-TTL', $maxAge);
        $response->headers->set('X-Cache-Tags', $this->generateCacheTags());
    }

    /**
     * Generate cache tags for cache invalidation.
     *
     * @return string
     */
    private function generateCacheTags(): string
    {
        $tags = [
            'service:' . config('app.name', 'unknown'),
            'version:' . config('app.version', '1.0.0'),
        ];

        return implode(' ', $tags);
    }

    /**
     * Purge cache for specific URL patterns.
     *
     * @param  array  $patterns
     * @return bool
     */
    public static function purgeCache(array $patterns): bool
    {
        if (!config('cache.varnish.enabled', false)) {
            return false;
        }

        $varnishHost = config('cache.varnish.host', 'varnish');
        $varnishPort = config('cache.varnish.admin_port', 6081);

        foreach ($patterns as $pattern) {
            try {
                $socket = fsockopen($varnishHost, $varnishPort, $errno, $errstr, 5);
                if (!$socket) {
                    continue;
                }

                $command = "ban req.url ~ \"{$pattern}\"\n";
                fwrite($socket, $command);
                fclose($socket);
            } catch (\Exception $e) {
                // Log error but don't fail
                logger()->warning('Varnish cache purge failed', [
                    'pattern' => $pattern,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return true;
    }

    /**
     * Purge all cache.
     *
     * @return bool
     */
    public static function purgeAll(): bool
    {
        return self::purgeCache(['.*']);
    }
}
