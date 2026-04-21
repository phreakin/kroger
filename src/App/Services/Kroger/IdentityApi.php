<?php
declare(strict_types=1);

namespace App\Services\Kroger;

final class IdentityApi
{
    public function __construct(private readonly KrogerApiClient $client)
    {
    }

    public function profile(): array
    {
        return $this->client->request('GET', '/identity/profile');
    }
}
