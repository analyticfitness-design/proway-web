<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Notification\NotificationService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class NotificationController
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuthMiddleware      $middleware,
    ) {}

    /**
     * GET /api/v1/notifications
     * List notifications for the authenticated user.
     */
    public function index(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);
        $limit = (int) ($request->query('limit') ?: 20);

        Response::success([
            'notifications' => $this->notifications->listForUser($user->type, $user->id, $limit),
        ]);
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, array $vars): never
    {
        $this->middleware->requireAuth($request);
        $ok = $this->notifications->markRead((int) $vars['id']);
        Response::success(['marked' => $ok]);
    }

    /**
     * GET /api/v1/notifications/unread-count
     * Return the unread count for the authenticated user.
     */
    public function unreadCount(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);
        Response::success([
            'count' => $this->notifications->countUnread($user->type, $user->id),
        ]);
    }
}
