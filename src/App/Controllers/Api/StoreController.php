<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Services\LocationService;

final class StoreController
{
    public function __construct(private readonly LocationService $locations)
    {
    }

    public function index(Request $request): Response
    {
        $zip = trim((string) ($request->query['zip'] ?? getenv('KROGER_DEFAULT_ZIP') ?: '85281'));

        return Response::json([
            'ok' => true,
            'locations' => $this->locations->search($zip),
        ]);
    }
}
