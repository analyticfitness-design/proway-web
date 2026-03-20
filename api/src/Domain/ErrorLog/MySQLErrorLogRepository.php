<?php
declare(strict_types=1);

namespace ProWay\Domain\ErrorLog;

use PDO;

class MySQLErrorLogRepository implements ErrorLogRepository
{
    public function __construct(private readonly PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO error_logs
                (level, message, stack, url, user_agent, user_id, user_type, context)
             VALUES
                (:level, :message, :stack, :url, :user_agent, :user_id, :user_type, :context)'
        );
        $stmt->execute([
            'level'      => $data['level']      ?? 'error',
            'message'    => $data['message']     ?? null,
            'stack'      => $data['stack']       ?? null,
            'url'        => $data['url']         ?? null,
            'user_agent' => $data['user_agent']  ?? null,
            'user_id'    => $data['user_id']     ?? null,
            'user_type'  => $data['user_type']   ?? null,
            'context'    => isset($data['context']) ? json_encode($data['context']) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findRecent(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM error_logs ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countByLevel(): array
    {
        $stmt = $this->db->query(
            'SELECT level, COUNT(*) AS total FROM error_logs GROUP BY level ORDER BY total DESC'
        );
        return $stmt->fetchAll();
    }
}
