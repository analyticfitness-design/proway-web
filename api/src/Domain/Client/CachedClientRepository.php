<?php
declare(strict_types=1);

namespace ProWay\Domain\Client;

use ProWay\Infrastructure\Cache\CacheInterface;

class CachedClientRepository implements ClientRepository
{
    public function __construct(
        private readonly ClientRepository $inner,
        private readonly CacheInterface $cache
    ) {}

    public function findById(int $id): ?array
    {
        $key    = "pw:clients:{$id}";
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->inner->findById($id);

        if ($data !== null) {
            $this->cache->set($key, $data, 600);
        }

        return $data;
    }

    public function findByEmail(string $email): ?array
    {
        $key    = "pw:clients:email:{$email}";
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->inner->findByEmail($email);

        if ($data !== null) {
            $this->cache->set($key, $data, 600);
        }

        return $data;
    }

    public function findByCode(string $code): ?array
    {
        $key    = "pw:clients:code:{$code}";
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->inner->findByCode($code);

        if ($data !== null) {
            $this->cache->set($key, $data, 600);
        }

        return $data;
    }

    public function findAllActive(): array
    {
        $key    = 'pw:clients:active';
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->inner->findAllActive();
        $this->cache->set($key, $data, 300);

        return $data;
    }

    public function update(int $id, array $data): bool
    {
        $result = $this->inner->update($id, $data);

        if ($result) {
            $this->cache->delete("pw:clients:{$id}");
            $this->cache->delete('pw:clients:active');
        }

        return $result;
    }
}
