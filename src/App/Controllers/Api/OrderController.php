<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Services\OrderService;

final class OrderController
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): Response
    {
        $userId = (int) ($request->query['userId'] ?? 1);
        return Response::json(['ok' => true, 'orders' => $this->orders->history($userId)]);
    }
}
