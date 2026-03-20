<?php
declare(strict_types=1);

namespace ProWay\Domain\Message;

class MessageService
{
    public function __construct(private readonly MessageRepository $repo) {}

    /**
     * Send a message in a project chat.
     */
    public function send(int $projectId, string $senderType, int $senderId, string $content): int
    {
        return $this->repo->create($projectId, $senderType, $senderId, $content);
    }

    /**
     * Get messages for a project (newest last for chat display).
     */
    public function getMessages(int $projectId, int $limit = 50): array
    {
        return $this->repo->findByProject($projectId, $limit);
    }

    /**
     * Mark all messages as read for the given reader type.
     */
    public function markRead(int $projectId, string $readerType): void
    {
        $this->repo->markAsRead($projectId, $readerType);
    }

    /**
     * Count unread messages for the given reader type.
     */
    public function unreadCount(int $projectId, string $readerType): int
    {
        return $this->repo->countUnread($projectId, $readerType);
    }
}
