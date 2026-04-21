<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;

final class OrderService
{
    public function __construct(private readonly OrderRepository $orders)
    {
    }

    public function history(int $userId): array
    {
        return $this->orders->forUser($userId);
    }
}
