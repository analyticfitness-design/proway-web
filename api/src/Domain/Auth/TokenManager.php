<?php
declare(strict_types=1);

namespace ProWay\Domain\Auth;

use PDO;

class TokenManager
{
    public function __construct(private readonly PDO $db) {}

    /** Generate a cryptographically secure token and store its hash */
    public function create(int $userId, string $type = 'client', int $expiryHours = 168): string
    {
        $token    = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $token);
        $expires  = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));

        $stmt = $this->db->prepare(
            'INSERT INTO auth_tokens (user_id, token, expires_at, user_type) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $hash, $expires, $type]);

        return $token;
    }

    /** Validate token. Returns [client_id, type] or null if invalid/expired */
    public function validate(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT user_id AS client_id, user_type AS type FROM auth_tokens
             WHERE token = ? AND expires_at > NOW()'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Revoke a specific token. PDO ERRMODE_EXCEPTION ensures failures are not swallowed silently. */
    public function revoke(string $token): void
    {
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare('DELETE FROM auth_tokens WHERE token = ?');
        $stmt->execute([$hash]);
    }
}
