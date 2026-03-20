<?php
declare(strict_types=1);

namespace ProWay\Domain\SocialMetrics;

interface SocialPostRepository
{
    /** Insert or update a post (ON DUPLICATE KEY UPDATE). Returns the post ID. */
    public function upsert(array $data): int;

    /** Find posts for a given profile, newest first. */
    public function findByProfileId(int $profileId, int $limit = 20): array;

    /** Find posts marked as ProWay content for a profile. */
    public function findProWayContent(int $profileId): array;

    /** Mark a single post as ProWay-produced content. */
    public function markAsProWay(int $postId): bool;

    /** Toggle the is_proway flag on a post. */
    public function setProWayFlag(int $postId, bool $isProWay): bool;
}
