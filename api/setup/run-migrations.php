<?php
declare(strict_types=1);

/**
 * run-migrations.php — Database migration runner for ProWay Lab.
 *
 * Scans api/setup/migrations/*.sql, executes any that haven't been run,
 * and records them in the _migrations table.
 *
 * Protected by API_SECRET header check.
 *
 * Usage:
 *   curl -H "X-Api-Secret: YOUR_SECRET" https://prowaylab.com/api/setup/run-migrations.php
 */

header('Content-Type: application/json; charset=utf-8');

// ── Load config ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';

// ── Auth: require API_SECRET ────────────────────────────────────────────────
$headers = getallheaders() ?: [];
$secret  = $headers['X-Api-Secret']
        ?? $headers['x-api-secret']
        ?? $_SERVER['HTTP_X_API_SECRET']
        ?? '';

if (API_SECRET === '' || $secret !== API_SECRET) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Forbidden — invalid or missing X-Api-Secret header.',
    ]);
    exit;
}

// ── Connect ─────────────────────────────────────────────────────────────────
try {
    $pdo = getDB();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database connection failed: ' . $e->getMessage(),
    ]);
    exit;
}

// ── Ensure _migrations table exists ─────────────────────────────────────────
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS _migrations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    filename    VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

// ── Gather already-executed migrations ──────────────────────────────────────
$executed = [];
$stmt = $pdo->query('SELECT filename FROM _migrations ORDER BY id');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $executed[$row['filename']] = true;
}

// ── Scan migration files ────────────────────────────────────────────────────
$migrationsDir = __DIR__ . '/migrations';
if (!is_dir($migrationsDir)) {
    echo json_encode([
        'success' => true,
        'message' => 'No migrations directory found.',
        'ran'     => [],
        'skipped' => [],
    ]);
    exit;
}

$files = glob($migrationsDir . '/*.sql');
sort($files); // alphabetical order

$ran     = [];
$skipped = [];
$errors  = [];

foreach ($files as $filePath) {
    $filename = basename($filePath);

    // Skip if already executed
    if (isset($executed[$filename])) {
        $skipped[] = $filename;
        continue;
    }

    $sql = file_get_contents($filePath);
    if ($sql === false || trim($sql) === '') {
        $errors[] = ['file' => $filename, 'error' => 'Could not read file or file is empty.'];
        continue;
    }

    try {
        $pdo->beginTransaction();

        // Execute the migration (may contain multiple statements)
        $pdo->exec($sql);

        // Record the migration
        $insert = $pdo->prepare('INSERT INTO _migrations (filename) VALUES (:f)');
        $insert->execute(['f' => $filename]);

        $pdo->commit();
        $ran[] = $filename;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = [
            'file'  => $filename,
            'error' => $e->getMessage(),
        ];
        // Stop on first error to avoid running dependent migrations out of order
        break;
    }
}

// ── Output results ──────────────────────────────────────────────────────────
$result = [
    'success' => empty($errors),
    'ran'     => $ran,
    'skipped' => $skipped,
    'errors'  => $errors,
    'total'   => count($files),
];

http_response_code(empty($errors) ? 200 : 500);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
