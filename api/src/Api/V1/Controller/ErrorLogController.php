<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\ErrorLog\ErrorLogService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class ErrorLogController
{
    public function __construct(
        private readonly ErrorLogService  $errors,
        private readonly AuthMiddleware   $middleware,
    ) {}

    /**
     * POST /api/v1/errors
     * Public endpoint — no auth required. Receives frontend error reports.
     */
    public function store(Request $request, array $vars): never
    {
        $body = $request->getBody();

        if (empty($body['message'])) {
            Response::error('VALIDATION', 'message is required', 422);
        }

        // Auto-capture user agent from headers
        $userAgent = $request->header('User-Agent')
                  ?? $_SERVER['HTTP_USER_AGENT']
                  ?? null;

        // If auth cookie exists, capture user info (best-effort, no failure)
        $userId   = null;
        $userType = null;
        try {
            $token = $request->resolveAccessToken();
            if ($token !== null) {
                $user = $this->middleware->requireAuth($request);
                $userId   = $user->id;
                $userType = $user->type;
            }
        } catch (\Throwable) {
            // Silently ignore — error logging should never fail due to auth
        }

        $data = [
            'level'      => $body['level']   ?? 'error',
            'message'    => $body['message'],
            'stack'      => $body['stack']    ?? null,
            'url'        => $body['url']      ?? null,
            'user_agent' => $userAgent,
            'user_id'    => $userId,
            'user_type'  => $userType,
            'context'    => $body['context']  ?? null,
        ];

        try {
            $id = $this->errors->create($data);
            Response::success(['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/admin/errors
     * Admin-only — returns recent error logs.
     */
    public function index(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $limit = (int) ($request->query('limit') ?: 50);

        Response::success([
            'errors' => $this->errors->listRecent($limit),
            'counts' => $this->errors->countByLevel(),
        ]);
    }
}
