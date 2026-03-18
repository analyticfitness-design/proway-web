<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testAutoloaderWorks(): void
    {
        $this->assertTrue(true, 'PHPUnit is configured correctly');
    }

    public function testPhpVersion(): void
    {
        $this->assertGreaterThanOrEqual(
            8.2,
            (float) PHP_VERSION,
            'PHP 8.2+ required'
        );
    }
}
