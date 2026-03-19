<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\Cache;

class NullCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 300): void
    {
        // no-op
    }

    public function delete(string $key): void
    {
        // no-op
    }
}
