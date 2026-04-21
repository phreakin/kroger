<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Services\CartService;

final class CartController
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function index(Request $request): Response
    {
        $userId = (int) ($request->query['userId'] ?? 1);
        return Response::json(['ok' => true, 'items' => $this->cartService->list($userId)]);
    }

    public function add(Request $request): Response
    {
        $id = $this->cartService->add((int) ($request->body['user_id'] ?? 1), $request->body);
        return Response::json(['ok' => true, 'id' => $id], 201);
    }
}
