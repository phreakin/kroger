<?php
declare(strict_types=1);

namespace App\Services\Kroger;

final class DiscountHistoryApi
{
    public function __construct(private readonly KrogerApiClient $client)
    {
    }

    public function byUpc(string $upc, string $locationId): array
    {
        return $this->client->request('GET', '/discount-history', [
            'filter.upc' => $upc,
            'filter.locationId' => $locationId,
        ]);
    }
}
