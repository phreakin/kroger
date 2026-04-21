<?php
declare(strict_types=1);

namespace App\Services\Kroger;

final class OrdersApi
{
    public function __construct(private readonly KrogerApiClient $client)
    {
    }

    public function list(int $limit = 20): array
    {
        return $this->client->request('GET', '/orders', ['filter.limit' => $limit]);
    }
}
