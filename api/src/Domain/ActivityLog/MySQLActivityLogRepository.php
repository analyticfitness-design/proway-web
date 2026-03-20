<?php
declare(strict_types=1);

namespace ProWay\Domain\ActivityLog;

use PDO;

class MySQLActivityLogRepository implements ActivityLogRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findForProject(int $projectId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM activity_log
             WHERE project_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $projectId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO activity_log
                (project_id, user_type, user_id, action, description, metadata)
             VALUES
                (:project_id, :user_type, :user_id, :action, :description, :metadata)'
        );
        $stmt->execute([
            'project_id'  => $data['project_id'],
            'user_type'   => $data['user_type']  ?? 'system',
            'user_id'     => $data['user_id']    ?? null,
            'action'      => $data['action'],
            'description' => $data['description'],
            'metadata'    => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
