<?php
declare(strict_types=1);

namespace ProWay\Domain\Project;

class ProjectService
{
    private const VALID_STATUSES = ['pendiente', 'en_progreso', 'revision', 'completado'];

    public function __construct(private readonly ProjectRepository $repo) {}

    public function listForClient(int $clientId): array
    {
        return $this->repo->findAllForClient($clientId);
    }

    public function get(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid project status: $status");
        }

        return $this->repo->updateStatus($id, $status);
    }
}
