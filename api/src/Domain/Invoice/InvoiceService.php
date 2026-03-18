<?php
declare(strict_types=1);

namespace ProWay\Domain\Invoice;

use PDO;

class InvoiceService
{
    private const VALID_STATUSES = ['pendiente', 'enviada', 'pagada', 'vencida'];

    public function __construct(private readonly PDO $db) {}

    public function listForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM invoices WHERE client_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function getPendingForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM invoices WHERE client_id = ? AND status IN ('pendiente','enviada') ORDER BY due_date ASC"
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function markPaid(int $id, string $method, string $reference = ''): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE invoices SET status = 'pagada', paid_at = NOW(), payment_method = ?, payu_reference = ? WHERE id = ?"
        );
        $stmt->execute([$method, $reference, $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid invoice status: $status");
        }

        $stmt = $this->db->prepare(
            'UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }
}
