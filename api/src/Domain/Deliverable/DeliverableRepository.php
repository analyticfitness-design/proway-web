<?php
declare(strict_types=1);

namespace ProWay\Domain\Deliverable;

interface DeliverableRepository
{
    public function findByProject(int $projectId): array;
    public function findById(int $id): ?array;
    public function create(array $data): int;
}
