<?php
declare(strict_types=1);

namespace App\Services\Kroger;

final class ProductsApi
{
    public function __construct(private readonly KrogerApiClient $client)
    {
    }

    public function search(string $term, string $locationId, int $limit): array
    {
        return $this->client->request('GET', '/products', [
            'filter.term' => $term,
            'filter.locationId' => $locationId,
            'filter.limit' => $limit,
        ]);
    }
}
