<?php
declare(strict_types=1);

namespace ProWay\Domain\Project;

interface ProjectRepository
{
    public function findAllForClient(int $clientId): array;
    public function findById(int $id): ?array;
    public function updateStatus(int $id, string $status): bool;

    /** Create a new project and return its new ID. */
    public function create(array $data): int;

    // ── Calendar queries ─────────────────────────────────────────────────────
    /** Projects that have start_date or deadline set, with client info. */
    public function findAllWithDates(): array;
    /** Same as above but filtered by client. */
    public function findAllWithDatesForClient(int $clientId): array;

    // ── Admin-scope queries ────────────────────────────────────────────────────
    /** All projects across all clients with client info joined, newest first. */
    public function findAll(): array;
    /** Count projects in active states (produccion, revision, entrega). */
    public function countActive(): int;
    /** Count projects grouped by status. */
    public function countByStatus(): array;
}
