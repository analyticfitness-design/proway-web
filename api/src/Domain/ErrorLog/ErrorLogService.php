<?php
declare(strict_types=1);

namespace ProWay\Domain\ErrorLog;

class ErrorLogService
{
    private const VALID_LEVELS = ['error', 'warning', 'info'];

    public function __construct(private readonly ErrorLogRepository $repo) {}

    /**
     * Create a new error log entry.
     *
     * @param array $data  Keys: level, message, stack, url, user_agent, user_id, user_type, context
     */
    public function create(array $data): int
    {
        if (empty($data['message'])) {
            throw new \InvalidArgumentException('message is required');
        }

        // Sanitise level
        if (!in_array($data['level'] ?? 'error', self::VALID_LEVELS, true)) {
            $data['level'] = 'error';
        }

        return $this->repo->create($data);
    }

    /** @return array[] Recent error log entries, newest first. */
    public function listRecent(int $limit = 50): array
    {
        return $this->repo->findRecent($limit);
    }

    /** Count error logs grouped by level. */
    public function countByLevel(): array
    {
        return $this->repo->countByLevel();
    }
}
