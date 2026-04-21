<?php
declare(strict_types=1);

namespace App\Services\Kroger;

final class CartApi
{
    public function __construct(private readonly KrogerApiClient $client)
    {
    }

    public function get(): array
    {
        return $this->client->request('GET', '/cart');
    }

    public function add(array $items): array
    {
        return $this->client->request('PUT', '/cart/add', [], ['items' => $items]);
    }
}
