<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

class Router
{
    private Dispatcher $dispatcher;

    public function __construct(callable $routes)
    {
        $this->dispatcher = simpleDispatcher($routes);
    }

    /**
     * Dispatch the incoming request and return routing info.
     *
     * Returns an array with:
     *   ['status' => 200, 'handler' => [ControllerClass, 'method'], 'vars' => ['id' => '5']]
     *   ['status' => 404, 'handler' => null, 'vars' => []]
     *   ['status' => 405, 'handler' => null, 'vars' => [], 'allowed' => ['GET', 'POST']]
     */
    public function dispatch(Request $request): array
    {
        $routeInfo = $this->dispatcher->dispatch($request->httpMethod(), $request->uri());

        return match ($routeInfo[0]) {
            Dispatcher::NOT_FOUND      => ['status' => 404, 'handler' => null, 'vars' => []],
            Dispatcher::METHOD_NOT_ALLOWED => [
                'status'  => 405,
                'handler' => null,
                'vars'    => [],
                'allowed' => $routeInfo[1],
            ],
            Dispatcher::FOUND => [
                'status'  => 200,
                'handler' => $routeInfo[1],
                'vars'    => $routeInfo[2],
            ],
        };
    }
}
