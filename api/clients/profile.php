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
    // Merge client base data + profile data
    $base = [
        'id'         => $client['id'],
        'code'       => $client['code'] ?? '',
        'name'       => $client['name'] ?? '',
        'email'      => $client['email'] ?? '',
        'phone'      => $client['phone'] ?? '',
        'company'    => $client['company'] ?? '',
        'instagram'  => $client['instagram'] ?? '',
        'plan_type'  => $client['plan_type'] ?? '',
        'status'     => $client['status'] ?? 'active',
        'created_at' => $client['created_at'] ?? '',
    ];

    $stmt = $db->prepare(
        'SELECT id, client_id, brand_name, brand_colors, target_audience,
                content_style, platforms, monthly_video_goal, updated_at
         FROM client_profiles WHERE client_id = ?'
    );
    $stmt->execute([$client['id']]);
    $profile = $stmt->fetch();

    if ($profile) {
        foreach (['brand_colors', 'platforms'] as $f) {
            if (isset($profile[$f]) && is_string($profile[$f])) {
                $profile[$f] = json_decode($profile[$f], true);
            }
        }
        $base['brand_name']        = $profile['brand_name'] ?? '';
        $base['brand_colors']      = $profile['brand_colors'] ?? [];
        $base['target_audience']   = $profile['target_audience'] ?? '';
        $base['content_style']     = $profile['content_style'] ?? '';
        $base['platforms']         = $profile['platforms'] ?? [];
        $base['monthly_video_goal'] = $profile['monthly_video_goal'] ?? 0;
        $base['profile_updated_at'] = $profile['updated_at'] ?? '';
    }

    respond($base);
}

// ── PUT ───────────────────────────────────────────────────────────────────────
$body = getJsonBody();

// Fields that go into `clients` table
$clientAllowed = ['name', 'email', 'phone', 'company', 'instagram'];
$clientUpdate  = [];
foreach ($clientAllowed as $field) {
    if (array_key_exists($field, $body)) {
        $clientUpdate[$field] = $body[$field];
    }
}

if (!empty($clientUpdate)) {
    $set  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($clientUpdate)));
    $vals = array_values($clientUpdate);
    $vals[] = $client['id'];
    $db->prepare("UPDATE clients SET $set WHERE id = ?")->execute($vals);
}

// Fields that go into `client_profiles` table
$profileAllowed = ['brand_name', 'brand_colors', 'target_audience', 'content_style', 'platforms', 'monthly_video_goal'];
$profileUpdate  = [];
foreach ($profileAllowed as $field) {
    if (array_key_exists($field, $body)) {
        $profileUpdate[$field] = $body[$field];
    }
}

if (!empty($profileUpdate)) {
    // Encode JSON fields
    foreach (['brand_colors', 'platforms'] as $f) {
        if (array_key_exists($f, $profileUpdate) && is_array($profileUpdate[$f])) {
            $profileUpdate[$f] = json_encode($profileUpdate[$f]);
        }
    }

    // Upsert
    $checkStmt = $db->prepare('SELECT id FROM client_profiles WHERE client_id = ?');
    $checkStmt->execute([$client['id']]);
    $exists = $checkStmt->fetchColumn();

    if ($exists) {
        $set  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($profileUpdate)));
        $vals = array_values($profileUpdate);
        $vals[] = $client['id'];
        $db->prepare("UPDATE client_profiles SET $set WHERE client_id = ?")->execute($vals);
    } else {
        $profileUpdate['client_id'] = $client['id'];
        $cols = implode(', ', array_keys($profileUpdate));
        $phs  = implode(', ', array_fill(0, count($profileUpdate), '?'));
        $db->prepare("INSERT INTO client_profiles ($cols) VALUES ($phs)")->execute(array_values($profileUpdate));
    }
}

if (empty($clientUpdate) && empty($profileUpdate)) {
    respondError('No hay campos validos para actualizar', 422);
}

// Return updated merged profile
$stmt = $db->prepare('SELECT id, code, name, email, phone, company, instagram, plan_type, status, created_at FROM clients WHERE id = ?');
$stmt->execute([$client['id']]);
$updatedClient = $stmt->fetch();

$stmt = $db->prepare(
    'SELECT brand_name, brand_colors, target_audience, content_style, platforms, monthly_video_goal, updated_at
     FROM client_profiles WHERE client_id = ?'
);
$stmt->execute([$client['id']]);
$profile = $stmt->fetch();

$result = $updatedClient ?: [];
if ($profile) {
    foreach (['brand_colors', 'platforms'] as $f) {
        if (isset($profile[$f]) && is_string($profile[$f])) {
            $profile[$f] = json_decode($profile[$f], true);
        }
    }
    $result = array_merge($result, $profile);
}

respond($result);
