<?php
declare(strict_types=1);

/**
 * GET /api/admin/stats
 *
 * Returns business metrics for dashboard and n8n weekly reports.
 * Requires admin authentication.
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');

// Accept admin Bearer token OR n8n API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$validKey = defined('N8N_API_KEY') ? N8N_API_KEY : env('N8N_API_KEY', 'proway-n8n-2026');

if ($apiKey !== $validKey) {
    authenticateAdmin();
}
$db = getDB();

// Period filter: ?period=week|month|all (default: month)
$period = $_GET['period'] ?? 'month';

$dateFilter = match ($period) {
    'week'  => "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    default => '',
};

// Total clients
$total = (int) $db->query('SELECT COUNT(*) FROM clients')->fetchColumn();

// New leads in period
$stmtLeads = $db->prepare("SELECT COUNT(*) FROM clients WHERE status = 'prospecto' $dateFilter");
$stmtLeads->execute();
$leads = (int) $stmtLeads->fetchColumn();

// Active clients (not prospecto)
$active = (int) $db->query(
    "SELECT COUNT(*) FROM clients WHERE status != 'prospecto'"
)->fetchColumn();

// Active projects
$projects = (int) $db->query(
    "SELECT COUNT(*) FROM projects WHERE status NOT IN ('entregado','facturado','pagado')"
)->fetchColumn();

// Total projects in period
$stmtProj = $db->prepare("SELECT COUNT(*) FROM projects WHERE 1=1 $dateFilter");
$stmtProj->execute();
$totalProjects = (int) $stmtProj->fetchColumn();

// Revenue (paid invoices in period)
$stmtRev = $db->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'pagada' $dateFilter"
);
$stmtRev->execute();
$revenue = (int) $stmtRev->fetchColumn();

// MRR estimate from active plan types
$mrr = $db->query(
    "SELECT COALESCE(SUM(
        CASE plan_type
            WHEN 'authority' THEN 2200000
            WHEN 'growth'    THEN 1600000
            WHEN 'starter'   THEN 1200000
            ELSE 0
        END
    ), 0) FROM clients WHERE status = 'activo'"
)->fetchColumn();

// Clients by status
$byStatus = $db->query(
    "SELECT status, COUNT(*) AS total FROM clients GROUP BY status ORDER BY total DESC"
)->fetchAll();

// Clients by plan
$byPlan = $db->query(
    "SELECT plan_type, COUNT(*) AS total FROM clients GROUP BY plan_type ORDER BY total DESC"
)->fetchAll();

respond([
    'period'          => $period,
    'total_clients'   => $total,
    'new_leads'       => $leads,
    'active_clients'  => $active,
    'active_projects' => $projects,
    'total_projects'  => $totalProjects,
    'revenue_cop'     => (int) $revenue,
    'mrr_cop'         => (int) $mrr,
    'by_status'       => $byStatus,
    'by_plan'         => $byPlan,
]);
