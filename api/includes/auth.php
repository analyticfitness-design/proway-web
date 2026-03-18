<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

/**
 * Generate a cryptographically secure random token.
 */
function generateToken(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Insert a new auth token in DB and return it.
 *
 * @param string $type   'admin' | 'client'
 * @param int    $userId
 */
function createToken(string $type, int $userId): string {
    $token   = generateToken();
    $hours   = ($type === 'admin') ? TOKEN_EXPIRY_ADMIN : TOKEN_EXPIRY_CLIENT;
    $expires = date('Y-m-d H:i:s', time() + $hours * 3600);

    $db  = getDB();
    $sql = 'INSERT INTO auth_tokens (token, user_type, user_id, expires_at) VALUES (?, ?, ?, ?)';
    $db->prepare($sql)->execute([$token, $type, $userId, $expires]);

    return $token;
}

/**
 * Revoke a token (delete from DB).
 */
function revokeToken(string $token): void {
    $db = getDB();
    $db->prepare('DELETE FROM auth_tokens WHERE token = ?')->execute([$token]);
}

/**
 * Extract token: cookie first (browser clients), then Authorization header (API/mobile clients).
 */
function getBearerToken(): ?string {
    // Try cookie first (browser clients) — httpOnly so JS cannot read or steal it
    $token = $_COOKIE['pw_access'] ?? null;
    if ($token) {
        return $token;
    }

    // Fall back to Authorization header (API clients, mobile apps)
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Verify the token, return the token row or null if invalid/expired.
 */
function verifyToken(?string $token): ?array {
    if (!$token) return null;

    $db  = getDB();
    $sql = 'SELECT * FROM auth_tokens WHERE token = ? AND expires_at > NOW()';
    $row = $db->prepare($sql);
    $row->execute([$token]);
    return $row->fetch() ?: null;
}

/**
 * Authenticate as admin. Returns admin data array.
 * Calls respondError(401) on failure.
 */
function authenticateAdmin(): array {
    $tokenRow = verifyToken(getBearerToken());
    if (!$tokenRow || $tokenRow['user_type'] !== 'admin') {
        respondError('Unauthorized', 401);
    }

    $db  = getDB();
    $sql = 'SELECT id, username, name, role, created_at FROM admins WHERE id = ?';
    $row = $db->prepare($sql);
    $row->execute([$tokenRow['user_id']]);
    $admin = $row->fetch();

    if (!$admin) {
        respondError('Unauthorized', 401);
    }

    return $admin;
}

/**
 * Authenticate as client. Returns client data array (with plan info).
 * Calls respondError(401) on failure.
 */
function authenticateClient(): array {
    $tokenRow = verifyToken(getBearerToken());
    if (!$tokenRow || $tokenRow['user_type'] !== 'client') {
        respondError('Unauthorized', 401);
    }

    $db  = getDB();
    $sql = 'SELECT c.id, c.code, c.name, c.email, c.phone, c.company, c.instagram,
                   c.plan_type, c.status, c.notes, c.created_at
            FROM clients c
            WHERE c.id = ?';
    $row = $db->prepare($sql);
    $row->execute([$tokenRow['user_id']]);
    $client = $row->fetch();

    if (!$client) {
        respondError('Unauthorized', 401);
    }

    return $client;
}

/**
 * Require authentication of a given type ('admin', 'client', or 'any').
 * Returns the authenticated user data.
 */
function requireAuth(string $type = 'any'): array {
    if ($type === 'admin') {
        return authenticateAdmin();
    }
    if ($type === 'client') {
        return authenticateClient();
    }

    // 'any' — try admin first, then client
    $tokenRow = verifyToken(getBearerToken());
    if (!$tokenRow) {
        respondError('Unauthorized', 401);
    }

    if ($tokenRow['user_type'] === 'admin') {
        return authenticateAdmin();
    }
    return authenticateClient();
}
