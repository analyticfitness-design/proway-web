<?php
declare(strict_types=1);

/**
 * Auto-Deploy Webhook
 *
 * Called by GitHub (push event) or EasyPanel to trigger a `git pull`
 * on the production container.
 *
 * URL: https://prowaylab.com/deploy-webhook.php?secret=YOUR_SECRET
 *  or: POST with header X-Deploy-Secret: YOUR_SECRET
 *
 * Security:
 *   - Secret validated via query param OR X-Deploy-Secret header
 *   - Only processes pushes to the `main` branch
 *   - Returns 403 immediately on any auth failure (no leak of details)
 */

// ── Response helpers ──────────────────────────────────────────────────────────

function jsonResponse(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Load .env (local dev) — in Docker env vars are injected by EasyPanel ─────

$envFile = __DIR__ . '/api/.env';
if (file_exists($envFile)) {
    $parsed = parse_ini_file($envFile);
    if ($parsed !== false) {
        foreach ($parsed as $k => $v) {
            $_ENV[$k] = $v;
        }
    }
}

$deploySecret = $_ENV['DEPLOY_SECRET'] ?? (getenv('DEPLOY_SECRET') ?: '');

// ── Log helper ────────────────────────────────────────────────────────────────

$logFile = __DIR__ . '/api/data/deploy.log';

function logDeploy(string $level, string $message, array $context = []): void
{
    global $logFile;

    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $line = sprintf(
        "[%s] [%s] %s%s\n",
        date('c'),
        strtoupper($level),
        $message,
        $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''
    );

    // Keep log file bounded — rotate at 512 KB
    if (file_exists($logFile) && filesize($logFile) > 524288) {
        rename($logFile, $logFile . '.1');
    }

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ── Authenticate ──────────────────────────────────────────────────────────────

if (empty($deploySecret)) {
    logDeploy('error', 'DEPLOY_SECRET not configured — denying all requests');
    jsonResponse(500, ['error' => 'Webhook not configured']);
}

$providedSecret = $_GET['secret']
    ?? $_SERVER['HTTP_X_DEPLOY_SECRET']
    ?? '';

// Constant-time comparison to prevent timing attacks
if (!hash_equals($deploySecret, $providedSecret)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    logDeploy('warn', 'Unauthorized deploy attempt', ['ip' => $ip]);
    jsonResponse(403, ['error' => 'Forbidden']);
}

// ── Parse GitHub webhook payload ──────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Allow GET requests (e.g., manual curl trigger) — skip branch check
if ($method === 'POST') {
    $body    = file_get_contents('php://input') ?: '';
    $payload = json_decode($body, true);

    if (is_array($payload)) {
        $ref = $payload['ref'] ?? '';

        // Only deploy on pushes to main
        if ($ref !== '' && $ref !== 'refs/heads/main') {
            logDeploy('info', 'Skipped: push to non-main branch', ['ref' => $ref]);
            jsonResponse(200, [
                'status'  => 'skipped',
                'message' => 'Not a push to main branch',
                'ref'     => $ref,
            ]);
        }
    }
}

// ── Run git pull ───────────────────────────────────────────────────────────────

$cmd    = 'cd /code && git pull origin main 2>&1';
$output = shell_exec($cmd);
$output = $output ?? '(no output)';

$success = str_contains($output, 'Already up to date')
        || str_contains($output, 'Updating ')
        || str_contains($output, 'Fast-forward');

$status = $success ? 'ok' : 'error';

logDeploy(
    $success ? 'info' : 'error',
    'Deploy ' . $status,
    ['output' => trim($output)]
);

jsonResponse($success ? 200 : 500, [
    'status'    => $status,
    'timestamp' => date('c'),
    'output'    => trim($output),
]);
