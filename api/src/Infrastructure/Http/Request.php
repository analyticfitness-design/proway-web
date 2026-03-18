<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\Http;

class Request
{
    private array $body;
    private array $query;
    private array $headers;

    public function __construct()
    {
        $raw = file_get_contents('php://input');
        $this->body    = json_decode($raw ?: '{}', true) ?? [];
        $this->query   = $_GET;
        $this->headers = getallheaders() ?: [];
    }

    public function getBody(): array { return $this->body; }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Resolve the access token from the current request.
     * Checks the Authorization header (Bearer) first, then falls back
     * to the httpOnly cookie set by the login endpoint (SEC-001).
     */
    public function resolveAccessToken(): ?string
    {
        $auth = $this->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return $_COOKIE['pw_access'] ?? null;
    }

    /** @deprecated Use resolveAccessToken() */
    public function bearerToken(): ?string
    {
        return $this->resolveAccessToken();
    }

    public function httpMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        return rawurldecode($uri);
    }

    public function isMethod(string $method): bool
    {
        return $this->httpMethod() === strtoupper($method);
    }
}
