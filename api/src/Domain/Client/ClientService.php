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

    public function update(int $id, array $data): bool
    {
        return $this->repo->update($id, $data);
    }
}
