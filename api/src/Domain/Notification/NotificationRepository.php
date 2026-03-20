<?php
declare(strict_types=1);

namespace ProWay\Domain\Notification;

interface NotificationRepository
{
    /** @return array[] Notifications for a given user, newest first. */
    public function findForUser(string $userType, int $userId, int $limit = 20): array;

    /** Count unread notifications for a user. */
    public function countUnread(string $userType, int $userId): int;

    /** Mark a single notification as read. */
    public function markRead(int $id): bool;

    /** Mark all notifications as read for a user. */
    public function markAllRead(string $userType, int $userId): int;

    /** Create a new notification and return its ID. */
    public function create(array $data): int;
}
