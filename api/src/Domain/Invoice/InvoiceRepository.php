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

    // ── Admin-scope queries ────────────────────────────────────────────────────
    /** Return every invoice across all clients, newest first. */
    public function findAll(): array;
    /** Count invoices in payable states (pendiente + enviada). */
    public function countPending(): int;
    /** Sum of total_cop for invoices paid in the current calendar month. */
    public function sumPaidThisMonth(): float;
    /** Insert a new invoice and return its auto-increment id. */
    public function create(array $data): int;
    /** SUM total_cop grouped by month for paid invoices (last N months). */
    public function revenueByMonth(int $months = 6): array;
}
