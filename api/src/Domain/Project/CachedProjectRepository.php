<?php
declare(strict_types=1);

namespace ProWay\Domain\Project;

use ProWay\Infrastructure\Cache\CacheInterface;

class CachedProjectRepository implements ProjectRepository
{
    public function __construct(
        private readonly ProjectRepository $inner,
        private readonly CacheInterface $cache
    ) {}

    public function findAllForClient(int $clientId): array
    {
        $key    = "pw:projects:client:{$clientId}";
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->inner->findAllForClient($clientId);
        $this->cache->set($key, $data, 300);

        return $data;
    }

    public function findById(int $id): ?array
    {
        $key    = "pw:projects:{$id}";
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->inner->findById($id);

        if ($data !== null) {
            $this->cache->set($key, $data, 600);
        }

        return $data;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $result = $this->inner->updateStatus($id, $status);

        if ($result) {
            $this->cache->delete("pw:projects:{$id}");
            // The client list will refresh when its TTL of 300s expires.
        }

        return $result;
    }

    public function create(array $data): int
    {
        return $this->inner->create($data);
    }

    // Calendar queries bypass cache — always fresh data.

    public function findAllWithDates(): array
    {
        return $this->inner->findAllWithDates();
    }

    public function findAllWithDatesForClient(int $clientId): array
    {
        return $this->inner->findAllWithDatesForClient($clientId);
    }

    // Admin queries bypass cache — always fresh data.

    public function findAll(): array
    {
        return $this->inner->findAll();
    }

    public function countActive(): int
    {
        return $this->inner->countActive();
    }

    public function countByStatus(): array
    {
        return $this->inner->countByStatus();
    }
}
