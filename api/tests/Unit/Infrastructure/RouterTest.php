<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use ProWay\Infrastructure\Http\Router;
use FastRoute\RouteCollector;

class RouterTest extends TestCase
{
    private function buildRouter(): Router
    {
        return new Router(function (RouteCollector $r) {
            $r->addRoute('GET',  '/api/v1/ping',       ['handler', 'ping']);
            $r->addRoute('POST', '/api/v1/auth/login', ['handler', 'login']);
            $r->addRoute('GET',  '/api/v1/items/{id:\d+}', ['handler', 'show']);
        });
    }

    public function test_found_route_returns_200_with_handler(): void
    {
        $router = $this->buildRouter();
        // Simulate a GET /api/v1/ping
        $result = $this->dispatchRaw($router, 'GET', '/api/v1/ping');

        $this->assertSame(200, $result['status']);
        $this->assertSame(['handler', 'ping'], $result['handler']);
        $this->assertSame([], $result['vars']);
    }

    public function test_found_route_extracts_path_vars(): void
    {
        $router = $this->buildRouter();
        $result = $this->dispatchRaw($router, 'GET', '/api/v1/items/42');

        $this->assertSame(200, $result['status']);
        $this->assertSame(['id' => '42'], $result['vars']);
    }

    public function test_unknown_route_returns_404(): void
    {
        $router = $this->buildRouter();
        $result = $this->dispatchRaw($router, 'GET', '/api/v1/nope');

        $this->assertSame(404, $result['status']);
        $this->assertNull($result['handler']);
    }

    public function test_wrong_method_returns_405_with_allowed(): void
    {
        $router = $this->buildRouter();
        $result = $this->dispatchRaw($router, 'DELETE', '/api/v1/ping');

        $this->assertSame(405, $result['status']);
        $this->assertContains('GET', $result['allowed']);
    }

    /**
     * Dispatch bypassing the Request object (uses internal FastRoute directly
     * so we don't need HTTP globals in unit tests).
     */
    private function dispatchRaw(Router $router, string $method, string $uri): array
    {
        // Access the dispatcher through reflection to keep Router testable
        $ref = new \ReflectionProperty($router, 'dispatcher');
        $ref->setAccessible(true);
        $dispatcher = $ref->getValue($router);

        $info = $dispatcher->dispatch($method, $uri);
        return match ($info[0]) {
            \FastRoute\Dispatcher::NOT_FOUND         => ['status' => 404, 'handler' => null, 'vars' => []],
            \FastRoute\Dispatcher::METHOD_NOT_ALLOWED => ['status' => 405, 'handler' => null, 'vars' => [], 'allowed' => $info[1]],
            \FastRoute\Dispatcher::FOUND              => ['status' => 200, 'handler' => $info[1], 'vars' => $info[2]],
        };
    }
}
