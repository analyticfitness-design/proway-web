<?php
declare(strict_types=1);

/**
 * File-based rate limiter. No external dependencies.
 * Stores request counts per IP in /tmp/rate_limits/
 *
 * @param string $endpoint  Identifier for the endpoint
 * @param int    $maxHits   Max requests allowed in the window
 * @param int    $windowSec Time window in seconds
 * @return bool  true if request is allowed, false if rate limited
 */
function checkRateLimit(string $endpoint, int $maxHits = 5, int $windowSec = 3600): bool {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
    // Take first IP if X-Forwarded-For has multiple
    $ip = explode(',', $ip)[0];
    $ip = trim($ip);

    $dir = sys_get_temp_dir() . '/rate_limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $file = $dir . '/' . md5($endpoint . ':' . $ip) . '.json';

    $data = ['hits' => [], 'blocked_until' => 0];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw) {
            $data = json_decode($raw, true) ?: $data;
        }
    }

    $now = time();

    // If currently blocked
    if ($data['blocked_until'] > $now) {
        return false;
    }

    // Remove expired hits
    $data['hits'] = array_values(array_filter(
        $data['hits'],
        fn(int $ts) => ($now - $ts) < $windowSec
    ));

    if (count($data['hits']) >= $maxHits) {
        // Block for remainder of window
        $data['blocked_until'] = $now + $windowSec;
        @file_put_contents($file, json_encode($data));
        return false;
    }

    $data['hits'][] = $now;
    @file_put_contents($file, json_encode($data));
    return true;
}
