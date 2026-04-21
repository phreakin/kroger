<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Services\Kroger\CatalogV2Api;
use App\Services\Kroger\ProductsApi;

final class CatalogService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductsApi $productsApi,
        private readonly CatalogV2Api $catalogApi,
    ) {
    }

    public function searchProducts(string $term, string $locationId, int $limit): array
    {
        $local = $term === '' ? $this->products->latest($limit) : $this->products->search($term, $limit);
        if ($locationId === '') {
            return $local;
        }

        $remote = $this->productsApi->search($term, $locationId, $limit)['data'] ?? [];
        return ['local' => $local, 'remote' => $remote];
    }

    public function catalogByUpc(string $upc, string $locationId): array
    {
        return $this->catalogApi->byUpc($upc, $locationId);
    }
}
