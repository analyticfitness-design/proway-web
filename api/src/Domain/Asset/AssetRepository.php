<?php
declare(strict_types=1);

namespace ProWay\Domain\Asset;

interface AssetRepository
{
    /**
     * Search deliverables with filters.
     *
     * @param array{client_id?: int, type?: string, tag_id?: int, q?: string} $filters
     */
    public function search(array $filters, int $page, int $perPage): array;

    /**
     * Count total results for the given filters.
     */
    public function countSearch(array $filters): int;

    /**
     * Return all tags ordered by name.
     */
    public function findAllTags(): array;

    /**
     * Attach tag IDs to a deliverable.
     *
     * @param int[] $tagIds
     */
    public function attachTags(int $deliverableId, array $tagIds): void;

    /**
     * Detach tag IDs from a deliverable.
     *
     * @param int[] $tagIds
     */
    public function detachTags(int $deliverableId, array $tagIds): void;

    /**
     * Find a single deliverable by ID with project/client info.
     */
    public function findById(int $id): ?array;

    /**
     * Get tags attached to a deliverable.
     */
    public function getTagsForDeliverable(int $deliverableId): array;

    /**
     * Create a new tag, returning its ID.
     */
    public function createTag(string $name): int;
}
