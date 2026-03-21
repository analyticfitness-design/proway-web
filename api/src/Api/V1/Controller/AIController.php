<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\AI\ContentSuggestionService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class AIController
{
    public function __construct(
        private readonly ContentSuggestionService $suggestions,
        private readonly AuthMiddleware           $middleware,
    ) {}

    /**
     * POST /api/v1/admin/ai/suggestions
     * Body: { client_id: int, platform: string, niche: string }
     */
    public function generate(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $clientId = (int) $request->input('client_id', 0);
        $platform = trim((string) $request->input('platform', ''));
        $niche    = trim((string) $request->input('niche', 'fitness'));

        if ($clientId <= 0) {
            Response::error('VALIDATION', 'client_id es obligatorio', 422);
        }

        if ($platform === '') {
            Response::error('VALIDATION', 'platform es obligatorio (Instagram, TikTok, YouTube)', 422);
        }

        $allowedPlatforms = ['Instagram', 'TikTok', 'YouTube'];
        if (!in_array($platform, $allowedPlatforms, true)) {
            Response::error('VALIDATION', 'platform debe ser uno de: ' . implode(', ', $allowedPlatforms), 422);
        }

        try {
            $result = $this->suggestions->generateSuggestions($clientId, $platform, $niche);
        } catch (\RuntimeException $e) {
            Response::error('AI_ERROR', $e->getMessage(), 422);
        }

        Response::success($result, 201);
    }

    /**
     * GET /api/v1/admin/ai/suggestions?client_id=&limit=
     */
    public function list(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $clientId = (int) $request->query('client_id', 0);
        $limit    = (int) $request->query('limit', 10);

        if ($clientId <= 0) {
            Response::error('VALIDATION', 'client_id es obligatorio', 422);
        }

        if ($limit < 1 || $limit > 50) {
            $limit = 10;
        }

        $items = $this->suggestions->listForClient($clientId, $limit);

        Response::success(['suggestions' => $items]);
    }

    /**
     * POST /api/v1/admin/ai/trend-analysis
     * Body: { client_id: int, platform: string, month: string }
     */
    public function trendAnalysis(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $clientId = (int) $request->input('client_id', 0);
        $platform = trim((string) $request->input('platform', ''));
        $month    = trim((string) $request->input('month', ''));

        if ($clientId <= 0) {
            Response::error('VALIDATION', 'client_id es obligatorio', 422);
        }

        if ($platform === '') {
            Response::error('VALIDATION', 'platform es obligatorio', 422);
        }

        if ($month === '') {
            $month = date('F Y'); // Current month in English; AI will handle
        }

        try {
            $result = $this->suggestions->generateTrendAnalysis($clientId, $platform, $month);
        } catch (\RuntimeException $e) {
            Response::error('AI_ERROR', $e->getMessage(), 422);
        }

        Response::success($result, 201);
    }
}
