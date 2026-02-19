<?php
/**
 * ProWay Lab — One-time setup & seed script
 * DELETE THIS FILE after running it once.
 * Access: GET /api/setup.php?secret=PROWAY_SETUP_2026
 */
header('Content-Type: application/json');

if (($_GET['secret'] ?? '') !== 'PROWAY_SETUP_2026') {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden']));
}

require_once __DIR__ . '/config/database.php';
$pdo = getDB();
$results = [];
$errors  = [];

// 1. Recrear tabla projects con estructura correcta
try {
    $pdo->prepare("DROP TABLE IF EXISTS projects")->execute();
    $pdo->prepare("CREATE TABLE projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        project_code VARCHAR(20) NOT NULL UNIQUE,
        service_type VARCHAR(100) NOT NULL,
        title VARCHAR(200),
        description TEXT,
        price_cop DECIMAL(12,2) NOT NULL DEFAULT 0,
        currency VARCHAR(10) NOT NULL DEFAULT 'COP',
        status ENUM('cotizacion','en_progreso','en_revision','entregado','facturado','pagado') NOT NULL DEFAULT 'cotizacion',
        start_date DATE,
        deadline DATE,
        delivered_at DATETIME,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        INDEX idx_client_status (client_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();
    $results[] = 'projects: recreada con estructura correcta';
} catch (Exception $e) { $errors[] = 'projects: ' . $e->getMessage(); }

// 2. Tabla deliverables
try {
    $pdo->prepare("CREATE TABLE IF NOT EXISTS deliverables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'video',
        file_url TEXT,
        preview_url TEXT,
        description TEXT,
        version INT NOT NULL DEFAULT 1,
        delivered_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_project (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();
    $results[] = 'deliverables: OK';
} catch (Exception $e) { $errors[] = 'deliverables: ' . $e->getMessage(); }

// 3. Tabla invoices
try {
    $pdo->prepare("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(20) NOT NULL UNIQUE,
        client_id INT NOT NULL,
        project_id INT,
        amount_cop DECIMAL(12,2) NOT NULL,
        tax_cop DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_cop DECIMAL(12,2) NOT NULL,
        status ENUM('pendiente','pagada') NOT NULL DEFAULT 'pendiente',
        due_date DATE,
        paid_at DATETIME,
        payment_method VARCHAR(50),
        payu_reference VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
        INDEX idx_client_status (client_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();
    $results[] = 'invoices: OK';
} catch (Exception $e) { $errors[] = 'invoices: ' . $e->getMessage(); }

// 4. Admins
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO admins (username, password_hash, name, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin',  password_hash('prowayAdmin2026!', PASSWORD_DEFAULT), 'Administrador ProWay', 'superadmin']);
    $stmt->execute(['editor', password_hash('proEditor2026!',   PASSWORD_DEFAULT), 'Editor ProWay',        'editor']);
    $results[] = 'admins: seeded';
} catch (Exception $e) { $errors[] = 'admins: ' . $e->getMessage(); }

echo json_encode(['ok' => empty($errors), 'results' => $results, 'errors' => $errors,
    'message' => empty($errors) ? 'Setup completo. ELIMINA este archivo ahora.' : 'Setup parcial.'], JSON_PRETTY_PRINT);
