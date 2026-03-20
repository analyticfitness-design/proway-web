<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\SocialMetrics\SocialMetricsService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class SocialMetricsController
{
    public function __construct(
        private readonly SocialMetricsService $social,
        private readonly AuthMiddleware       $middleware,
    ) {}

    // ── Admin endpoints ─────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/social-profiles — list all active profiles (admin)
     */
    public function listProfiles(Request $request, array $vars): never
    {
        $this->requireAdmin($request);
        Response::success(['profiles' => $this->social->getAllActiveProfiles()]);
    }

    /**
     * POST /api/v1/admin/social-profiles — add profile for client
     * Body: { client_id, platform, username }
     */
    public function addProfile(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        $clientId = (int) $request->input('client_id', 0);
        $platform = trim((string) $request->input('platform', ''));
        $username = trim((string) $request->input('username', ''));

        if ($clientId === 0) {
            Response::error('VALIDATION', 'client_id is required', 422);
        }

        if ($platform === '' || $username === '') {
            Response::error('VALIDATION', 'platform and username are required', 422);
        }

        try {
            $id = $this->social->addProfile($clientId, $platform, $username);
            Response::success(['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Response::error('CONFLICT', 'This profile already exists', 409);
            }
            throw $e;
        }
    }

    /**
     * DELETE /api/v1/admin/social-profiles/{id} — remove profile
     */
    public function removeProfile(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        $id = (int) $vars['id'];
        $ok = $this->social->removeProfile($id);

        if (!$ok) {
            Response::error('NOT_FOUND', 'Profile not found', 404);
        }

        Response::success(['deleted' => true]);
    }

    /**
     * PATCH /api/v1/social-posts/{id}/proway — toggle is_proway flag
     * Body: { is_proway: 1|0 }
     */
    public function toggleProWay(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        $postId  = (int) $vars['id'];
        $isProWay = (bool) $request->input('is_proway', 0);

        $ok = $this->social->toggleProWay($postId, $isProWay);

        Response::success(['updated' => $ok]);
    }

    // ── Client endpoints ────────────────────────────────────────────────────────

    /**
     * GET /api/v1/social-metrics/dashboard — client's social dashboard
     * Uses auth to determine client_id.
     */
    public function clientDashboard(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        $profiles = $this->social->getClientProfiles($user->id);

        $dashboards = [];
        foreach ($profiles as $profile) {
            $dashboards[] = $this->social->getProfileDashboard((int) $profile['id'], 30);
        }

        Response::success([
            'profiles'   => $profiles,
            'dashboards' => $dashboards,
        ]);
    }

    /**
     * GET /api/v1/social-metrics/profiles/{id}/metrics?days=30
     * Detailed profile metrics for a specific profile.
     */
    public function profileMetrics(Request $request, array $vars): never
    {
        $this->middleware->requireAuth($request);

        $profileId = (int) $vars['id'];
        $days      = (int) $request->query('days', 30);

        if ($days < 1 || $days > 365) {
            $days = 30;
        }

        $dashboard  = $this->social->getProfileDashboard($profileId, $days);
        $comparison = $this->social->getProWayComparison($profileId);

        if (empty($dashboard)) {
            Response::error('NOT_FOUND', 'Profile not found', 404);
        }

        Response::success([
            'dashboard'  => $dashboard,
            'comparison' => $comparison,
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function requireAdmin(Request $request): \ProWay\Domain\Auth\UserDTO
    {
        $user = $this->middleware->requireAuth($request);
        if ($user->type !== 'admin') {
            Response::error('FORBIDDEN', 'Admin access required', 403);
        }
        return $user;
    }
}
