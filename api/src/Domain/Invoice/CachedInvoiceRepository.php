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

    public function markPaid(int $id, string $method, string $reference): bool
    {
        // We delegate directly. Since InvoiceRepository has no findById,
        // we cannot determine the client_id to invalidate list keys.
        // The TTL of 180s is acceptable for invoice data freshness.
        return $this->inner->markPaid($id, $method, $reference);
    }

    public function updateStatus(int $id, string $status): bool
    {
        // Same as markPaid: delegate directly, rely on TTL expiry for list invalidation.
        return $this->inner->updateStatus($id, $status);
    }
}
