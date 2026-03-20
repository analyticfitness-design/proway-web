<?php
declare(strict_types=1);

namespace ProWay\Domain\Report;

use PDO;
use ProWay\Domain\Client\ClientService;
use ProWay\Domain\Deliverable\DeliverableService;
use ProWay\Domain\Project\ProjectService;
use ProWay\Domain\SocialMetrics\SocialMetricsService;

class MonthlyReportService
{
    public function __construct(
        private readonly PDO                   $db,
        private readonly ProjectService        $projects,
        private readonly DeliverableService    $deliverables,
        private readonly SocialMetricsService  $social,
        private readonly ClientService         $clients,
    ) {}

    // ── Report data gathering ────────────────────────────────────────────────

    /**
     * Gather all data needed for a monthly report.
     */
    public function generateForClient(int $clientId, int $year, int $month): array
    {
        $client = $this->clients->getById($clientId);
        if ($client === null) {
            throw new \InvalidArgumentException('Cliente no encontrado');
        }

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        // Active projects for this client
        $allProjects = $this->projects->listForClient($clientId);

        // Deliverables uploaded during the month
        $deliverables = $this->getDeliverablesByMonth($clientId, $startDate, $endDate);

        // Social metrics summary (if profiles exist)
        $socialSummary = $this->getSocialSummary($clientId, $startDate, $endDate);

        return [
            'client'       => $client,
            'year'         => $year,
            'month'        => $month,
            'period_label' => $this->getMonthName($month) . ' ' . $year,
            'projects'     => $allProjects,
            'deliverables' => $deliverables,
            'social'       => $socialSummary,
        ];
    }

    // ── CRUD operations ──────────────────────────────────────────────────────

    /**
     * Create or update a monthly report record.
     */
    public function save(int $clientId, int $year, int $month, ?string $recommendations, ?string $pdfPath): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO monthly_reports (client_id, year, month, recommendations, pdf_path, generated_at)
             VALUES (:client_id, :year, :month, :recommendations, :pdf_path, NOW())
             ON DUPLICATE KEY UPDATE
                recommendations = VALUES(recommendations),
                pdf_path        = VALUES(pdf_path),
                generated_at    = NOW()'
        );
        $stmt->execute([
            ':client_id'       => $clientId,
            ':year'            => $year,
            ':month'           => $month,
            ':recommendations' => $recommendations,
            ':pdf_path'        => $pdfPath,
        ]);

        $id = (int) $this->db->lastInsertId();

        // ON DUPLICATE KEY returns 0 for lastInsertId if it updated
        if ($id === 0) {
            $lookup = $this->db->prepare(
                'SELECT id FROM monthly_reports WHERE client_id = ? AND year = ? AND month = ?'
            );
            $lookup->execute([$clientId, $year, $month]);
            $id = (int) $lookup->fetchColumn();
        }

        return $id;
    }

    /**
     * List all generated reports (admin view).
     */
    public function listAll(): array
    {
        $stmt = $this->db->query(
            'SELECT r.*, c.nombre AS client_name, c.email AS client_email
             FROM monthly_reports r
             JOIN clientes c ON c.id = r.client_id
             ORDER BY r.year DESC, r.month DESC, c.nombre ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * List reports for a specific client.
     */
    public function listForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM monthly_reports
             WHERE client_id = ?
             ORDER BY year DESC, month DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a single report by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, c.nombre AS client_name, c.email AS client_email
             FROM monthly_reports r
             JOIN clientes c ON c.id = r.client_id
             WHERE r.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get a report for a specific client by ID (ensures ownership).
     */
    public function getForClient(int $clientId, int $reportId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM monthly_reports WHERE id = ? AND client_id = ?'
        );
        $stmt->execute([$reportId, $clientId]);
        return $stmt->fetch() ?: null;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Get deliverables for a client within a date range.
     */
    private function getDeliverablesByMonth(int $clientId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, p.service_type AS project_service
             FROM deliverables d
             JOIN proyectos p ON p.id = d.project_id
             WHERE p.client_id = ?
               AND d.delivered_at BETWEEN ? AND ?
             ORDER BY d.delivered_at DESC'
        );
        $stmt->execute([$clientId, $startDate, $endDate . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    /**
     * Get social metrics summary for the period.
     */
    private function getSocialSummary(int $clientId, string $startDate, string $endDate): array
    {
        $profiles = $this->social->getClientProfiles($clientId);
        if (empty($profiles)) {
            return [];
        }

        $summaries = [];
        foreach ($profiles as $profile) {
            $profileId = (int) $profile['id'];

            // Growth for the month
            $stmtFirst = $this->db->prepare(
                'SELECT followers FROM social_metrics_daily
                 WHERE profile_id = ? AND post_id IS NULL AND date >= ?
                 ORDER BY date ASC LIMIT 1'
            );
            $stmtFirst->execute([$profileId, $startDate]);
            $first = $stmtFirst->fetch();

            $stmtLast = $this->db->prepare(
                'SELECT followers FROM social_metrics_daily
                 WHERE profile_id = ? AND post_id IS NULL AND date <= ?
                 ORDER BY date DESC LIMIT 1'
            );
            $stmtLast->execute([$profileId, $endDate]);
            $last = $stmtLast->fetch();

            // Engagement totals
            $stmtTotals = $this->db->prepare(
                'SELECT SUM(likes) AS total_likes,
                        SUM(comments) AS total_comments,
                        SUM(views) AS total_views,
                        AVG(engagement_rate) AS avg_engagement
                 FROM social_metrics_daily
                 WHERE profile_id = ? AND date BETWEEN ? AND ?'
            );
            $stmtTotals->execute([$profileId, $startDate, $endDate]);
            $totals = $stmtTotals->fetch() ?: [];

            // Top posts
            $stmtTopPosts = $this->db->prepare(
                'SELECT sp.caption, sp.permalink, sp.post_type,
                        COALESCE(sp.thumbnail_url, \'\') AS thumbnail_url,
                        m.likes, m.comments, m.views
                 FROM social_posts sp
                 JOIN social_metrics_daily m ON m.post_id = sp.id
                 WHERE sp.profile_id = ? AND m.date BETWEEN ? AND ?
                 ORDER BY (COALESCE(m.likes,0) + COALESCE(m.comments,0)) DESC
                 LIMIT 3'
            );
            $stmtTopPosts->execute([$profileId, $startDate, $endDate]);
            $topPosts = $stmtTopPosts->fetchAll();

            $followersStart = $first ? (int) $first['followers'] : (int) ($profile['followers'] ?? 0);
            $followersEnd   = $last  ? (int) $last['followers']  : (int) ($profile['followers'] ?? 0);
            $growth         = $followersEnd - $followersStart;

            $summaries[] = [
                'platform'         => $profile['platform'],
                'username'         => $profile['username'],
                'followers_start'  => $followersStart,
                'followers_end'    => $followersEnd,
                'follower_growth'  => $growth,
                'total_likes'      => (int) ($totals['total_likes'] ?? 0),
                'total_comments'   => (int) ($totals['total_comments'] ?? 0),
                'total_views'      => (int) ($totals['total_views'] ?? 0),
                'avg_engagement'   => round((float) ($totals['avg_engagement'] ?? 0), 2),
                'top_posts'        => $topPosts,
            ];
        }

        return $summaries;
    }

    /**
     * Return Spanish month name.
     */
    private function getMonthName(int $month): string
    {
        $names = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        return $names[$month] ?? '';
    }
}
