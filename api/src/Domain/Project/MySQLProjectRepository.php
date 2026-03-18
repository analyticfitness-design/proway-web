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
}
