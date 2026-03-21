<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Analytics\AnalyticsService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class AnalyticsController
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly AuthMiddleware   $middleware,
    ) {}

    // ── GET /api/v1/admin/analytics/summary ──────────────────────────────────────
    public function summary(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        Response::success([
            'mrr'              => $this->analytics->getMRR(),
            'mrr_neto'         => $this->analytics->getMRRNeto(),
            'revenue_month'    => $this->analytics->getRevenueThisMonth(),
            'churn_rate'       => $this->analytics->getChurnRate(),
            'ltv'              => $this->analytics->getLTV(),
            'arpu'             => $this->analytics->getARPU(),
            'overdue_invoices' => $this->analytics->getOverdueInvoices(),
            'clients_at_risk'  => $this->analytics->getClientsAtRisk(),
            'top_clients'      => $this->analytics->getTopClients(),
        ]);
    }

    // ── GET /api/v1/admin/analytics/projections ──────────────────────────────────
    public function projections(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        Response::success([
            'projections' => $this->analytics->getProjections(),
        ]);
    }
}
