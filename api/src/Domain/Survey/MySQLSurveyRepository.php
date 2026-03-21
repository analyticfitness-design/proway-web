<?php
declare(strict_types=1);

namespace ProWay\Domain\Survey;

use PDO;

class MySQLSurveyRepository implements SurveyRepository
{
    public function __construct(private readonly PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO surveys
                (client_id, project_id, deliverable_id, type, status, sent_at)
             VALUES
                (:client_id, :project_id, :deliverable_id, :type, :status, :sent_at)'
        );
        $stmt->execute([
            ':client_id'      => $data['client_id'],
            ':project_id'     => $data['project_id'],
            ':deliverable_id' => $data['deliverable_id'] ?? null,
            ':type'           => $data['type'] ?? 'nps',
            ':status'         => $data['status'] ?? 'pending',
            ':sent_at'        => $data['sent_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findPendingForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, p.title AS project_title
               FROM surveys s
               JOIN projects p ON p.id = s.project_id
              WHERE s.client_id = :client_id
                AND s.status IN (\'pending\', \'sent\')
              ORDER BY s.created_at DESC
              LIMIT 1'
        );
        $stmt->execute([':client_id' => $clientId]);
        return $stmt->fetchAll();
    }

    public function respond(int $id, int $score, ?string $comment): void
    {
        $stmt = $this->db->prepare(
            'UPDATE surveys
                SET score        = :score,
                    comment      = :comment,
                    responded_at = :responded_at,
                    status       = \'responded\'
              WHERE id = :id
                AND status IN (\'pending\', \'sent\')'
        );
        $stmt->execute([
            ':id'           => $id,
            ':score'        => $score,
            ':comment'      => $comment,
            ':responded_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function averageNPS(?int $projectId = null): float
    {
        if ($projectId !== null) {
            $stmt = $this->db->prepare(
                'SELECT AVG(score) AS avg_score
                   FROM surveys
                  WHERE status = \'responded\'
                    AND type = \'nps\'
                    AND project_id = :project_id'
            );
            $stmt->execute([':project_id' => $projectId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT AVG(score) AS avg_score
                   FROM surveys
                  WHERE status = \'responded\'
                    AND type = \'nps\''
            );
            $stmt->execute();
        }

        $row = $stmt->fetch();
        return round((float) ($row['avg_score'] ?? 0), 2);
    }

    public function listRecent(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*,
                    c.name  AS client_name,
                    c.email AS client_email,
                    p.title AS project_title,
                    p.project_code
               FROM surveys s
               JOIN clients  c ON c.id = s.client_id
               JOIN projects p ON p.id = s.project_id
              WHERE s.status = \'responded\'
              ORDER BY s.responded_at DESC
              LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a specific survey by ID (used internally for ownership checks).
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM surveys WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
