<?php
declare(strict_types=1);

namespace App\Services\Cart;

use PDO;
use RuntimeException;

class CartSyncService
{
    public function __construct(
        private PDO $db,
        private CartService $cartService,
        private KrogerCartService $krogerCartService
    ) {
    }

    public function syncLocalToKroger($cartId): array
    {
        $cartId = (int) $cartId;
        if ($cartId <= 0) {
            throw new RuntimeException('cartId is required.');
        }

        $cart = $this->cartService->getCartById($cartId);
        $response = $this->krogerCartService->pushFullSync($cart);
        $this->cartService->calculateTotals($cartId);

        return [
            'direction' => 'local_to_kroger',
            'cart_id' => $cartId,
            'response' => $response,
        ];
    }

    public function syncKrogerToLocal($cartId): array
    {
        $cartId = (int) $cartId;
        if ($cartId <= 0) {
            throw new RuntimeException('cartId is required.');
        }

        $cart = $this->cartService->getCartById($cartId);
        $this->krogerCartService->createKrogerCartIfMissing($cart);
        $this->cartService->calculateTotals($cartId);

        return [
            'direction' => 'kroger_to_local',
            'cart_id' => $cartId,
            'cart' => $this->cartService->getCartById($cartId),
            'items' => $this->cartService->getCartItems($cartId),
        ];
    }

    public function syncOnLogin($anonymousCartId, $userCartId): array
    {
        $anonymousCartId = (int) $anonymousCartId;
        $userCartId = (int) $userCartId;
        if ($anonymousCartId <= 0 || $userCartId <= 0) {
            throw new RuntimeException('anonymousCartId and userCartId are required.');
        }

        $merged = $this->cartService->mergeCarts($anonymousCartId, $userCartId);
        $syncResult = $this->syncLocalToKroger($userCartId);

        $stmt = $this->db->prepare('
            INSERT INTO shopping_cart_sync_log (cart_id, action, request_json, response_json, http_status)
            VALUES (:cart_id, :action, :request_json, :response_json, :http_status)
        ');
        $stmt->execute([
            ':cart_id' => $userCartId,
            ':action' => 'merge',
            ':request_json' => json_encode(['anonymous_cart_id' => $anonymousCartId, 'user_cart_id' => $userCartId], JSON_UNESCAPED_SLASHES),
            ':response_json' => json_encode(['merged' => $merged], JSON_UNESCAPED_SLASHES),
            ':http_status' => 200,
        ]);

        return [
            'merged_cart' => $merged,
            'sync' => $syncResult,
        ];
    }
}
