<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Message\MessageService;
use ProWay\Domain\Notification\NotificationService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class MessageController
{
    public function __construct(
        private readonly MessageService       $messages,
        private readonly AuthMiddleware        $middleware,
        private readonly NotificationService   $notifications,
    ) {}

    /**
     * GET /api/v1/projects/{id}/messages
     * List messages for a project. Auto-marks as read for the current user.
     */
    public function listMessages(Request $request, array $vars): never
    {
        $user      = $this->middleware->requireAuth($request);
        $projectId = (int) $vars['id'];

        // Auto-mark messages as read for the reader
        $this->messages->markRead($projectId, $user->type);

        $limit    = (int) ($request->query('limit') ?: 50);
        $messages = $this->messages->getMessages($projectId, $limit);

        Response::success(['messages' => $messages]);
    }

    /**
     * POST /api/v1/projects/{id}/messages
     * Send a message. Body: { "content": "..." }
     */
    public function send(Request $request, array $vars): never
    {
        $user      = $this->middleware->requireAuth($request);
        $projectId = (int) $vars['id'];
        $content   = trim((string) $request->input('content', ''));

        if ($content === '') {
            Response::error('VALIDATION', 'El contenido del mensaje es requerido.', 422);
        }

        $messageId = $this->messages->send($projectId, $user->type, $user->id, $content);

        // Notify the other party
        $targetType = $user->type === 'admin' ? 'client' : 'admin';
        $this->notifications->notify(
            userType: $targetType,
            userId:   0, // broadcast to type (notification partial filters by type)
            title:    'Nuevo mensaje en proyecto #' . $projectId,
            message:  mb_substr($content, 0, 100),
            type:     'message',
            link:     '/projects/' . $projectId . '?tab=chat',
        );

        Response::success(['id' => $messageId], 201);
    }

    /**
     * GET /api/v1/projects/{id}/messages/unread
     * Count unread messages for the current user in a project.
     */
    public function unreadCount(Request $request, array $vars): never
    {
        $user      = $this->middleware->requireAuth($request);
        $projectId = (int) $vars['id'];

        Response::success([
            'count' => $this->messages->unreadCount($projectId, $user->type),
        ]);
    }
}
