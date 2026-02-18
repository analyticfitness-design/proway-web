<?php
declare(strict_types=1);

/**
 * ProWay Lab — Database Seeder
 *
 * Run ONCE via CLI or protected web request to populate initial data.
 * Access is blocked by .htaccess in production.
 *
 * Usage: php seed.php
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();

echo "ProWay Lab — Seeding database...\n\n";

// ── ADMINS ────────────────────────────────────────────────────────────────────
$admins = [
    [
        'username' => 'admin',
        'password' => 'ProWay2026!',
        'name'     => 'Administrador Principal',
        'role'     => 'superadmin',
    ],
    [
        'username' => 'proway',
        'password' => 'admin123',
        'name'     => 'ProWay Operador',
        'role'     => 'editor',
    ],
];

$adminStmt = $db->prepare(
    'INSERT IGNORE INTO admins (username, password_hash, name, role) VALUES (?, ?, ?, ?)'
);

foreach ($admins as $a) {
    $hash = password_hash($a['password'], PASSWORD_BCRYPT);
    $adminStmt->execute([$a['username'], $hash, $a['name'], $a['role']]);
    echo "  Admin: {$a['username']} (role: {$a['role']})\n";
}

// ── DEMO CLIENTS (coaches ficticios) ──────────────────────────────────────────
$clients = [
    [
        'code'      => 'pw-001',
        'name'      => 'Andrés García',
        'email'     => 'andres@fitpro.co',
        'phone'     => '+573001234567',
        'company'   => 'FitPro Colombia',
        'instagram' => '@andresgarciafitpro',
        'plan_type' => 'authority',
        'status'    => 'activo',
        'brand'     => [
            'brand_name'         => 'FitPro Colombia',
            'brand_colors'       => json_encode(['#E31E24', '#000000', '#FFFFFF']),
            'target_audience'    => 'Hombres 25-40 años que quieren ganar músculo y perder grasa',
            'content_style'      => 'educativo',
            'platforms'          => json_encode(['instagram', 'youtube', 'tiktok']),
            'monthly_video_goal' => 12,
        ],
    ],
    [
        'code'      => 'pw-002',
        'name'      => 'Valentina Torres',
        'email'     => 'vale@vfit.co',
        'phone'     => '+573009876543',
        'company'   => 'VFit Studio',
        'instagram' => '@valentina.vfit',
        'plan_type' => 'growth',
        'status'    => 'activo',
        'brand'     => [
            'brand_name'         => 'VFit Studio',
            'brand_colors'       => json_encode(['#FF6B9D', '#1A1A2E', '#FFFFFF']),
            'target_audience'    => 'Mujeres 20-35 años interesadas en fitness femenino y nutrición',
            'content_style'      => 'motivacional',
            'platforms'          => json_encode(['instagram', 'tiktok']),
            'monthly_video_goal' => 8,
        ],
    ],
    [
        'code'      => 'pw-003',
        'name'      => 'Sebastián Mora',
        'email'     => 'seba@fitcorp.co',
        'phone'     => '+573005555555',
        'company'   => 'FitCorp Training',
        'instagram' => '@sebamorafit',
        'plan_type' => 'starter',
        'status'    => 'activo',
        'brand'     => [
            'brand_name'         => 'FitCorp Training',
            'brand_colors'       => json_encode(['#00D9FF', '#0C0C0F', '#FFFFFF']),
            'target_audience'    => 'Atletas y deportistas que buscan rendimiento y estética',
            'content_style'      => 'técnico',
            'platforms'          => json_encode(['instagram', 'youtube']),
            'monthly_video_goal' => 4,
        ],
    ],
];

$clientStmt = $db->prepare(
    'INSERT IGNORE INTO clients (code, name, email, phone, company, instagram, plan_type, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$profileStmt = $db->prepare(
    'INSERT IGNORE INTO client_profiles
     (client_id, brand_name, brand_colors, target_audience, content_style, platforms,
      monthly_video_goal, password_hash)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$demoPassword = password_hash('pw2026', PASSWORD_BCRYPT);

foreach ($clients as $c) {
    $clientStmt->execute([
        $c['code'], $c['name'], $c['email'], $c['phone'],
        $c['company'], $c['instagram'], $c['plan_type'], $c['status'],
    ]);

    // Get the client id (may already exist if re-seeding)
    $idStmt = $db->prepare('SELECT id FROM clients WHERE code = ?');
    $idStmt->execute([$c['code']]);
    $clientId = (int) $idStmt->fetchColumn();

    if ($clientId) {
        $b = $c['brand'];
        $profileStmt->execute([
            $clientId,
            $b['brand_name'],
            $b['brand_colors'],
            $b['target_audience'],
            $b['content_style'],
            $b['platforms'],
            $b['monthly_video_goal'],
            $demoPassword,
        ]);
        echo "  Client: {$c['name']} ({$c['code']}) — plan: {$c['plan_type']}\n";
    }
}

// ── DEMO PROJECTS ─────────────────────────────────────────────────────────────
$projectsData = [
    [
        'code'         => 'pw-001',
        'project_code' => 'PW-2026-001',
        'service_type' => 'Paquete Authority',
        'title'        => 'Contenido Febrero 2026 — FitPro Colombia',
        'price_cop'    => 2200000.00,
        'status'       => 'en_produccion',
        'start_date'   => '2026-02-01',
        'deadline'     => '2026-02-28',
    ],
    [
        'code'         => 'pw-002',
        'project_code' => 'PW-2026-002',
        'service_type' => 'Paquete Growth',
        'title'        => 'Contenido Febrero 2026 — VFit Studio',
        'price_cop'    => 1600000.00,
        'status'       => 'revision',
        'start_date'   => '2026-02-05',
        'deadline'     => '2026-02-28',
    ],
    [
        'code'         => 'pw-003',
        'project_code' => 'PW-2026-003',
        'service_type' => 'Paquete Starter',
        'title'        => 'Onboarding — FitCorp Training',
        'price_cop'    => 1200000.00,
        'status'       => 'confirmado',
        'start_date'   => '2026-02-15',
        'deadline'     => '2026-02-28',
    ],
];

$projStmt = $db->prepare(
    'INSERT IGNORE INTO projects
     (client_id, project_code, service_type, title, price_cop, status, start_date, deadline)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($projectsData as $p) {
    $idStmt = $db->prepare('SELECT id FROM clients WHERE code = ?');
    $idStmt->execute([$p['code']]);
    $clientId = (int) $idStmt->fetchColumn();
    if (!$clientId) continue;

    $projStmt->execute([
        $clientId,
        $p['project_code'],
        $p['service_type'],
        $p['title'],
        $p['price_cop'],
        $p['status'],
        $p['start_date'],
        $p['deadline'],
    ]);
    echo "  Project: {$p['project_code']} — {$p['title']}\n";
}

echo "\nSeeding completado. Passwords demo: 'pw2026' para todos los clientes.\n";
echo "Admins: admin/ProWay2026! y proway/admin123\n";
