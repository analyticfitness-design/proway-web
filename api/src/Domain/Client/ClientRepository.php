<?php
declare(strict_types=1);

namespace ProWay\Domain\Client;

interface ClientRepository
{
    public function findByCode(string $code): ?array;
    public function findByEmail(string $email): ?array;
    public function findById(int $id): ?array;
    public function findAllActive(): array;
    public function update(int $id, array $data): bool;
    public function create(array $data): int;
    /** Count active clients grouped by plan_type. */
    public function countByPlan(): array;
    /** Count new clients grouped by month (last N months). */
    public function newByMonth(int $months = 6): array;
}
