<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\ActivityLog\ActivityLogService;
use ProWay\Domain\Project\ProjectService;
use ProWay\Domain\WhatsApp\WhatsAppNotifier;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class ProjectController
{
    public function __construct(
        private readonly ProjectService     $projects,
        private readonly AuthMiddleware     $middleware,
        private readonly ?ActivityLogService $activityLog = null,
        private readonly ?WhatsAppNotifier   $whatsApp = null,
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

            // WhatsApp notification
            try {
                $project = $this->projects->get($projectId);
                if ($project !== null) {
                    $this->whatsApp?->notifyProjectStatusChange(
                        (int) $project['client_id'],
                        $project['title'] ?? $project['service_type'] ?? '',
                        $status,
                    );
                }
            } catch (\Throwable) {
                // Never block primary operation
            }

            Response::success(['updated' => $ok]);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/calendar/events
     * Returns project events formatted for FullCalendar.js.
     * Clients see own projects; admins see all.
     */
    public function calendarEvents(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        $projects = $user->type === 'admin'
            ? $this->projects->listAllWithDates()
            : $this->projects->listWithDatesForClient($user->id);

        $today  = date('Y-m-d');
        $events = [];

        $completedStatuses = ['entregado', 'facturado', 'pagado'];

        foreach ($projects as $p) {
            $status   = $p['status'] ?? 'cotizacion';
            $title    = $p['title'] ?? $p['service_type'] ?? 'Sin título';
            $deadline = $p['deadline'] ?? null;
            $start    = $p['start_date'] ?? $deadline;

            // Determine color based on status / overdue
            $isOverdue = $deadline
                && $deadline < $today
                && !in_array($status, $completedStatuses, true);

            if ($isOverdue) {
                $color = '#E31E24';  // red — overdue
            } elseif (in_array($status, ['pagado', 'entregado'], true)) {
                $color = '#00FF87';  // green — completed
            } elseif (in_array($status, ['en_produccion', 'revision'], true)) {
                $color = '#00D9FF';  // cyan — in progress
            } else {
                $color = '#FACC15';  // yellow — confirmado / cotizacion
            }

            $events[] = [
                'id'            => $p['id'],
                'title'         => $title,
                'start'         => $start,
                'end'           => $deadline,
                'color'         => $color,
                'url'           => '/proyectos/' . $p['id'],
                'extendedProps' => [
                    'status'      => $status,
                    'client_name' => $p['client_name'] ?? '',
                ],
            ];
        }

        Response::success(['events' => $events]);
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
