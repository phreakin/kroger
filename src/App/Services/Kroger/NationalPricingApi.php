<?php
declare(strict_types=1);

namespace App\Services\Kroger;

final class NationalPricingApi
{
    public function __construct(private readonly KrogerApiClient $client)
    {
    }

    public function byUpc(string $upc): array
    {
        return $this->client->request('GET', '/national-pricing', [
            'filter.upc' => $upc,
        ]);
    }
}
