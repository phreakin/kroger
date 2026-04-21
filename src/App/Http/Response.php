<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $content,
        public readonly array $headers = []
    ) {
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self($status, (string) json_encode($data, JSON_UNESCAPED_SLASHES), [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($status, $content, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->content;
    }
}
