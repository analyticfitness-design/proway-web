<?php
declare(strict_types=1);

namespace ProWay\Domain\Message;

interface MessageRepository
{
    /** Insert a new message and return its ID. */
    public function create(int $projectId, string $senderType, int $senderId, string $content): int;

    /** Fetch messages for a project, newest last (chat order). */
    public function findByProject(int $projectId, int $limit = 50, int $offset = 0): array;

    /** Mark all messages NOT from readerType as read. Returns affected rows. */
    public function markAsRead(int $projectId, string $readerType): int;

    /** Count unread messages for a reader (messages NOT from readerType). */
    public function countUnread(int $projectId, string $readerType): int;
}
