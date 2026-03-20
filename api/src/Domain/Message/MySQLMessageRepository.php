<?php
declare(strict_types=1);

namespace ProWay\Domain\Message;

use PDO;

class MySQLMessageRepository implements MessageRepository
{
    public function __construct(private readonly PDO $db) {}

    public function create(int $projectId, string $senderType, int $senderId, string $content): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (project_id, sender_type, sender_id, content)
             VALUES (:project_id, :sender_type, :sender_id, :content)'
        );
        $stmt->execute([
            'project_id'  => $projectId,
            'sender_type' => $senderType,
            'sender_id'   => $senderId,
            'content'     => $content,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByProject(int $projectId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM messages
             WHERE project_id = ?
             ORDER BY created_at ASC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $projectId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function markAsRead(int $projectId, string $readerType): int
    {
        // Mark messages NOT sent by the reader as read
        $stmt = $this->db->prepare(
            'UPDATE messages
             SET is_read = 1
             WHERE project_id = ? AND sender_type != ? AND is_read = 0'
        );
        $stmt->execute([$projectId, $readerType]);

        return $stmt->rowCount();
    }

    public function countUnread(int $projectId, string $readerType): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM messages
             WHERE project_id = ? AND sender_type != ? AND is_read = 0'
        );
        $stmt->execute([$projectId, $readerType]);

        return (int) $stmt->fetchColumn();
    }
}
