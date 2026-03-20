<?php
declare(strict_types=1);

namespace ProWay\Domain\SocialMetrics;

interface SocialProfileRepository
{
    /** Create a new social profile and return its ID. */
    public function create(array $data): int;

    /** Find all social profiles for a given client. */
    public function findByClientId(int $clientId): array;

    /** Find a single profile by ID. */
    public function findById(int $id): ?array;

    /** Find all active profiles (for cron sync). */
    public function findAllActive(): array;

    /** Update profile metrics (followers, following, posts_count, etc.). */
    public function updateMetrics(int $id, array $data): bool;

    /** Delete a profile by ID. */
    public function delete(int $id): bool;
}
