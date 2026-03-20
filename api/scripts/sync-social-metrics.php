<?php
declare(strict_types=1);

/**
 * Cron: Sync social media metrics for all active profiles.
 *
 * Usage:  php /code/api/scripts/sync-social-metrics.php
 * Cron:   0 6 * * * php /code/api/scripts/sync-social-metrics.php >> /var/log/social-sync.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ProWay\Domain\SocialMetrics\MySQLSocialProfileRepository;
use ProWay\Domain\SocialMetrics\MySQLSocialPostRepository;
use ProWay\Domain\SocialMetrics\MySQLMetricsRepository;
use ProWay\Domain\SocialMetrics\SocialMetricsService;
use ProWay\Infrastructure\Database\Connection;
use ProWay\Infrastructure\SocialApi\SocialApiClient;

// ── Bootstrap ────────────────────────────────────────────────────────────────
$pdo = Connection::getInstance();

$profileRepo = new MySQLSocialProfileRepository($pdo);
$postRepo    = new MySQLSocialPostRepository($pdo);
$metricsRepo = new MySQLMetricsRepository($pdo);
$service     = new SocialMetricsService($profileRepo, $postRepo, $metricsRepo);
$apiClient   = new SocialApiClient();

// ── Sync ─────────────────────────────────────────────────────────────────────
$profiles = $service->getAllActiveProfiles();
$total    = count($profiles);
$success  = 0;
$errors   = 0;

echo sprintf("[%s] Starting social metrics sync — %d active profiles\n", date('Y-m-d H:i:s'), $total);

foreach ($profiles as $profile) {
    $id       = (int) $profile['id'];
    $platform = $profile['platform'];
    $username = $profile['username'];

    echo sprintf("  Syncing %s @%s (id=%d) ... ", $platform, $username, $id);

    try {
        // Fetch profile data from API
        $profileData = $apiClient->fetchProfile($platform, $username);

        if ($profileData === null) {
            echo "SKIP (API returned null)\n";
            $errors++;
            continue;
        }

        // Fetch recent posts
        $posts = $apiClient->fetchRecentPosts($platform, $username, 12);

        // Record everything
        $service->recordDailySnapshot($id, $profileData, $posts);

        $success++;
        echo sprintf("OK (followers=%d, posts=%d)\n", $profileData['followers'] ?? 0, count($posts));

    } catch (\Throwable $e) {
        $errors++;
        echo sprintf("ERROR: %s\n", $e->getMessage());
        error_log(sprintf(
            '[social-sync] Failed for profile %d (%s @%s): %s',
            $id, $platform, $username, $e->getMessage()
        ));
    }
}

echo sprintf(
    "[%s] Sync complete — %d/%d success, %d errors\n",
    date('Y-m-d H:i:s'),
    $success,
    $total,
    $errors
);

exit($errors > 0 ? 1 : 0);
