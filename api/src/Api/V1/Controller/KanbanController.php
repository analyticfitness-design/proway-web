<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\ActivityLog\ActivityLogService;
use ProWay\Domain\Project\ProjectService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class KanbanController
{
    private const VALID_STATUSES = [
        'cotizacion', 'confirmado', 'en_produccion', 'revision',
        'entregado', 'facturado', 'pagado',
    ];

    public function __construct(
        private readonly ProjectService     $projects,
        private readonly AuthMiddleware     $middleware,
        private readonly ?ActivityLogService $activityLog = null,
    ) {}

    /**
     * GET /api/v1/admin/kanban — returns projects grouped by status columns
     */
    public function board(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        Response::success([
            'columns' => $this->projects->listGroupedByStatus(),
        ]);
    }

    /**
     * PATCH /api/v1/admin/kanban/{id} — move card to new status/order
     */
    public function moveCard(Request $request, array $vars): never
    {
        $user = $this->requireAdmin($request);

        $id     = (int) $vars['id'];
        $status = trim((string) $request->input('status', ''));
        $order  = (int) $request->input('order', 0);

        if (!in_array($status, self::VALID_STATUSES, true)) {
            Response::error('VALIDATION', 'Estado inválido: ' . $status, 422);
        }

        $moved = $this->projects->reorder($id, $status, $order);

        if (!$moved) {
            Response::error('NOT_FOUND', 'Proyecto no encontrado', 404);
        }

        // Log activity
        try {
            $this->activityLog?->log(
                $id,
                'kanban_move',
                "Proyecto movido a \"{$status}\" (posición {$order})",
                $user->type,
                $user->id,
                ['status' => $status, 'order' => $order],
            );
        } catch (\Throwable) {
            // Never block primary operation
        }

        Response::success(['moved' => true]);
    }

    private function requireAdmin(Request $request): \ProWay\Domain\Auth\UserDTO
    {
        $user = $this->middleware->requireAuth($request);
        if ($user->type !== 'admin') {
            Response::error('FORBIDDEN', 'Admin access required', 403);
        }
        return $user;
    }
}
