<?php
/**
 * ProWay Lab — Setup: crea tablas + seed inicial
 * GET /api/setup.php?secret=PROWAY_SETUP_2026
 * ELIMINAR este archivo después de usarlo.
 */
declare(strict_types=1);
header('Content-Type: application/json');

if (($_GET['secret'] ?? '') !== 'PROWAY_SETUP_2026') {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden']));
}

require_once __DIR__ . '/config/database.php';
$db = getDB();

$results = [];
$errors  = [];

// ── TABLAS ────────────────────────────────────────────────────────────────────

$tables = [
'clients' => "CREATE TABLE IF NOT EXISTS clients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(20)  UNIQUE NOT NULL,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(150) UNIQUE NOT NULL,
    phone      VARCHAR(30),
    company    VARCHAR(120),
    instagram  VARCHAR(80),
    plan_type  ENUM('video_individual','starter','growth','authority') DEFAULT 'video_individual',
    status     ENUM('activo','inactivo','prospecto') DEFAULT 'prospecto',
    notes      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'client_profiles' => "CREATE TABLE IF NOT EXISTS client_profiles (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    client_id          INT  NOT NULL,
    brand_name         VARCHAR(120),
    brand_colors       JSON,
    target_audience    TEXT,
    content_style      VARCHAR(50),
    platforms          JSON,
    monthly_video_goal INT DEFAULT 4,
    password_hash      VARCHAR(255),
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cp_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'auth_tokens' => "CREATE TABLE IF NOT EXISTS auth_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    token      VARCHAR(128) UNIQUE NOT NULL,
    user_type  ENUM('admin','client') NOT NULL,
    user_id    INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'admins' => "CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(120),
    role          ENUM('superadmin','editor') DEFAULT 'editor',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'projects' => "CREATE TABLE IF NOT EXISTS projects (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    client_id    INT NOT NULL,
    project_code VARCHAR(30) UNIQUE NOT NULL,
    service_type VARCHAR(80) NOT NULL,
    status       ENUM('cotizacion','confirmado','en_produccion','revision','entregado','facturado','pagado') DEFAULT 'cotizacion',
    title        VARCHAR(200),
    description  TEXT,
    price_cop    DECIMAL(12,2) NOT NULL,
    currency     VARCHAR(3)   DEFAULT 'COP',
    start_date   DATE,
    deadline     DATE,
    delivered_at TIMESTAMP NULL,
    notes        TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_proj_client FOREIGN KEY (client_id) REFERENCES clients(id),
    INDEX idx_proj_client (client_id),
    INDEX idx_proj_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'deliverables' => "CREATE TABLE IF NOT EXISTS deliverables (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    project_id   INT NOT NULL,
    type         ENUM('video','thumbnail','copy','brand_asset','revision','final') DEFAULT 'video',
    title        VARCHAR(200) NOT NULL,
    file_url     VARCHAR(500),
    preview_url  VARCHAR(500),
    description  TEXT,
    version      INT DEFAULT 1,
    delivered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_del_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'invoices' => "CREATE TABLE IF NOT EXISTS invoices (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    client_id        INT NOT NULL,
    project_id       INT,
    invoice_number   VARCHAR(30) UNIQUE NOT NULL,
    amount_cop       DECIMAL(12,2) NOT NULL,
    tax_cop          DECIMAL(12,2) DEFAULT 0,
    total_cop        DECIMAL(12,2) NOT NULL,
    status           ENUM('borrador','enviada','pendiente','pagada','vencida','cancelada') DEFAULT 'pendiente',
    due_date         DATE,
    paid_at          TIMESTAMP NULL,
    payment_method   VARCHAR(50),
    payu_reference   VARCHAR(100),
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_client  FOREIGN KEY (client_id)  REFERENCES clients(id),
    CONSTRAINT fk_inv_project FOREIGN KEY (project_id) REFERENCES projects(id),
    INDEX idx_inv_client (client_id),
    INDEX idx_inv_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'brand_assets' => "CREATE TABLE IF NOT EXISTS brand_assets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    asset_type  ENUM('logo','color_palette','typography','guideline','template','other') NOT NULL,
    name        VARCHAR(200) NOT NULL,
    file_url    VARCHAR(500),
    description TEXT,
    version     INT DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ba_client FOREIGN KEY (client_id) REFERENCES clients(id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($tables as $name => $sql) {
    try {
        $db->prepare($sql)->execute();
        $results[] = "$name: OK";
    } catch (\Exception $e) {
        $errors[] = "$name: " . $e->getMessage();
    }
}

// ── ADMINS ────────────────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare("INSERT IGNORE INTO admins (username, password_hash, name, role) VALUES (?,?,?,?)");
    $stmt->execute(['admin',  password_hash('ProWay2026!', PASSWORD_DEFAULT), 'Administrador Principal', 'superadmin']);
    $stmt->execute(['proway', password_hash('admin123',    PASSWORD_DEFAULT), 'ProWay Operador',         'editor']);
    $results[] = 'admins: admin/ProWay2026! + proway/admin123';
} catch (\Exception $e) { $errors[] = 'admins: ' . $e->getMessage(); }

// ── CLIENTE DE PRUEBA ─────────────────────────────────────────────────────────
try {
    $db->prepare("INSERT IGNORE INTO clients (code, name, email, phone, company, instagram, plan_type, status)
                  VALUES (?,?,?,?,?,?,?,?)")
       ->execute(['TEST-001', '[TEST] Coach Prueba', 'test@prowaylab.com', '+57 000 0000000',
                  '[TEST] Marca Prueba', '@test_coach', 'starter', 'activo']);

    $stmt = $db->prepare("SELECT id FROM clients WHERE code = 'TEST-001'");
    $stmt->execute();
    $clientId = (int)$stmt->fetchColumn();

    if ($clientId) {
        $db->prepare("INSERT IGNORE INTO client_profiles (client_id, brand_name, brand_colors, target_audience, content_style, platforms, monthly_video_goal, password_hash)
                      VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$clientId, '[TEST] Marca Prueba',
                      json_encode(['#00D9FF', '#0C0C0F']),
                      '[PRUEBA] Audiencia de ejemplo: coaches fitness 25-40 años',
                      'educativo',
                      json_encode(['instagram', 'tiktok']),
                      4,
                      password_hash('test1234', PASSWORD_DEFAULT)]);

        // Proyecto de prueba
        $db->prepare("INSERT IGNORE INTO projects (client_id, project_code, service_type, title, price_cop, status, start_date, deadline)
                      VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$clientId, 'TEST-PW-001', 'Paquete Starter',
                      '[TEST] Proyecto de prueba — Starter', 1200000.00,
                      'en_produccion', date('Y-m-01'), date('Y-m-t')]);

        // Factura de prueba
        $db->prepare("INSERT IGNORE INTO invoices (client_id, invoice_number, amount_cop, total_cop, status, due_date)
                      VALUES (?,?,?,?,?,?)")
           ->execute([$clientId, 'TEST-INV-001', 1200000.00, 1200000.00, 'pendiente', date('Y-m-t')]);

        $results[] = 'cliente prueba: test@prowaylab.com / test1234';
        $results[] = 'proyecto prueba: TEST-PW-001';
        $results[] = 'factura prueba: TEST-INV-001';
    }
} catch (\Exception $e) { $errors[] = 'cliente prueba: ' . $e->getMessage(); }

echo json_encode([
    'ok'      => empty($errors),
    'results' => $results,
    'errors'  => $errors,
    'message' => empty($errors)
        ? 'Setup ProWay completo. Tablas creadas y datos de prueba listos. ELIMINA este archivo.'
        : 'Setup parcial. Revisa errores.',
    'accesos' => [
        'admin'   => ['usuario' => 'admin', 'password' => 'ProWay2026!', 'url' => '/login'],
        'cliente' => ['email' => 'test@prowaylab.com', 'password' => 'test1234', 'url' => '/login-cliente'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
