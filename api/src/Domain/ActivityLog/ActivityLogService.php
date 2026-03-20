<?php
declare(strict_types=1);

namespace ProWay\Domain\ActivityLog;

class ActivityLogService
{
    public function __construct(private readonly ActivityLogRepository $repo) {}

    public function getTimeline(int $projectId, int $limit = 50): array
    {
        return $this->repo->findForProject($projectId, $limit);
    }

    /**
     * Log an activity on a project.
     *
     * @param int         $projectId
     * @param string      $action      e.g. 'status_change', 'invoice_created', 'project_created', 'note_added'
     * @param string      $description Human-readable description
     * @param string      $userType    'admin'|'client'|'system'
     * @param int|null    $userId
     * @param array|null  $metadata    Optional structured data
     */
    public function log(
        int     $projectId,
        string  $action,
        string  $description = '',
        string  $userType = 'system',
        ?int    $userId = null,
        ?array  $metadata = null,
    ): int {
        return $this->repo->create([
            'project_id'  => $projectId,
            'action'      => $action,
            'description' => $description,
            'user_type'   => $userType,
            'user_id'     => $userId,
            'metadata'    => $metadata,
        ]);
    }
}
