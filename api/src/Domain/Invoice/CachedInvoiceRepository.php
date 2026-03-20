<?php
declare(strict_types=1);

namespace ProWay\Domain\Invoice;

use ProWay\Infrastructure\Cache\CacheInterface;

class CachedInvoiceRepository implements InvoiceRepository
{
    public function __construct(
        private readonly InvoiceRepository $inner,
        private readonly CacheInterface $cache
    ) {}

    public function findById(int $id): ?array
    {
        return $this->inner->findById($id);
    }

    public function findByClientAndId(int $clientId, int $id): ?array
    {
        return $this->inner->findByClientAndId($clientId, $id);
    }

    public function findAllForClient(int $clientId): array
    {
        $key    = "pw:invoices:client:{$clientId}";
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->inner->findAllForClient($clientId);
        $this->cache->set($key, $data, 180);

        return $data;
    }

    public function findPendingForClient(int $clientId): array
    {
        $key    = "pw:invoices:pending:{$clientId}";
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->inner->findPendingForClient($clientId);
        $this->cache->set($key, $data, 180);

        return $data;
    }

    // Admin queries bypass cache — aggregate data must be fresh.

    public function findAll(): array
    {
        return $this->inner->findAll();
    }

    public function countPending(): int
    {
        return $this->inner->countPending();
    }

    public function sumPaidThisMonth(): float
    {
        return $this->inner->sumPaidThisMonth();
    }

    public function create(array $data): int
    {
        return $this->inner->create($data);
    }

    public function markPaid(int $id, string $method, string $reference): bool
    {
        return $this->inner->markPaid($id, $method, $reference);
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->inner->updateStatus($id, $status);
    }

    public function revenueByMonth(int $months = 6): array
    {
        return $this->inner->revenueByMonth($months);
    }
}
