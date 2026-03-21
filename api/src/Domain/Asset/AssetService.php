<?php
declare(strict_types=1);

namespace ProWay\Domain\Asset;

class AssetService
{
    public function __construct(private readonly AssetRepository $repo) {}

    /**
     * Search assets with pagination.
     *
     * @return array{items: array, total: int, page: int, per_page: int, pages: int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 24): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $items = $this->repo->search($filters, $page, $perPage);
        $total = $this->repo->countSearch($filters);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * List all tags with usage counts.
     */
    public function getTags(): array
    {
        return $this->repo->findAllTags();
    }

    /**
     * Attach tags to a deliverable. Creates any new tags by name.
     *
     * @param int[] $tagIds
     */
    public function tagDeliverable(int $deliverableId, array $tagIds): array
    {
        // Validate deliverable exists
        $asset = $this->repo->findById($deliverableId);
        if ($asset === null) {
            throw new \InvalidArgumentException('Deliverable not found.');
        }

        // Clear existing tags, then attach new set
        $existing = $this->repo->getTagsForDeliverable($deliverableId);
        $existingIds = array_column($existing, 'id');

        // Detach those no longer in the set
        $toDetach = array_diff($existingIds, $tagIds);
        if (!empty($toDetach)) {
            $this->repo->detachTags($deliverableId, array_values($toDetach));
        }

        // Attach new ones
        $toAttach = array_diff($tagIds, $existingIds);
        if (!empty($toAttach)) {
            $this->repo->attachTags($deliverableId, array_values($toAttach));
        }

        return $this->repo->getTagsForDeliverable($deliverableId);
    }

    /**
     * Create a tag by name, return the tag row.
     */
    public function createTag(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tag name cannot be empty.');
        }

        return $this->repo->createTag($name);
    }

    /**
     * Get a single asset with its tags.
     */
    public function getAsset(int $id): ?array
    {
        $asset = $this->repo->findById($id);
        if ($asset === null) {
            return null;
        }

        $asset['tags'] = $this->repo->getTagsForDeliverable($id);
        return $asset;
    }
}
