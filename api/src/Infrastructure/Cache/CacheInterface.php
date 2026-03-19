<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 300): void;
    public function delete(string $key): void;
}
