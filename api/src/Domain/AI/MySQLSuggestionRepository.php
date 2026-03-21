<?php
declare(strict_types=1);

namespace ProWay\Domain\AI;

use PDO;

class MySQLSuggestionRepository implements SuggestionRepository
{
    public function __construct(private readonly PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_suggestions (client_id, prompt_type, context_json, result_text, tokens_used, expires_at, created_at)
             VALUES (:client_id, :prompt_type, :context_json, :result_text, :tokens_used, :expires_at, NOW())'
        );

        $stmt->execute([
            'client_id'    => $data['client_id'],
            'prompt_type'  => $data['prompt_type'],
            'context_json' => $data['context_json'],
            'result_text'  => $data['result_text'],
            'tokens_used'  => $data['tokens_used'],
            'expires_at'   => $data['expires_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findLatestForClient(int $clientId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ai_suggestions
             WHERE client_id = :client_id
             ORDER BY created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('client_id', $clientId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_suggestions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findFresh(int $clientId, string $promptType): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ai_suggestions
             WHERE client_id  = :client_id
               AND prompt_type = :prompt_type
               AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([
            'client_id'   => $clientId,
            'prompt_type' => $promptType,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function countRecentByClient(int $clientId, int $windowMinutes = 60): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ai_suggestions
             WHERE client_id = :client_id
               AND created_at > DATE_SUB(NOW(), INTERVAL :mins MINUTE)'
        );
        $stmt->bindValue('client_id', $clientId, PDO::PARAM_INT);
        $stmt->bindValue('mins', $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
