<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Brief\BriefService;
use ProWay\Domain\Brief\MySQLBriefRepository;
use ProWay\Domain\Notification\NotificationService;
use ProWay\Domain\ActivityLog\ActivityLogService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class BriefController
{
    public function __construct(
        private readonly BriefService           $briefs,
        private readonly AuthMiddleware          $middleware,
        private readonly NotificationService     $notifications,
        private readonly ActivityLogService      $activityLog,
        private readonly MySQLBriefRepository    $briefRepo,
    ) {}

    /**
     * GET /api/v1/projects/{id}/brief — get brief for project
     */
    public function show(Request $request, array $vars): never
    {
        $user      = $this->middleware->requireAuth($request);
        $projectId = (int) $vars['id'];

        // Clients can only see their own project's brief
        if ($user->type === 'client' && !$this->briefRepo->clientOwnsProject($projectId, $user->id)) {
            Response::error('FORBIDDEN', 'No tienes acceso a este proyecto.', 403);
        }

        $brief = $this->briefs->getByProject($projectId);

        Response::success(['brief' => $brief]);
    }

    /**
     * PUT /api/v1/projects/{id}/brief — save/update brief (admin or client who owns project)
     */
    public function save(Request $request, array $vars): never
    {
        $user      = $this->middleware->requireAuth($request);
        $projectId = (int) $vars['id'];

        // Clients can only edit their own project's brief
        if ($user->type === 'client' && !$this->briefRepo->clientOwnsProject($projectId, $user->id)) {
            Response::error('FORBIDDEN', 'No tienes acceso a este proyecto.', 403);
        }

        // If client and brief is already submitted, deny editing
        if ($user->type === 'client') {
            $existing = $this->briefs->getByProject($projectId);
            if ($existing !== null && $existing['status'] === 'submitted') {
                Response::error('FORBIDDEN', 'El brief ya fue enviado. Contacta al equipo para modificaciones.', 403);
            }
        }

        $fields = [
            'objective'        => trim((string) $request->input('objective', '')),
            'target_audience'  => trim((string) $request->input('target_audience', '')),
            'tone'             => trim((string) $request->input('tone', '')),
            'key_messages'     => trim((string) $request->input('key_messages', '')),
            'references_urls'  => trim((string) $request->input('references_urls', '')),
            'filming_date'     => trim((string) $request->input('filming_date', '')),
            'location'         => trim((string) $request->input('location', '')),
            'talent_notes'     => trim((string) $request->input('talent_notes', '')),
            'special_reqs'     => trim((string) $request->input('special_reqs', '')),
        ];

        $this->briefs->saveDraft($projectId, $fields);

        // Log activity
        try {
            $this->activityLog->log(
                $projectId,
                'brief_saved',
                'Brief creativo guardado como borrador',
                $user->type,
                $user->id,
            );
        } catch (\Throwable) {}

        $brief = $this->briefs->getByProject($projectId);

        Response::success(['brief' => $brief]);
    }

    /**
     * POST /api/v1/projects/{id}/brief/submit — client submits brief, notifies admin
     */
    public function submit(Request $request, array $vars): never
    {
        $user      = $this->middleware->requireAuth($request);
        $projectId = (int) $vars['id'];

        // Clients can only submit their own project's brief
        if ($user->type === 'client' && !$this->briefRepo->clientOwnsProject($projectId, $user->id)) {
            Response::error('FORBIDDEN', 'No tienes acceso a este proyecto.', 403);
        }

        try {
            $this->briefs->submit($projectId);
        } catch (\RuntimeException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }

        // Notify admin
        try {
            $this->notifications->notify(
                userType: 'admin',
                userId:   0,
                title:    'Brief creativo enviado',
                message:  "El cliente {$user->name} envió el brief del proyecto #{$projectId}",
                type:     'brief',
                link:     '/admin?tab=projects',
            );
        } catch (\Throwable) {}

        // Log activity
        try {
            $this->activityLog->log(
                $projectId,
                'brief_submitted',
                'Brief creativo enviado para revisión',
                $user->type,
                $user->id,
            );
        } catch (\Throwable) {}

        $brief = $this->briefs->getByProject($projectId);

        Response::success(['brief' => $brief]);
    }
}
