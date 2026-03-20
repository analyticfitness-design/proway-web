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

    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT i.*, c.nombre AS client_name, c.email AS client_email
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             ORDER BY i.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function countPending(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM invoices WHERE status IN ('pendiente', 'enviada')"
        );
        return (int) $stmt->fetchColumn();
    }

    public function sumPaidThisMonth(): float
    {
        $stmt = $this->db->query(
            "SELECT COALESCE(SUM(total_cop), 0)
             FROM invoices
             WHERE status = 'pagada'
               AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
               AND paid_at <  DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')"
        );
        return (float) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO invoices
                (client_id, project_id, invoice_number, amount_cop, tax_cop, total_cop, status, due_date, notes)
             VALUES
                (:client_id, :project_id, :invoice_number, :amount_cop, :tax_cop, :total_cop, :status, :due_date, :notes)'
        );
        $stmt->execute([
            ':client_id'      => $data['client_id'],
            ':project_id'     => $data['project_id'] ?? null,
            ':invoice_number' => $data['invoice_number'],
            ':amount_cop'     => $data['amount_cop'],
            ':tax_cop'        => $data['tax_cop'] ?? 0,
            ':total_cop'      => $data['total_cop'],
            ':status'         => $data['status'] ?? 'enviada',
            ':due_date'       => $data['due_date'] ?? null,
            ':notes'          => $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
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

    public function revenueByMonth(int $months = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month,
                    COALESCE(SUM(total_cop), 0) AS total
             FROM invoices
             WHERE status = 'pagada'
               AND paid_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC"
        );
        $stmt->execute([$months - 1]);
        return $stmt->fetchAll();
    }
}
