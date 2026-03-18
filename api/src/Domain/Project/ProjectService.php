<?php
declare(strict_types=1);

namespace ProWay\Domain\Project;

use PDO;

class ProjectService
{
    private const VALID_STATUSES = ['pendiente', 'en_progreso', 'revision', 'completado'];

    public function __construct(private readonly PDO $db) {}

    public function listForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid project status: $status");
        }

        $stmt = $this->db->prepare(
            'UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }
}
