<?php
declare(strict_types=1);

namespace ProWay\Domain\Analytics;

use PDO;

class MySQLAnalyticsRepository implements AnalyticsRepository
{
    public function __construct(private readonly PDO $db) {}

    // ── MRR ──────────────────────────────────────────────────────────────────────
    public function computeMRR(): float
    {
        $stmt = $this->db->query(
            "SELECT COALESCE(SUM(total_cop), 0)
             FROM invoices
             WHERE status = 'pagada'
               AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
               AND paid_at <  DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')"
        );
        return (float) $stmt->fetchColumn();
    }

    // ── Revenue this month ───────────────────────────────────────────────────────
    public function computeRevenueThisMonth(): float
    {
        return $this->computeMRR();
    }

    // ── Revenue by month ─────────────────────────────────────────────────────────
    public function revenueByMonth(int $months = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month,
                    COALESCE(SUM(total_cop), 0) AS total
             FROM invoices
             WHERE status = 'pagada'
               AND paid_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL :months MONTH)
             GROUP BY month
             ORDER BY month ASC"
        );
        $stmt->execute([':months' => $months - 1]);
        return $stmt->fetchAll();
    }

    // ── Churn rate ───────────────────────────────────────────────────────────────
    public function computeChurnRate(): float
    {
        // Total active clients
        $totalStmt = $this->db->query(
            "SELECT COUNT(*) FROM clients WHERE status = 'activo'"
        );
        $totalActive = (int) $totalStmt->fetchColumn();

        if ($totalActive === 0) {
            return 0.0;
        }

        // Churned = active clients with no paid invoice in the last 60 days
        $churnedStmt = $this->db->query(
            "SELECT COUNT(*) FROM clients c
             WHERE c.status = 'activo'
               AND c.id NOT IN (
                   SELECT DISTINCT i.client_id
                   FROM invoices i
                   WHERE i.status = 'pagada'
                     AND i.paid_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
               )"
        );
        $churned = (int) $churnedStmt->fetchColumn();

        return round(($churned / $totalActive) * 100, 2);
    }

    // ── ARPU ─────────────────────────────────────────────────────────────────────
    public function computeARPU(): float
    {
        $stmt = $this->db->query(
            "SELECT COALESCE(SUM(total_cop), 0) AS revenue,
                    COUNT(DISTINCT client_id)    AS paying_clients
             FROM invoices
             WHERE status = 'pagada'
               AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
               AND paid_at <  DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')"
        );
        $row = $stmt->fetch();

        $revenue = (float) ($row['revenue'] ?? 0);
        $clients = (int) ($row['paying_clients'] ?? 0);

        return $clients > 0 ? round($revenue / $clients, 2) : 0.0;
    }

    // ── LTV ──────────────────────────────────────────────────────────────────────
    public function computeLTV(): float
    {
        $arpu      = $this->computeARPU();
        $churnRate = $this->computeChurnRate();

        // churnRate is a percentage (e.g. 5.0 means 5%)
        $churnDecimal = $churnRate / 100;

        if ($churnDecimal <= 0) {
            // If no churn, use 24-month cap to avoid infinity
            return round($arpu * 24, 2);
        }

        return round($arpu * (1 / $churnDecimal), 2);
    }

    // ── Overdue invoices ─────────────────────────────────────────────────────────
    public function overdueInvoices(): array
    {
        $stmt = $this->db->query(
            "SELECT i.id, i.invoice_number, i.client_id, i.total_cop, i.due_date, i.status,
                    c.name AS client_name, c.email AS client_email,
                    DATEDIFF(NOW(), i.due_date) AS days_overdue
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE i.status IN ('pendiente', 'enviada', 'vencida')
               AND i.due_date < NOW()
             ORDER BY i.due_date ASC"
        );
        return $stmt->fetchAll();
    }

    // ── Clients at risk ──────────────────────────────────────────────────────────
    public function clientsAtRisk(): array
    {
        $stmt = $this->db->query(
            "SELECT c.id, c.name, c.email, c.plan_type,
                    MAX(i.paid_at) AS last_payment,
                    DATEDIFF(NOW(), MAX(i.paid_at)) AS days_since_payment
             FROM clients c
             LEFT JOIN invoices i ON i.client_id = c.id AND i.status = 'pagada'
             WHERE c.status = 'activo'
             GROUP BY c.id, c.name, c.email, c.plan_type
             HAVING last_payment IS NULL OR days_since_payment >= 40
             ORDER BY days_since_payment DESC"
        );
        return $stmt->fetchAll();
    }

    // ── Projected revenue ────────────────────────────────────────────────────────
    public function projectedRevenue(int $months = 3): array
    {
        // Get last 3 months of revenue for linear trend calculation
        $stmt = $this->db->query(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month,
                    COALESCE(SUM(total_cop), 0) AS total
             FROM invoices
             WHERE status = 'pagada'
               AND paid_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 3 MONTH)
               AND paid_at <  DATE_FORMAT(NOW(), '%Y-%m-01')
             GROUP BY month
             ORDER BY month ASC"
        );
        $historical = $stmt->fetchAll();

        if (count($historical) < 2) {
            // Not enough data — flat projection from current MRR
            $currentMRR = $this->computeMRR();
            $projections = [];
            for ($i = 1; $i <= $months; $i++) {
                $projections[] = [
                    'month' => date('Y-m', strtotime("+{$i} months", strtotime(date('Y-m-01')))),
                    'projected' => $currentMRR,
                ];
            }
            return $projections;
        }

        // Linear regression: y = a + b*x
        $n = count($historical);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($historical as $idx => $row) {
            $x = $idx + 1;
            $y = (float) $row['total'];
            $sumX  += $x;
            $sumY  += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            $b = 0;
        } else {
            $b = ($n * $sumXY - $sumX * $sumY) / $denominator;
        }
        $a = ($sumY - $b * $sumX) / $n;

        $projections = [];
        for ($i = 1; $i <= $months; $i++) {
            $x = $n + $i;
            $projected = max(0, $a + $b * $x);
            $projections[] = [
                'month' => date('Y-m', strtotime("+{$i} months", strtotime(date('Y-m-01')))),
                'projected' => round($projected, 2),
            ];
        }

        return $projections;
    }

    // ── Top clients by revenue ───────────────────────────────────────────────────
    public function topClientsByRevenue(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.name, c.email, c.plan_type,
                    COALESCE(SUM(i.total_cop), 0) AS total_revenue,
                    COUNT(i.id) AS invoice_count,
                    MAX(i.paid_at) AS last_payment
             FROM clients c
             INNER JOIN invoices i ON i.client_id = c.id AND i.status = 'pagada'
             WHERE c.status = 'activo'
             GROUP BY c.id, c.name, c.email, c.plan_type
             ORDER BY total_revenue DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Save snapshot ────────────────────────────────────────────────────────────
    public function saveSnapshot(array $data): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO revenue_snapshots (month, mrr, total_revenue, new_clients, churned, total_active)
             VALUES (:month, :mrr, :total_revenue, :new_clients, :churned, :total_active)
             ON DUPLICATE KEY UPDATE
                mrr = VALUES(mrr),
                total_revenue = VALUES(total_revenue),
                new_clients = VALUES(new_clients),
                churned = VALUES(churned),
                total_active = VALUES(total_active)"
        );
        $stmt->execute([
            ':month'         => $data['month'],
            ':mrr'           => $data['mrr'],
            ':total_revenue' => $data['total_revenue'],
            ':new_clients'   => $data['new_clients'],
            ':churned'       => $data['churned'],
            ':total_active'  => $data['total_active'],
        ]);
    }
}
