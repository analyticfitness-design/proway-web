<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\Http;

class Response
{
    // Pure builder (no side effects) — used by tests and by success()/error()/paginated()
    public static function buildSuccess(mixed $data): array
    {
        return [
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'version'   => '1.0',
            ],
            'error'   => null,
        ];
    }

    public static function buildError(string $code, string $message): array
    {
        return [
            'success' => false,
            'data'    => null,
            'meta'    => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'version'   => '1.0',
            ],
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
    }

    public static function buildPaginated(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'version'   => '1.0',
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'pages'     => (int) ceil($total / $perPage),
            ],
            'error'   => null,
        ];
    }

    // HTTP output methods (call build* + emit)
    public static function success(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::buildSuccess($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $code, string $message, int $status = 400): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::buildError($code, $message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): never
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::buildPaginated($items, $total, $page, $perPage), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
