<?php
declare(strict_types=1);

namespace ProWay\Domain\ErrorLog;

interface ErrorLogRepository
{
    /** Create a new error log entry and return its ID. */
    public function create(array $data): int;

    /** @return array[] Recent error log entries, newest first. */
    public function findRecent(int $limit = 50): array;

    /** Count error logs grouped by level. */
    public function countByLevel(): array;
}
