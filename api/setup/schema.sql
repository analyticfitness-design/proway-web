-- ============================================================
-- ProWay Lab — Database Schema
-- MySQL 8.x  |  charset utf8mb4
-- Run once to initialize the database
-- ============================================================

CREATE DATABASE IF NOT EXISTS prowaylab_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE prowaylab_db;

-- ── Clients (coaches que contratan ProWay) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS clients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(20)  UNIQUE NOT NULL,          -- pw-001, pw-002
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(150) UNIQUE NOT NULL,
    phone      VARCHAR(30),
    company    VARCHAR(120),                           -- nombre de su marca/negocio
    instagram  VARCHAR(80),
    plan_type  ENUM('video_individual','starter','growth','authority')
               DEFAULT 'video_individual',
    status     ENUM('activo','inactivo','prospecto') DEFAULT 'prospecto',
    notes      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Client profiles (info de marca del coach) ────────────────────────────────
CREATE TABLE IF NOT EXISTS client_profiles (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    client_id          INT  NOT NULL,
    brand_name         VARCHAR(120),
    brand_colors       JSON,                           -- ["#E31E24", "#000000"]
    target_audience    TEXT,
    content_style      VARCHAR(50),                    -- "educativo", "motivacional", "técnico"
    platforms          JSON,                           -- ["instagram", "tiktok", "youtube"]
    monthly_video_goal INT DEFAULT 4,
    password_hash      VARCHAR(255),                   -- bcrypt hash para login del cliente
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cp_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Auth tokens ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS auth_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    token      VARCHAR(128) UNIQUE NOT NULL,
    user_type  ENUM('admin','client') NOT NULL,
    user_id    INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Admins ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(120),
    role          ENUM('superadmin','editor') DEFAULT 'editor',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Projects (trabajos de producción) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    client_id    INT NOT NULL,
    project_code VARCHAR(30) UNIQUE NOT NULL,          -- PW-2026-001
    service_type VARCHAR(80) NOT NULL,                 -- "Video Elite", "Paquete Growth", etc.
    status       ENUM(
        'cotizacion','confirmado','en_produccion',
        'revision','entregado','facturado','pagado'
    ) DEFAULT 'cotizacion',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Deliverables (archivos entregados) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS deliverables (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    project_id   INT NOT NULL,
    type         ENUM('video','thumbnail','copy','brand_asset','revision','final')
                 DEFAULT 'video',
    title        VARCHAR(200) NOT NULL,
    file_url     VARCHAR(500),                         -- Google Drive, Dropbox, CDN link
    preview_url  VARCHAR(500),                         -- thumbnail preview
    description  TEXT,
    version      INT DEFAULT 1,
    delivered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_del_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Invoices (facturas) ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoices (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    client_id        INT NOT NULL,
    project_id       INT,
    invoice_number   VARCHAR(30) UNIQUE NOT NULL,      -- INV-2026-001
    amount_cop       DECIMAL(12,2) NOT NULL,
    tax_cop          DECIMAL(12,2) DEFAULT 0,
    total_cop        DECIMAL(12,2) NOT NULL,
    status           ENUM('borrador','enviada','pendiente','pagada','vencida','cancelada')
                     DEFAULT 'pendiente',
    due_date         DATE,
    paid_at          TIMESTAMP NULL,
    payment_method   VARCHAR(50),                      -- "transferencia", "PayU", "efectivo"
    payu_reference   VARCHAR(100),
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_client  FOREIGN KEY (client_id)  REFERENCES clients(id),
    CONSTRAINT fk_inv_project FOREIGN KEY (project_id) REFERENCES projects(id),
    INDEX idx_inv_client (client_id),
    INDEX idx_inv_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Brand Assets (activos de marca del cliente) ───────────────────────────────
CREATE TABLE IF NOT EXISTS brand_assets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    asset_type  ENUM('logo','color_palette','typography','guideline','template','other')
                NOT NULL,
    name        VARCHAR(200) NOT NULL,
    file_url    VARCHAR(500),
    description TEXT,
    version     INT DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ba_client FOREIGN KEY (client_id) REFERENCES clients(id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
