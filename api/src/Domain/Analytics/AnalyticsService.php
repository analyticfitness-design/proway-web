<?php
declare(strict_types=1);

namespace ProWay\Domain\Analytics;

use ProWay\Infrastructure\Cache\CacheInterface;

class AnalyticsService
{
    private const CACHE_TTL = 600; // 10 minutes

    public function __construct(
        private readonly AnalyticsRepository $repo,
        private readonly ?CacheInterface $cache = null,
    ) {}

    // ── KPI getters ──────────────────────────────────────────────────────────────

    public function getMRR(): float
    {
        return $this->cached('analytics:mrr', fn() => $this->repo->computeMRR());
    }

    /** MRR neto = MRR / 1.19 (IVA 19% Colombia). */
    public function getMRRNeto(): float
    {
        return round($this->getMRR() / 1.19, 2);
    }

    public function getRevenueThisMonth(): float
    {
        return $this->cached('analytics:revenue_month', fn() => $this->repo->computeRevenueThisMonth());
    }

    public function getChurnRate(): float
    {
        return $this->cached('analytics:churn_rate', fn() => $this->repo->computeChurnRate());
    }

    public function getLTV(): float
    {
        return $this->cached('analytics:ltv', fn() => $this->repo->computeLTV());
    }

    public function getARPU(): float
    {
        return $this->cached('analytics:arpu', fn() => $this->repo->computeARPU());
    }

    public function getOverdueInvoices(): array
    {
        return $this->cached('analytics:overdue', fn() => $this->repo->overdueInvoices());
    }

    public function getClientsAtRisk(): array
    {
        return $this->cached('analytics:at_risk', fn() => $this->repo->clientsAtRisk());
    }

    public function getProjections(int $months = 3): array
    {
        return $this->cached('analytics:projections', fn() => $this->repo->projectedRevenue($months));
    }

    public function getTopClients(int $limit = 5): array
    {
        return $this->cached('analytics:top_clients', fn() => $this->repo->topClientsByRevenue($limit));
    }

    public function getRevenueByMonth(int $months = 12): array
    {
        return $this->cached("analytics:rev_by_month:{$months}", fn() => $this->repo->revenueByMonth($months));
    }

    // ── Snapshot ─────────────────────────────────────────────────────────────────

    public function saveSnapshot(array $data): void
    {
        $this->repo->saveSnapshot($data);
    }

    // ── Cache helper ─────────────────────────────────────────────────────────────

    private function cached(string $key, callable $compute): mixed
    {
        if ($this->cache === null) {
            return $compute();
        }

        $value = $this->cache->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $compute();
        $this->cache->set($key, $value, self::CACHE_TTL);
        return $value;
    }
}
