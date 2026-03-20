<?php
declare(strict_types=1);

namespace ProWay\Domain\SocialMetrics;

interface MetricsRepository
{
    /** Record a daily metrics snapshot. Returns the row ID. */
    public function recordDaily(array $data): int;

    /** Get daily metrics for a profile within a date range. */
    public function getProfileTimeline(int $profileId, string $startDate, string $endDate): array;

    /** Get all daily metrics for a specific post. */
    public function getPostMetrics(int $postId): array;

    /** Get growth summary (first vs last snapshot in range). */
    public function getGrowthSummary(int $profileId, int $days = 30): array;
}
