<?php
declare(strict_types=1);

namespace ProWay\Domain\Brief;

interface BriefRepository
{
    public function findByProject(int $projectId): ?array;
    public function upsert(array $data): int;
    public function submit(int $projectId): bool;
}
