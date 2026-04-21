<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\StoreRepository;
use App\Services\Kroger\LocationApi;

final class LocationService
{
    public function __construct(
        private readonly StoreRepository $stores,
        private readonly LocationApi $locationApi,
    ) {
    }

    public function search(string $zip): array
    {
        return [
            'local' => $this->stores->latest(12),
            'remote' => $this->locationApi->search($zip)['data'] ?? [],
        ];
    }
}
