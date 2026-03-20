<?php
declare(strict_types=1);

namespace ProWay\Domain\Notification;

use PDO;

class MySQLNotificationRepository implements NotificationRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findForUser(string $userType, int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications
             WHERE user_type = ? AND user_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userType);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countUnread(string $userType, int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE user_type = ? AND user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userType, $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead(string $userType, int $userId): int
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1
             WHERE user_type = ? AND user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userType, $userId]);
        return $stmt->rowCount();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications
                (user_type, user_id, type, title, message, link)
             VALUES
                (:user_type, :user_id, :type, :title, :message, :link)'
        );
        $stmt->execute([
            'user_type' => $data['user_type'],
            'user_id'   => $data['user_id'],
            'type'      => $data['type']    ?? 'system',
            'title'     => $data['title'],
            'message'   => $data['message'] ?? null,
            'link'      => $data['link']    ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
