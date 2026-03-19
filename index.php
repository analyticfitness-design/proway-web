<?php
/**
 * Front-controller for prowaylab.com
 *
 * Nginx config: root /code  |  try_files $uri $uri/ /index.php
 *
 * - /api/* requests  → delegated to api/index.php
 * - Clean URL pages  → served from the matching .html file
 * - Unknown paths    → 404
 */
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// ── API requests → delegate to api/index.php ──────────────────────────────
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api/index.php';
    exit;
}

// ── Static page router: clean URL → .html file ────────────────────────────
$pageMap = [
    '/'                => 'login.html',
    '/login'           => 'login.html',
    '/portal'          => 'portal.html',
    '/proyectos'       => 'proyectos.html',
    '/facturas'        => 'facturas.html',
    '/perfil'          => 'perfil.html',
    '/admin'           => 'admin.html',
    '/admin/proyectos' => 'admin/proyectos.html',
    '/admin/clientes'  => 'admin/clientes.html',
    '/admin/facturas'  => 'admin/facturas.html',
];

// Normalize trailing slash
$normalized = rtrim($uri, '/') ?: '/';

if (isset($pageMap[$normalized])) {
    $file = __DIR__ . '/' . $pageMap[$normalized];
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }
}

// ── 404 ───────────────────────────────────────────────────────────────────
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>404 — ProWay Lab</title>'
    . '<style>body{font-family:system-ui,sans-serif;background:#0a0a0f;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}</style></head>'
    . '<body><div style="text-align:center"><h1 style="color:#6366f1">404</h1><p>Página no encontrada</p>'
    . '<a href="/login" style="color:#818cf8">← Volver al portal</a></div></body></html>';
