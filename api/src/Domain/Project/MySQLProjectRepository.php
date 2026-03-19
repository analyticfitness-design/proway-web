<?php
declare(strict_types=1);

namespace ProWay\Domain\Project;

use PDO;

class MySQLProjectRepository implements ProjectRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findAllForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT p.*, c.nombre AS client_name, c.code AS client_code
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id
             ORDER BY p.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function countActive(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM projects WHERE status IN ('produccion','revision','entrega')"
        );
        return (int) $stmt->fetchColumn();
    }
}
