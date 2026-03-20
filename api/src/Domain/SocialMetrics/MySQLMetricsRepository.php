<?php
declare(strict_types=1);

namespace ProWay\Domain\SocialMetrics;

use PDO;

class MySQLMetricsRepository implements MetricsRepository
{
    public function __construct(private readonly PDO $db) {}

    public function recordDaily(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO social_metrics_daily
                (profile_id, post_id, date, followers, likes, comments, shares, views, reach, engagement_rate)
             VALUES
                (:profile_id, :post_id, :date, :followers, :likes, :comments, :shares, :views, :reach, :engagement_rate)
             ON DUPLICATE KEY UPDATE
                followers       = VALUES(followers),
                likes           = VALUES(likes),
                comments        = VALUES(comments),
                shares          = VALUES(shares),
                views           = VALUES(views),
                reach           = VALUES(reach),
                engagement_rate = VALUES(engagement_rate)'
        );
        $stmt->execute([
            'profile_id'      => $data['profile_id'],
            'post_id'         => $data['post_id'] ?? null,
            'date'            => $data['date'] ?? date('Y-m-d'),
            'followers'       => $data['followers'] ?? null,
            'likes'           => $data['likes'] ?? null,
            'comments'        => $data['comments'] ?? null,
            'shares'          => $data['shares'] ?? null,
            'views'           => $data['views'] ?? null,
            'reach'           => $data['reach'] ?? null,
            'engagement_rate' => $data['engagement_rate'] ?? null,
        ]);

        $insertId = (int) $this->db->lastInsertId();
        return $insertId > 0 ? $insertId : 0;
    }

    public function getProfileTimeline(int $profileId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT date,
                    followers,
                    SUM(likes)    AS likes,
                    SUM(comments) AS comments,
                    SUM(shares)   AS shares,
                    SUM(views)    AS views,
                    SUM(reach)    AS reach,
                    AVG(engagement_rate) AS engagement_rate
             FROM social_metrics_daily
             WHERE profile_id = ? AND date BETWEEN ? AND ?
             GROUP BY date
             ORDER BY date ASC'
        );
        $stmt->execute([$profileId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }

    public function getPostMetrics(int $postId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM social_metrics_daily
             WHERE post_id = ?
             ORDER BY date ASC'
        );
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }

    public function getGrowthSummary(int $profileId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate   = date('Y-m-d');

        // Get the first and last profile-level snapshots (post_id IS NULL)
        $stmtFirst = $this->db->prepare(
            'SELECT followers, date
             FROM social_metrics_daily
             WHERE profile_id = ? AND post_id IS NULL AND date >= ?
             ORDER BY date ASC
             LIMIT 1'
        );
        $stmtFirst->execute([$profileId, $startDate]);
        $first = $stmtFirst->fetch() ?: null;

        $stmtLast = $this->db->prepare(
            'SELECT followers, date
             FROM social_metrics_daily
             WHERE profile_id = ? AND post_id IS NULL AND date <= ?
             ORDER BY date DESC
             LIMIT 1'
        );
        $stmtLast->execute([$profileId, $endDate]);
        $last = $stmtLast->fetch() ?: null;

        // Totals for the period
        $stmtTotals = $this->db->prepare(
            'SELECT SUM(likes)    AS total_likes,
                    SUM(comments) AS total_comments,
                    SUM(shares)   AS total_shares,
                    SUM(views)    AS total_views,
                    SUM(reach)    AS total_reach,
                    AVG(engagement_rate) AS avg_engagement
             FROM social_metrics_daily
             WHERE profile_id = ? AND date BETWEEN ? AND ?'
        );
        $stmtTotals->execute([$profileId, $startDate, $endDate]);
        $totals = $stmtTotals->fetch() ?: [];

        $followersStart = $first ? (int) $first['followers'] : 0;
        $followersEnd   = $last  ? (int) $last['followers']  : 0;
        $followerGrowth = $followersEnd - $followersStart;
        $growthPct      = $followersStart > 0
            ? round(($followerGrowth / $followersStart) * 100, 2)
            : 0;

        return [
            'period_days'      => $days,
            'start_date'       => $startDate,
            'end_date'         => $endDate,
            'followers_start'  => $followersStart,
            'followers_end'    => $followersEnd,
            'follower_growth'  => $followerGrowth,
            'growth_pct'       => $growthPct,
            'total_likes'      => (int) ($totals['total_likes'] ?? 0),
            'total_comments'   => (int) ($totals['total_comments'] ?? 0),
            'total_shares'     => (int) ($totals['total_shares'] ?? 0),
            'total_views'      => (int) ($totals['total_views'] ?? 0),
            'total_reach'      => (int) ($totals['total_reach'] ?? 0),
            'avg_engagement'   => round((float) ($totals['avg_engagement'] ?? 0), 2),
        ];
    }
}
