<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$method = requireMethods(['GET', 'PUT']);
$client = authenticateClient();
$db     = getDB();

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id, client_id, brand_name, brand_colors, target_audience,
                content_style, platforms, monthly_video_goal, updated_at
         FROM client_profiles WHERE client_id = ?'
    );
    $stmt->execute([$client['id']]);
    $profile = $stmt->fetch();

    if (!$profile) {
        respond(null);
    }

    foreach (['brand_colors', 'platforms'] as $f) {
        if (isset($profile[$f]) && is_string($profile[$f])) {
            $profile[$f] = json_decode($profile[$f], true);
        }
    }

    respond($profile);
}

// ── PUT ───────────────────────────────────────────────────────────────────────
$body = getJsonBody();

$allowed = ['brand_name', 'brand_colors', 'target_audience', 'content_style', 'platforms', 'monthly_video_goal'];
$update  = [];
foreach ($allowed as $field) {
    if (array_key_exists($field, $body)) {
        $update[$field] = $body[$field];
    }
}

if (empty($update)) {
    respondError('No hay campos válidos para actualizar', 422);
}

// Encode JSON fields
foreach (['brand_colors', 'platforms'] as $f) {
    if (array_key_exists($f, $update) && is_array($update[$f])) {
        $update[$f] = json_encode($update[$f]);
    }
}

// Upsert
$checkStmt = $db->prepare('SELECT id FROM client_profiles WHERE client_id = ?');
$checkStmt->execute([$client['id']]);
$exists = $checkStmt->fetchColumn();

if ($exists) {
    $set  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update)));
    $vals = array_values($update);
    $vals[] = $client['id'];
    $db->prepare("UPDATE client_profiles SET $set WHERE client_id = ?")->execute($vals);
} else {
    $update['client_id'] = $client['id'];
    $cols = implode(', ', array_keys($update));
    $phs  = implode(', ', array_fill(0, count($update), '?'));
    $db->prepare("INSERT INTO client_profiles ($cols) VALUES ($phs)")->execute(array_values($update));
}

// Return updated profile
$stmt = $db->prepare(
    'SELECT id, client_id, brand_name, brand_colors, target_audience,
            content_style, platforms, monthly_video_goal, updated_at
     FROM client_profiles WHERE client_id = ?'
);
$stmt->execute([$client['id']]);
$profile = $stmt->fetch();
foreach (['brand_colors', 'platforms'] as $f) {
    if (isset($profile[$f]) && is_string($profile[$f])) {
        $profile[$f] = json_decode($profile[$f], true);
    }
}

respond($profile);
