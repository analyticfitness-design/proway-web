<?php
declare(strict_types=1);

namespace ProWay\Domain\Project;

class ProjectService
{
    private const VALID_STATUSES = ['pendiente', 'produccion', 'revision', 'entrega', 'completado', 'cancelado'];

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

    // ── Admin-scope methods ────────────────────────────────────────────────────

    public function listAll(): array
    {
        return $this->repo->findAll();
    }

    public function countActive(): int
    {
        return $this->repo->countActive();
    }
}
