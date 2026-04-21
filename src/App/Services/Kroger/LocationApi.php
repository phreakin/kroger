<?php
declare(strict_types=1);

namespace App\Services\Kroger;

final class LocationApi
{
    public function __construct(private readonly KrogerApiClient $client)
    {
    }

    public function search(string $zip, int $limit = 10): array
    {
        return $this->client->request('GET', '/locations', [
            'filter.zipCode.near' => $zip,
            'filter.limit' => $limit,
        ]);
    }
}
