<?php
declare(strict_types=1);

namespace ProWay\Domain\Approval;

interface ApprovalRepository
{
    public function findByDeliverable(int $deliverableId): array;
    public function findByProject(int $projectId): array;
    public function findPendingAll(int $limit = 50): array;
    public function upsert(array $data): int;
}
