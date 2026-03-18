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
}
