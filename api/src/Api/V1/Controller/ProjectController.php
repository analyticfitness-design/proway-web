<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Project\ProjectService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class ProjectController
{
    public function __construct(
        private readonly ProjectService  $projects,
        private readonly AuthMiddleware  $middleware,
    ) {}

    /**
     * GET /api/v1/projects
     * Clients see their own projects; admins could extend this.
     */
    public function index(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);
        Response::success(['projects' => $this->projects->listForClient($user->id)]);
    }

    /**
     * GET /api/v1/projects/{id}
     */
    public function show(Request $request, array $vars): never
    {
        $this->middleware->requireAuth($request);
        $project = $this->projects->get((int) $vars['id']);

        if ($project === null) {
            Response::error('NOT_FOUND', 'Project not found', 404);
        }

        Response::success(['project' => $project]);
    }

    /**
     * PATCH /api/v1/projects/{id}/status — Admin only
     * Body: { status: 'pendiente'|'en_progreso'|'revision'|'completado' }
     */
    public function updateStatus(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $status = $request->input('status');
        if (empty($status)) {
            Response::error('VALIDATION', 'status is required', 422);
        }

        try {
            $ok = $this->projects->updateStatus((int) $vars['id'], $status);
            Response::success(['updated' => $ok]);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }
    }
}
