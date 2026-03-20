<?php
declare(strict_types=1);

namespace ProWay\Domain\SocialMetrics;

use PDO;

class MySQLSocialProfileRepository implements SocialProfileRepository
{
    public function __construct(private readonly PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO social_profiles
                (client_id, platform, username, display_name, profile_pic_url, bio)
             VALUES
                (:client_id, :platform, :username, :display_name, :profile_pic_url, :bio)'
        );
        $stmt->execute([
            'client_id'       => $data['client_id'],
            'platform'        => $data['platform'],
            'username'        => $data['username'],
            'display_name'    => $data['display_name'] ?? null,
            'profile_pic_url' => $data['profile_pic_url'] ?? null,
            'bio'             => $data['bio'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByClientId(int $clientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM social_profiles
             WHERE client_id = ?
             ORDER BY platform, username'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM social_profiles WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findAllActive(): array
    {
        $stmt = $this->db->query(
            'SELECT sp.*, c.nombre AS client_name, c.code AS client_code
             FROM social_profiles sp
             LEFT JOIN clients c ON c.id = sp.client_id
             WHERE sp.is_active = 1
             ORDER BY sp.last_synced_at ASC'
        );
        return $stmt->fetchAll();
    }

    public function updateMetrics(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowed = ['followers', 'following', 'posts_count', 'display_name', 'profile_pic_url', 'bio'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        // Always update last_synced_at
        $fields[] = 'last_synced_at = NOW()';

        $sql = 'UPDATE social_profiles SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM social_profiles WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
