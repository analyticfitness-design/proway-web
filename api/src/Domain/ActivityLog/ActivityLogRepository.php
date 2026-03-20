<?php
declare(strict_types=1);

namespace ProWay\Domain\ActivityLog;

interface ActivityLogRepository
{
    /** @return array[] Timeline entries for a project, newest first. */
    public function findForProject(int $projectId, int $limit = 50): array;

    /** Create a new activity log entry and return its ID. */
    public function create(array $data): int;
}
