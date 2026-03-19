<?php
declare(strict_types=1);

namespace ProWay\Domain\Invoice;

use PDO;

class MySQLInvoiceRepository implements InvoiceRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function findByClientAndId(int $clientId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $clientId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function findAllForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM invoices WHERE client_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function findPendingForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM invoices WHERE client_id = ? AND status IN ('pendiente','enviada') ORDER BY due_date ASC"
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function markPaid(int $id, string $method, string $reference): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE invoices SET status = 'pagada', paid_at = NOW(), payment_method = ?, payu_reference = ? WHERE id = ?"
        );
        $stmt->execute([$method, $reference, $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }
}
