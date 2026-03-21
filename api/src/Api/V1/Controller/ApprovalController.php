<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Approval\ApprovalService;
use ProWay\Domain\Approval\MySQLApprovalRepository;
use ProWay\Domain\Notification\NotificationService;
use ProWay\Domain\ActivityLog\ActivityLogService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class ApprovalController
{
    public function __construct(
        private readonly ApprovalService      $approvals,
        private readonly AuthMiddleware        $middleware,
        private readonly NotificationService   $notifications,
        private readonly ActivityLogService    $activityLog,
        private readonly MySQLApprovalRepository $approvalRepo,
    ) {}

    /**
     * POST /api/v1/deliverables/{id}/approve — client reviews a deliverable
     */
    public function review(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        $deliverableId = (int) $vars['id'];
        $status        = trim((string) $request->input('status', ''));
        $comment       = trim((string) $request->input('comment', '')) ?: null;

        if ($status === '') {
            Response::error('VALIDATION', 'El campo status es requerido (approved | changes_requested).', 422);
        }

        // Security: verify client owns the deliverable's project
        $ownership = $this->approvalRepo->clientOwnsDeliverable($deliverableId, $user->id);
        if ($ownership === null) {
            Response::error('FORBIDDEN', 'No tienes acceso a este entregable.', 403);
        }

        try {
            $result = $this->approvals->review($deliverableId, $user->id, $status, $comment);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }

        $projectId = (int) $ownership['project_id'];

        // Notify admin about the review
        try {
            $statusLabel = $status === 'approved' ? 'aprobado' : 'cambios solicitados';
            $this->notifications->notify(
                userType: 'admin',
                userId:   0,
                title:    'Revisión de entregable',
                message:  "Cliente revisó entregable: {$statusLabel}",
                type:     'approval',
                link:     '/admin?tab=approvals',
            );
        } catch (\Throwable) {}

        // Log activity on project
        try {
            $this->activityLog->log(
                $projectId,
                'deliverable_reviewed',
                "Entregable {$status}",
                $user->type,
                $user->id,
            );
        } catch (\Throwable) {}

        Response::success(['approval' => $result]);
    }

    /**
     * GET /api/v1/admin/approvals — admin sees pending/changes_requested queue
     */
    public function listAll(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $limit = (int) ($request->query('limit') ?: 50);

        Response::success([
            'approvals' => $this->approvals->listPendingAll($limit),
        ]);
    }
}
