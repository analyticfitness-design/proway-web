<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Middleware;

use ProWay\Infrastructure\Http\Response;

/**
 * APCu-based sliding-window rate limiter.
 *
 * Limits to RATE_LIMIT_MAX requests per RATE_LIMIT_WINDOW seconds per IP.
 * Defaults: 60 requests / 60 seconds.
 *
 * Falls back to no-op when APCu is not available (e.g., during CLI unit tests).
 */
class RateLimitMiddleware
{
    private const DEFAULT_MAX    = 60;
    private const DEFAULT_WINDOW = 60; // seconds

    public static function check(): void
    {
        // APCu not available in CLI or when disabled — skip silently
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            return;
        }

        $max    = (int) (getenv('RATE_LIMIT_MAX')    ?: self::DEFAULT_MAX);
        $window = (int) (getenv('RATE_LIMIT_WINDOW') ?: self::DEFAULT_WINDOW);
        $ip     = self::resolveIp();
        $key    = 'rl:' . $ip;

        $count = apcu_fetch($key);

        if ($count === false) {
            apcu_store($key, 1, $window);
            return;
        }

        if ($count >= $max) {
            $ttl = apcu_key_info($key)['ttl'] ?? $window;
            header('Retry-After: ' . $ttl);
            header('X-RateLimit-Limit: ' . $max);
            header('X-RateLimit-Remaining: 0');
            Response::error('Too many requests', 429);
        }

        apcu_inc($key);
        header('X-RateLimit-Limit: ' . $max);
        header('X-RateLimit-Remaining: ' . ($max - $count - 1));
    }

    private static function resolveIp(): string
    {
        // Trust X-Forwarded-For only when behind a known proxy
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded && getenv('BEHIND_PROXY')) {
            return trim(explode(',', $forwarded)[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
