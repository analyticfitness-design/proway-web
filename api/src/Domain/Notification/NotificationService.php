<?php
declare(strict_types=1);

namespace ProWay\Domain\Notification;

class NotificationService
{
    public function __construct(private readonly NotificationRepository $repo) {}

    public function listForUser(string $userType, int $userId, int $limit = 20): array
    {
        return $this->repo->findForUser($userType, $userId, $limit);
    }

    public function countUnread(string $userType, int $userId): int
    {
        return $this->repo->countUnread($userType, $userId);
    }

    public function markRead(int $id): bool
    {
        return $this->repo->markRead($id);
    }

    public function markAllRead(string $userType, int $userId): int
    {
        return $this->repo->markAllRead($userType, $userId);
    }

    /**
     * Convenience method to send a notification.
     *
     * @param string $userType 'admin'|'client'
     * @param int    $userId
     * @param string $title
     * @param string $message
     * @param string $type    e.g. 'invoice', 'project', 'system'
     * @param string|null $link  Optional in-app link
     */
    public function notify(
        string $userType,
        int    $userId,
        string $title,
        string $message = '',
        string $type = 'system',
        ?string $link = null,
    ): int {
        return $this->repo->create([
            'user_type' => $userType,
            'user_id'   => $userId,
            'type'      => $type,
            'title'     => $title,
            'message'   => $message,
            'link'      => $link,
        ]);
    }
}
