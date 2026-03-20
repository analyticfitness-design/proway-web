<?php
declare(strict_types=1);

namespace ProWay\Domain\Deliverable;

use PDO;

class MySQLDeliverableRepository implements DeliverableRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findByProject(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM deliverables WHERE project_id = ? ORDER BY delivered_at DESC, id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM deliverables WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO deliverables
                (project_id, type, title, file_url, preview_url, description, version, delivered_at)
             VALUES
                (:project_id, :type, :title, :file_url, :preview_url, :description, :version, :delivered_at)'
        );
        $stmt->execute([
            ':project_id'  => $data['project_id'],
            ':type'        => $data['type'],
            ':title'       => $data['title'],
            ':file_url'    => $data['file_url']    ?? null,
            ':preview_url' => $data['preview_url'] ?? null,
            ':description' => $data['description'] ?? null,
            ':version'     => $data['version']     ?? 1,
            ':delivered_at' => $data['delivered_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }
}
