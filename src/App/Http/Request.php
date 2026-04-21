<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $server,
        public readonly array $headers,
    ) {
    }

    public static function capture(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $payload = file_get_contents('php://input') ?: '';
        $json = json_decode($payload, true);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            is_array($json) ? $json : $_POST,
            $_SERVER,
            function_exists('getallheaders') ? (getallheaders() ?: []) : []
        );
    }

    public function expectsJson(): bool
    {
        $accept = (string) ($this->headers['Accept'] ?? '');
        return str_contains($accept, 'application/json') || str_starts_with($this->path, '/api/');
    }
}
