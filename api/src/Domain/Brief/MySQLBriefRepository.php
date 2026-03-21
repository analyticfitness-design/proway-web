<?php
declare(strict_types=1);

namespace ProWay\Domain\Brief;

use PDO;

class MySQLBriefRepository implements BriefRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findByProject(int $projectId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM content_briefs WHERE project_id = :project_id'
        );
        $stmt->execute([':project_id' => $projectId]);
        return $stmt->fetch() ?: null;
    }

    public function upsert(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO content_briefs
                (project_id, objective, target_audience, tone, key_messages,
                 references_urls, filming_date, location, talent_notes, special_reqs, status)
             VALUES
                (:project_id, :objective, :target_audience, :tone, :key_messages,
                 :references_urls, :filming_date, :location, :talent_notes, :special_reqs, :status)
             ON DUPLICATE KEY UPDATE
                objective       = VALUES(objective),
                target_audience = VALUES(target_audience),
                tone            = VALUES(tone),
                key_messages    = VALUES(key_messages),
                references_urls = VALUES(references_urls),
                filming_date    = VALUES(filming_date),
                location        = VALUES(location),
                talent_notes    = VALUES(talent_notes),
                special_reqs    = VALUES(special_reqs),
                status          = VALUES(status)'
        );

        $stmt->execute([
            ':project_id'       => $data['project_id'],
            ':objective'        => $data['objective'] ?? null,
            ':target_audience'  => $data['target_audience'] ?? null,
            ':tone'             => $data['tone'] ?? null,
            ':key_messages'     => $data['key_messages'] ?? null,
            ':references_urls'  => $data['references_urls'] ?? null,
            ':filming_date'     => $data['filming_date'] ?: null,
            ':location'         => $data['location'] ?? null,
            ':talent_notes'     => $data['talent_notes'] ?? null,
            ':special_reqs'     => $data['special_reqs'] ?? null,
            ':status'           => $data['status'] ?? 'draft',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function submit(int $projectId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE content_briefs
                SET status = \'submitted\', submitted_at = NOW()
              WHERE project_id = :project_id'
        );
        $stmt->execute([':project_id' => $projectId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Verify that a client owns the project.
     */
    public function clientOwnsProject(int $projectId, int $clientId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM projects WHERE id = :project_id AND client_id = :client_id'
        );
        $stmt->execute([
            ':project_id' => $projectId,
            ':client_id'  => $clientId,
        ]);
        return (bool) $stmt->fetch();
    }
}
