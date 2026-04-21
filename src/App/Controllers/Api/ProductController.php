<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Services\CatalogService;

final class ProductController
{
    public function __construct(private readonly CatalogService $catalog)
    {
    }

    public function index(Request $request): Response
    {
        $term = trim((string) ($request->query['q'] ?? ''));
        $locationId = trim((string) ($request->query['locationId'] ?? ''));
        $limit = max(1, min(50, (int) ($request->query['limit'] ?? 24)));

        return Response::json([
            'ok' => true,
            'results' => $this->catalog->searchProducts($term, $locationId, $limit),
        ]);
    }
}
