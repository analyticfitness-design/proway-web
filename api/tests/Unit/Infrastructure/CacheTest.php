<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use ProWay\Infrastructure\Cache\ApcuCache;
use ProWay\Infrastructure\Cache\NullCache;

class CacheTest extends TestCase
{
    // ── NullCache tests ──────────────────────────────────────────────────────

    public function test_null_cache_get_returns_null(): void
    {
        $cache = new NullCache();
        $this->assertNull($cache->get('any-key'));
    }

    public function test_null_cache_set_and_get(): void
    {
        $cache = new NullCache();
        $cache->set('key', 'value', 60);
        // set is a no-op, so get still returns null
        $this->assertNull($cache->get('key'));
    }

    public function test_null_cache_delete_is_noop(): void
    {
        $cache = new NullCache();
        // Should not throw
        $cache->delete('nonexistent-key');
        $this->assertTrue(true);
    }

    // ── ApcuCache tests ──────────────────────────────────────────────────────

    /**
     * @requires extension apcu
     */
    public function test_apcu_cache_set_and_get(): void
    {
        if (!apcu_enabled()) {
            $this->markTestSkipped('APCu is loaded but not enabled (CLI mode).');
        }

        $cache = new ApcuCache();
        $key   = 'pw:test:' . uniqid('', true);

        $cache->set($key, ['foo' => 'bar'], 60);
        $result = $cache->get($key);

        $this->assertIsArray($result);
        $this->assertSame('bar', $result['foo']);

        // Cleanup
        $cache->delete($key);
    }

    /**
     * @requires extension apcu
     */
    public function test_apcu_cache_get_missing_key_returns_null(): void
    {
        if (!apcu_enabled()) {
            $this->markTestSkipped('APCu is loaded but not enabled (CLI mode).');
        }

        $cache = new ApcuCache();
        $this->assertNull($cache->get('pw:test:nonexistent-' . uniqid('', true)));
    }

    /**
     * @requires extension apcu
     */
    public function test_apcu_cache_delete_removes_entry(): void
    {
        if (!apcu_enabled()) {
            $this->markTestSkipped('APCu is loaded but not enabled (CLI mode).');
        }

        $cache = new ApcuCache();
        $key   = 'pw:test:del:' . uniqid('', true);

        $cache->set($key, 'value', 60);
        $cache->delete($key);

        $this->assertNull($cache->get($key));
    }
}
