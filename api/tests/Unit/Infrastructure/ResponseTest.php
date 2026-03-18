<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use ProWay\Infrastructure\Http\Response;

class ResponseTest extends TestCase
{
    public function test_success_structure(): void
    {
        $result = Response::buildSuccess(['id' => 1, 'name' => 'Test']);

        $this->assertTrue($result['success']);
        $this->assertSame(['id' => 1, 'name' => 'Test'], $result['data']);
        $this->assertNull($result['error']);
        $this->assertArrayHasKey('timestamp', $result['meta']);
        $this->assertSame('1.0', $result['meta']['version']);
    }

    public function test_error_structure(): void
    {
        $result = Response::buildError('AUTH_001', 'Credenciales inválidas');

        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertSame('AUTH_001', $result['error']['code']);
        $this->assertSame('Credenciales inválidas', $result['error']['message']);
    }

    public function test_paginated_meta(): void
    {
        $result = Response::buildPaginated([['id' => 1]], 50, 1, 10);

        $this->assertTrue($result['success']);
        $this->assertSame(50, $result['meta']['total']);
        $this->assertSame(1, $result['meta']['page']);
        $this->assertSame(10, $result['meta']['per_page']);
        $this->assertSame(5, $result['meta']['pages']);
    }

    public function test_error_includes_meta_timestamp(): void
    {
        $result = Response::buildError('ERR_500', 'Server error');
        $this->assertArrayHasKey('timestamp', $result['meta']);
        $this->assertSame('1.0', $result['meta']['version']);
    }

    public function test_paginated_rejects_zero_per_page(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Response::buildPaginated([], 10, 1, 0);
    }
}
