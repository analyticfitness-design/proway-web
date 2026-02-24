<?php
/**
 * Migration: password_resets table
 * Run: https://prowaylab.com/api/setup/migrate-password-resets.php?secret=PROWAY_SETUP_2026
 */

if (!isset($_GET['secret']) || $_GET['secret'] !== 'PROWAY_SETUP_2026') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    $db->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            token VARCHAR(128) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            used TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo json_encode([
        'success' => true,
        'message' => 'Table password_resets created or verified.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Migration failed',
        'detail' => $e->getMessage()
    ]);
}
