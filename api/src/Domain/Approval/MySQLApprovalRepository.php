<?php
declare(strict_types=1);

namespace ProWay\Domain\Approval;

use PDO;

class MySQLApprovalRepository implements ApprovalRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findByDeliverable(int $deliverableId): array
    {
        $stmt = $this->db->prepare(
            'SELECT da.*, c.name AS client_name, c.email AS client_email
               FROM deliverable_approvals da
               JOIN clients c ON c.id = da.client_id
              WHERE da.deliverable_id = :deliverable_id
              ORDER BY da.created_at DESC'
        );
        $stmt->execute([':deliverable_id' => $deliverableId]);
        return $stmt->fetchAll();
    }

    public function findByProject(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT da.*, d.title AS deliverable_title, d.type AS deliverable_type,
                    c.name AS client_name, c.email AS client_email
               FROM deliverable_approvals da
               JOIN deliverables d ON d.id = da.deliverable_id
               JOIN clients c ON c.id = da.client_id
              WHERE d.project_id = :project_id
              ORDER BY da.created_at DESC'
        );
        $stmt->execute([':project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function findPendingAll(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT da.*,
                    d.title AS deliverable_title, d.type AS deliverable_type,
                    d.project_id,
                    p.title AS project_title, p.project_code,
                    c.name AS client_name, c.email AS client_email
               FROM deliverable_approvals da
               JOIN deliverables d ON d.id = da.deliverable_id
               JOIN projects p ON p.id = d.project_id
               JOIN clients c ON c.id = da.client_id
              WHERE da.status IN (\'pending\', \'changes_requested\')
              ORDER BY da.created_at DESC
              LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function upsert(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO deliverable_approvals
                (deliverable_id, client_id, status, comment, reviewed_at)
             VALUES
                (:deliverable_id, :client_id, :status, :comment, :reviewed_at)
             ON DUPLICATE KEY UPDATE
                status      = VALUES(status),
                comment     = VALUES(comment),
                reviewed_at = VALUES(reviewed_at)'
        );
        $stmt->execute([
            ':deliverable_id' => $data['deliverable_id'],
            ':client_id'      => $data['client_id'],
            ':status'         => $data['status'],
            ':comment'        => $data['comment'] ?? null,
            ':reviewed_at'    => $data['reviewed_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Verify that a client owns the project to which a deliverable belongs.
     */
    public function clientOwnsDeliverable(int $deliverableId, int $clientId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.id AS deliverable_id, d.project_id, p.client_id
               FROM deliverables d
               JOIN projects p ON p.id = d.project_id
              WHERE d.id = :deliverable_id
                AND p.client_id = :client_id'
        );
        $stmt->execute([
            ':deliverable_id' => $deliverableId,
            ':client_id'      => $clientId,
        ]);
        return $stmt->fetch() ?: null;
    }
}
