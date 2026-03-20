<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\ActivityLog\ActivityLogService;
use ProWay\Domain\Project\ProjectService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class ProjectController
{
    public function __construct(
        private readonly ProjectService     $projects,
        private readonly AuthMiddleware     $middleware,
        private readonly ?ActivityLogService $activityLog = null,
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
        $user = $this->middleware->requireAdmin($request);

        $status = $request->input('status');
        if (empty($status)) {
            Response::error('VALIDATION', 'status is required', 422);
        }

        try {
            $projectId = (int) $vars['id'];
            $ok = $this->projects->updateStatus($projectId, $status);

            // Log status change to activity timeline
            try {
                $this->activityLog?->log(
                    $projectId,
                    'status_change',
                    "Estado cambiado a \"{$status}\"",
                    $user->type,
                    $user->id,
                    ['new_status' => $status],
                );
            } catch (\Throwable) {
                // Never block primary operation
            }

            Response::success(['updated' => $ok]);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/projects/{id}/timeline
     */
    public function timeline(Request $request, array $vars): never
    {
        $this->middleware->requireAuth($request);

        $projectId = (int) $vars['id'];
        $project = $this->projects->get($projectId);

        if ($project === null) {
            Response::error('NOT_FOUND', 'Project not found', 404);
        }

        $entries = $this->activityLog?->getTimeline($projectId) ?? [];

        Response::success(['timeline' => $entries]);
    }
}
