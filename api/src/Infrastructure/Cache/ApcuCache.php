<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\Cache;

class ApcuCache implements CacheInterface
{
    private function isAvailable(): bool
    {
        return extension_loaded('apcu') && apcu_enabled();
    }

    public function get(string $key): mixed
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $success = false;
        $value   = apcu_fetch($key, $success);

        return $success ? $value : null;
    }

    public function set(string $key, mixed $value, int $ttl = 300): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        apcu_store($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        apcu_delete($key);
    }
}
