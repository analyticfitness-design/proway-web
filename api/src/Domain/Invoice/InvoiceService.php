<?php
declare(strict_types=1);

namespace ProWay\Domain\Invoice;

class InvoiceService
{
    private const VALID_STATUSES = ['pendiente', 'enviada', 'pagada', 'vencida'];

    public function __construct(private readonly InvoiceRepository $repo) {}

    public function listForClient(int $clientId): array
    {
        return $this->repo->findAllForClient($clientId);
    }

    public function getPendingForClient(int $clientId): array
    {
        return $this->repo->findPendingForClient($clientId);
    }

    public function markPaid(int $id, string $method, string $reference = ''): bool
    {
        return $this->repo->markPaid($id, $method, $reference);
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid invoice status: $status");
        }

        return $this->repo->updateStatus($id, $status);
    }
}
