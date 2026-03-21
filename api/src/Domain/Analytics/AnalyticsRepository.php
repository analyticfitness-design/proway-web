<?php
declare(strict_types=1);

namespace ProWay\Domain\Analytics;

interface AnalyticsRepository
{
    /** MRR = SUM(total_cop) from paid invoices in the current month. */
    public function computeMRR(): float;

    /** Total revenue (total_cop) for invoices paid this month. */
    public function computeRevenueThisMonth(): float;

    /** Revenue grouped by month for the last N months. */
    public function revenueByMonth(int $months = 6): array;

    /** Churn rate: clients with no paid invoice in 60 days / total active * 100. */
    public function computeChurnRate(): float;

    /** LTV = ARPU * (1 / monthly_churn_rate). */
    public function computeLTV(): float;

    /** ARPU = revenue this month / distinct paying clients this month. */
    public function computeARPU(): float;

    /** Invoices with status pendiente/vencida and due_date < NOW(). */
    public function overdueInvoices(): array;

    /** Clients with no payment in 40+ days. */
    public function clientsAtRisk(): array;

    /** Linear projection based on last 3 months trend. */
    public function projectedRevenue(int $months = 3): array;

    /** Top N clients by total revenue. */
    public function topClientsByRevenue(int $limit = 5): array;

    /** Persist a monthly revenue snapshot. */
    public function saveSnapshot(array $data): void;
}
