<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Cart\CartService;
use App\Services\Cart\CartSyncService;
use App\Services\Cart\KrogerCartService;
use RuntimeException;
use Throwable;

class CartController
{
    public function __construct(
        private CartService $cartService,
        private KrogerCartService $krogerCartService,
        private CartSyncService $cartSyncService
    ) {
    }

    public function handle(string $method, string $path, array $body = [], array $query = []): array
    {
        try {
            return match ([$method, $path]) {
                ['POST', '/cart/add'] => $this->add($body),
                ['POST', '/cart/update'] => $this->update($body),
                ['POST', '/cart/remove'] => $this->remove($body),
                ['GET', '/cart'] => $this->get($query),
                ['POST', '/cart/merge'] => $this->merge($body),
                ['POST', '/cart/sync'] => $this->sync($body),
                default => $this->json(404, ['ok' => false, 'error' => 'Route not found']),
            };
        } catch (Throwable $e) {
            return $this->json(400, [
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function add(array $payload): array
    {
        $cartId = (int) ($payload['cart_id'] ?? 0);
        $productId = isset($payload['product_id']) ? (int) $payload['product_id'] : null;
        $upc = trim((string) ($payload['upc'] ?? ''));
        $quantity = (int) ($payload['quantity'] ?? 1);
        $cart = $this->requireCart($cartId);
        if ($upc === '') {
            throw new RuntimeException('upc is required.');
        }

        $item = $this->cartService->addItem($cartId, $productId, $upc, $quantity);
        $this->krogerCartService->pushAddItem($cart, $upc, max(1, $quantity));

        return $this->json(200, [
            'ok' => true,
            'item' => $item,
            'cart' => $this->cartService->getCartById($cartId),
            'items' => $this->cartService->getCartItems($cartId),
        ]);
    }

    public function update(array $payload): array
    {
        $cartId = (int) ($payload['cart_id'] ?? 0);
        $upc = trim((string) ($payload['upc'] ?? ''));
        $quantity = (int) ($payload['quantity'] ?? 1);
        $cart = $this->requireCart($cartId);
        if ($upc === '') {
            throw new RuntimeException('upc is required.');
        }

        $item = $this->cartService->updateItem($cartId, $upc, $quantity);
        if ($quantity <= 0) {
            $this->krogerCartService->pushRemoveItem($cart, $upc);
        } else {
            $this->krogerCartService->pushAddItem($cart, $upc, $quantity);
        }

        return $this->json(200, [
            'ok' => true,
            'item' => $item,
            'cart' => $this->cartService->getCartById($cartId),
            'items' => $this->cartService->getCartItems($cartId),
        ]);
    }

    public function remove(array $payload): array
    {
        $cartId = (int) ($payload['cart_id'] ?? 0);
        $upc = trim((string) ($payload['upc'] ?? ''));
        $cart = $this->requireCart($cartId);
        if ($upc === '') {
            throw new RuntimeException('upc is required.');
        }

        $removed = $this->cartService->removeItem($cartId, $upc);
        if ($removed) {
            $this->krogerCartService->pushRemoveItem($cart, $upc);
        }

        return $this->json(200, [
            'ok' => true,
            'removed' => $removed,
            'cart' => $this->cartService->getCartById($cartId),
            'items' => $this->cartService->getCartItems($cartId),
        ]);
    }

    public function get(array $query): array
    {
        $cartId = (int) ($query['cart_id'] ?? 0);
        $this->requireCart($cartId);

        return $this->json(200, [
            'ok' => true,
            'cart' => $this->cartService->getCartById($cartId),
            'items' => $this->cartService->getCartItems($cartId),
        ]);
    }

    public function merge(array $payload): array
    {
        $sourceCartId = (int) ($payload['source_cart_id'] ?? 0);
        $targetCartId = (int) ($payload['target_cart_id'] ?? 0);
        if ($sourceCartId <= 0 || $targetCartId <= 0) {
            throw new RuntimeException('source_cart_id and target_cart_id are required.');
        }

        $result = $this->cartSyncService->syncOnLogin($sourceCartId, $targetCartId);
        return $this->json(200, [
            'ok' => true,
            'result' => $result,
        ]);
    }

    public function sync(array $payload): array
    {
        $cartId = (int) ($payload['cart_id'] ?? 0);
        $direction = strtolower(trim((string) ($payload['direction'] ?? 'local_to_kroger')));
        $this->requireCart($cartId);

        if ($direction === 'kroger_to_local') {
            $result = $this->cartSyncService->syncKrogerToLocal($cartId);
        } else {
            $result = $this->cartSyncService->syncLocalToKroger($cartId);
        }

        return $this->json(200, [
            'ok' => true,
            'result' => $result,
        ]);
    }

    private function requireCart(int $cartId): array
    {
        if ($cartId <= 0) {
            throw new RuntimeException('cart_id is required.');
        }
        return $this->cartService->getCartById($cartId);
    }

    private function json(int $status, array $payload): array
    {
        return [
            'status' => $status,
            'body' => $payload,
        ];
    }
}
