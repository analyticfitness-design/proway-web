<?php
declare(strict_types=1);

namespace ProWay\Domain\SocialMetrics;

use PDO;

class MySQLSocialPostRepository implements SocialPostRepository
{
    public function __construct(private readonly PDO $db) {}

    public function upsert(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO social_posts
                (profile_id, external_id, post_type, caption, thumbnail_url, permalink, is_proway, posted_at)
             VALUES
                (:profile_id, :external_id, :post_type, :caption, :thumbnail_url, :permalink, :is_proway, :posted_at)
             ON DUPLICATE KEY UPDATE
                caption       = VALUES(caption),
                thumbnail_url = VALUES(thumbnail_url),
                permalink     = VALUES(permalink),
                post_type     = VALUES(post_type)'
        );
        $stmt->execute([
            'profile_id'    => $data['profile_id'],
            'external_id'   => $data['external_id'],
            'post_type'     => $data['post_type'] ?? 'post',
            'caption'       => $data['caption'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'permalink'     => $data['permalink'] ?? null,
            'is_proway'     => $data['is_proway'] ?? 0,
            'posted_at'     => $data['posted_at'] ?? null,
        ]);

        // If INSERT happened, lastInsertId returns the new ID.
        // If UPDATE happened, lastInsertId returns 0 — fetch by external_id.
        $insertId = (int) $this->db->lastInsertId();
        if ($insertId > 0) {
            return $insertId;
        }

        $lookup = $this->db->prepare(
            'SELECT id FROM social_posts WHERE profile_id = ? AND external_id = ?'
        );
        $lookup->execute([$data['profile_id'], $data['external_id']]);
        return (int) $lookup->fetchColumn();
    }

    public function findByProfileId(int $profileId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT sp.*,
                    (SELECT SUM(smd.likes)    FROM social_metrics_daily smd WHERE smd.post_id = sp.id) AS total_likes,
                    (SELECT SUM(smd.comments) FROM social_metrics_daily smd WHERE smd.post_id = sp.id) AS total_comments,
                    (SELECT SUM(smd.views)    FROM social_metrics_daily smd WHERE smd.post_id = sp.id) AS total_views,
                    (SELECT SUM(smd.shares)   FROM social_metrics_daily smd WHERE smd.post_id = sp.id) AS total_shares
             FROM social_posts sp
             WHERE sp.profile_id = ?
             ORDER BY sp.posted_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $profileId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findProWayContent(int $profileId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sp.*,
                    (SELECT SUM(smd.likes)    FROM social_metrics_daily smd WHERE smd.post_id = sp.id) AS total_likes,
                    (SELECT SUM(smd.comments) FROM social_metrics_daily smd WHERE smd.post_id = sp.id) AS total_comments,
                    (SELECT SUM(smd.views)    FROM social_metrics_daily smd WHERE smd.post_id = sp.id) AS total_views,
                    (SELECT SUM(smd.shares)   FROM social_metrics_daily smd WHERE smd.post_id = sp.id) AS total_shares
             FROM social_posts sp
             WHERE sp.profile_id = ? AND sp.is_proway = 1
             ORDER BY sp.posted_at DESC'
        );
        $stmt->execute([$profileId]);
        return $stmt->fetchAll();
    }

    public function markAsProWay(int $postId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE social_posts SET is_proway = 1 WHERE id = ?'
        );
        $stmt->execute([$postId]);
        return $stmt->rowCount() > 0;
    }

    public function setProWayFlag(int $postId, bool $isProWay): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE social_posts SET is_proway = ? WHERE id = ?'
        );
        $stmt->execute([$isProWay ? 1 : 0, $postId]);
        return $stmt->rowCount() > 0;
    }
}
