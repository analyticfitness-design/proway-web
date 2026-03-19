<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\Cache;

class CacheFactory
{
    public static function create(): CacheInterface
    {
        if (extension_loaded('apcu') && apcu_enabled()) {
            return new ApcuCache();
        }

        return new NullCache();
    }
}
