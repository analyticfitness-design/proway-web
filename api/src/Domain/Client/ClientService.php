<?php
declare(strict_types=1);

namespace ProWay\Domain\Client;

class ClientService
{
    public function __construct(private readonly ClientRepository $repo) {}

    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function getByCode(string $code): ?array
    {
        return $this->repo->findByCode($code);
    }

    public function getActiveClients(): array
    {
        return $this->repo->findAllActive();
    }

    public function create(array $data): int
    {
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->repo->update($id, $data);
    }

    public function countByPlan(): array
    {
        return $this->repo->countByPlan();
    }

    public function newByMonth(int $months = 6): array
    {
        return $this->repo->newByMonth($months);
    }
}
