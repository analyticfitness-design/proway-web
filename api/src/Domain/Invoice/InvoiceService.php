<?php
declare(strict_types=1);

namespace ProWay\Domain\Invoice;

class InvoiceService
{
    private const VALID_STATUSES = ['borrador', 'pendiente', 'enviada', 'pagada', 'vencida', 'cancelada'];

    public function __construct(private readonly InvoiceRepository $repo) {}

    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function getForClient(int $clientId, int $id): ?array
    {
        return $this->repo->findByClientAndId($clientId, $id);
    }

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

    // ── Admin-scope methods ────────────────────────────────────────────────────

    public function listAll(): array
    {
        return $this->repo->findAll();
    }

    public function countPending(): int
    {
        return $this->repo->countPending();
    }

    public function sumPaidThisMonth(): float
    {
        return $this->repo->sumPaidThisMonth();
    }

    /**
     * Create a new invoice. Generates invoice_number automatically if not supplied.
     * Returns the new record's id.
     */
    public function create(array $data): int
    {
        $amountCop = (float) ($data['amount_cop'] ?? 0);
        $taxCop    = (float) ($data['tax_cop']    ?? 0);
        $totalCop  = $amountCop + $taxCop;

        $data['amount_cop']     = $amountCop;
        $data['tax_cop']        = $taxCop;
        $data['total_cop']      = $totalCop;
        $data['invoice_number'] = $data['invoice_number'] ?? $this->generateNumber();
        $data['status']         = $data['status'] ?? 'enviada';

        return $this->repo->create($data);
    }

    public function revenueByMonth(int $months = 6): array
    {
        return $this->repo->revenueByMonth($months);
    }

    private function generateNumber(): string
    {
        return 'INV-' . date('Y') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
}
