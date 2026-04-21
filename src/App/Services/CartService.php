<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CartRepository;

final class CartService
{
    public function __construct(private readonly CartRepository $cart)
    {
    }

    public function list(int $userId): array
    {
        return $this->cart->listByUser($userId);
    }

    public function add(int $userId, array $payload): int
    {
        return $this->cart->add($userId, $payload);
    }
}
