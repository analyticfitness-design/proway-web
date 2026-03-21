<?php
declare(strict_types=1);

namespace ProWay\Domain\ContentCalendar;

use PDO;

class MySQLContentSlotRepository implements ContentSlotRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findForClientInRange(int $clientId, string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            'SELECT cs.*, c.name AS client_name
               FROM content_slots cs
               JOIN clients c ON c.id = cs.client_id
              WHERE cs.client_id = :client_id
                AND cs.scheduled_date >= :from_date
                AND cs.scheduled_date <= :to_date
              ORDER BY cs.scheduled_date ASC, cs.id ASC'
        );
        $stmt->execute([
            ':client_id' => $clientId,
            ':from_date' => $from,
            ':to_date'   => $to,
        ]);
        return $stmt->fetchAll();
    }

    public function findAllInRange(string $from, string $to, ?int $clientId = null): array
    {
        $sql = 'SELECT cs.*, c.name AS client_name
                  FROM content_slots cs
                  JOIN clients c ON c.id = cs.client_id
                 WHERE cs.scheduled_date >= :from_date
                   AND cs.scheduled_date <= :to_date';
        $params = [
            ':from_date' => $from,
            ':to_date'   => $to,
        ];

        if ($clientId !== null) {
            $sql .= ' AND cs.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }

        $sql .= ' ORDER BY cs.scheduled_date ASC, cs.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT cs.*, c.name AS client_name
               FROM content_slots cs
               JOIN clients c ON c.id = cs.client_id
              WHERE cs.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO content_slots
                (client_id, project_id, scheduled_date, content_type, title, description, status, platform)
             VALUES
                (:client_id, :project_id, :scheduled_date, :content_type, :title, :description, :status, :platform)'
        );
        $stmt->execute([
            ':client_id'      => $data['client_id'],
            ':project_id'     => $data['project_id'] ?? null,
            ':scheduled_date' => $data['scheduled_date'],
            ':content_type'   => $data['content_type'],
            ':title'          => $data['title'] ?? null,
            ':description'    => $data['description'] ?? null,
            ':status'         => $data['status'] ?? 'planned',
            ':platform'       => $data['platform'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['client_id', 'project_id', 'scheduled_date', 'content_type', 'title', 'description', 'status', 'platform'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE content_slots SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM content_slots WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
