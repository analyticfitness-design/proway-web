<?php
declare(strict_types=1);

namespace ProWay\Domain\Invoice;

interface InvoiceRepository
{
    public function findById(int $id): ?array;
    public function findAllForClient(int $clientId): array;
    public function findPendingForClient(int $clientId): array;
    public function findByClientAndId(int $clientId, int $id): ?array;
    public function markPaid(int $id, string $method, string $reference): bool;
    public function updateStatus(int $id, string $status): bool;
}
