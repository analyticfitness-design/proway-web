<?php
declare(strict_types=1);

namespace ProWay\Domain\Auth;

use PDO;

class AuthService
{
    public function __construct(
        private readonly PDO          $db,
        private readonly TokenManager $tokens,
    ) {}

    /**
     * Authenticate a client by email + password.
     * Returns [token, user] on success, null on failure.
     */
    public function loginClient(string $email, string $password): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT cp.password_hash, c.id, c.name, c.email, c.plan_type, c.code
             FROM client_profiles cp
             JOIN clients c ON c.id = cp.client_id
             WHERE c.email = ? AND c.status = ?'
        );
        $stmt->execute([$email, 'activo']);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        $user  = UserDTO::fromArray($row, 'client');
        $token = $this->tokens->create($user->id, 'client');

        return ['token' => $token, 'user' => $user];
    }

    /**
     * Authenticate an admin by username + password.
     */
    public function loginAdmin(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash FROM admin_users WHERE username = ?'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        $user  = UserDTO::fromArray(array_merge($row, ['code' => '', 'plan_type' => '']), 'admin');
        $token = $this->tokens->create($user->id, 'admin', 8);

        return ['token' => $token, 'user' => $user];
    }

    /**
     * Get the current authenticated user from a token.
     */
    public function getCurrentUser(string $token): ?UserDTO
    {
        $data = $this->tokens->validate($token);
        if (!$data) {
            return null;
        }

        $userId = (int) $data['client_id'];
        $type   = $data['type'];

        if ($type === 'admin') {
            $stmt = $this->db->prepare('SELECT id, name, email FROM admin_users WHERE id = ?');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if (!$row) return null;
            return UserDTO::fromArray(array_merge($row, ['code' => '', 'plan_type' => '']), 'admin');
        }

        $stmt = $this->db->prepare(
            'SELECT c.id, c.name, c.email, c.plan_type, c.code
             FROM clients c WHERE c.id = ? AND c.status = ?'
        );
        $stmt->execute([$userId, 'activo']);
        $row = $stmt->fetch();

        return $row ? UserDTO::fromArray($row, 'client') : null;
    }

    /**
     * Revoke the token (logout).
     */
    public function logout(string $token): void
    {
        $this->tokens->revoke($token);
    }
}
