<?php
declare(strict_types=1);

namespace ProWay\Domain\Project;

interface ProjectRepository
{
    public function findAllForClient(int $clientId): array;
    public function findById(int $id): ?array;
    public function updateStatus(int $id, string $status): bool;
}
