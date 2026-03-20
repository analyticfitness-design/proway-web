<?php
declare(strict_types=1);

namespace ProWay\Domain\SocialMetrics;

class SocialMetricsService
{
    public function __construct(
        private readonly SocialProfileRepository $profiles,
        private readonly SocialPostRepository    $posts,
        private readonly MetricsRepository       $metrics,
    ) {}

    // ── Profile management ──────────────────────────────────────────────────────

    /**
     * Add a social profile for a client. Returns the new profile ID.
     */
    public function addProfile(int $clientId, string $platform, string $username): int
    {
        $platform = strtolower(trim($platform));
        $username = ltrim(trim($username), '@');

        if (!in_array($platform, ['instagram', 'tiktok'], true)) {
            throw new \InvalidArgumentException("Invalid platform: {$platform}");
        }

        if ($username === '') {
            throw new \InvalidArgumentException('username is required');
        }

        return $this->profiles->create([
            'client_id' => $clientId,
            'platform'  => $platform,
            'username'  => $username,
        ]);
    }

    /**
     * Get all social profiles for a client.
     */
    public function getClientProfiles(int $clientId): array
    {
        return $this->profiles->findByClientId($clientId);
    }

    /**
     * Full dashboard payload for a single profile.
     * Returns profile info + recent posts + timeline metrics + growth summary.
     */
    public function getProfileDashboard(int $profileId, int $days = 30): array
    {
        $profile = $this->profiles->findById($profileId);
        if ($profile === null) {
            return [];
        }

        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate   = date('Y-m-d');

        return [
            'profile'   => $profile,
            'posts'     => $this->posts->findByProfileId($profileId, 12),
            'timeline'  => $this->metrics->getProfileTimeline($profileId, $startDate, $endDate),
            'growth'    => $this->metrics->getGrowthSummary($profileId, $days),
        ];
    }

    // ── Sync operations (used by cron) ──────────────────────────────────────────

    /**
     * Record a daily snapshot for a profile: updates profile metrics,
     * upserts posts, and records daily metrics for each post.
     */
    public function recordDailySnapshot(int $profileId, array $profileData, array $posts): void
    {
        // Update profile-level metrics
        $this->profiles->updateMetrics($profileId, $profileData);

        // Record profile-level daily metrics (post_id = null)
        $this->metrics->recordDaily([
            'profile_id'      => $profileId,
            'post_id'         => null,
            'date'            => date('Y-m-d'),
            'followers'       => $profileData['followers'] ?? null,
            'likes'           => null,
            'comments'        => null,
            'shares'          => null,
            'views'           => null,
            'reach'           => $profileData['reach'] ?? null,
            'engagement_rate' => $profileData['engagement_rate'] ?? null,
        ]);

        // Upsert each post and record its daily metrics
        foreach ($posts as $post) {
            $post['profile_id'] = $profileId;
            $postId = $this->posts->upsert($post);

            $this->metrics->recordDaily([
                'profile_id'      => $profileId,
                'post_id'         => $postId,
                'date'            => date('Y-m-d'),
                'followers'       => null,
                'likes'           => $post['likes'] ?? null,
                'comments'        => $post['comments'] ?? null,
                'shares'          => $post['shares'] ?? null,
                'views'           => $post['views'] ?? null,
                'reach'           => $post['reach'] ?? null,
                'engagement_rate' => $post['engagement_rate'] ?? null,
            ]);
        }
    }

    // ── ProWay vs Organic comparison ────────────────────────────────────────────

    /**
     * Compare metrics for ProWay-produced content vs organic content.
     */
    public function getProWayComparison(int $profileId): array
    {
        $proWayPosts   = $this->posts->findProWayContent($profileId);
        $allPosts      = $this->posts->findByProfileId($profileId, 100);

        // Separate organic posts (non-ProWay)
        $organicPosts = array_filter($allPosts, fn(array $p) => !$p['is_proway']);

        $proWayAvg  = $this->calculatePostAverages($proWayPosts);
        $organicAvg = $this->calculatePostAverages($organicPosts);

        return [
            'proway'  => [
                'count'    => count($proWayPosts),
                'averages' => $proWayAvg,
            ],
            'organic' => [
                'count'    => count($organicPosts),
                'averages' => $organicAvg,
            ],
        ];
    }

    // ── Utilities ───────────────────────────────────────────────────────────────

    public function removeProfile(int $id): bool
    {
        return $this->profiles->delete($id);
    }

    public function getAllActiveProfiles(): array
    {
        return $this->profiles->findAllActive();
    }

    public function toggleProWay(int $postId, bool $isProWay): bool
    {
        return $this->posts->setProWayFlag($postId, $isProWay);
    }

    /**
     * Calculate average metrics across a set of posts.
     */
    private function calculatePostAverages(array $posts): array
    {
        $count = count($posts);
        if ($count === 0) {
            return [
                'avg_likes'    => 0,
                'avg_comments' => 0,
                'avg_views'    => 0,
                'avg_shares'   => 0,
            ];
        }

        $totalLikes    = array_sum(array_column($posts, 'total_likes'));
        $totalComments = array_sum(array_column($posts, 'total_comments'));
        $totalViews    = array_sum(array_column($posts, 'total_views'));
        $totalShares   = array_sum(array_column($posts, 'total_shares'));

        return [
            'avg_likes'    => round($totalLikes / $count),
            'avg_comments' => round($totalComments / $count),
            'avg_views'    => round($totalViews / $count),
            'avg_shares'   => round($totalShares / $count),
        ];
    }
}
