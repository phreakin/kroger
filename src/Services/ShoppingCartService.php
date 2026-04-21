<?php
class ShoppingCartService {
    public function __construct(
        private ShoppingCartRepository $repo,
        private KrogerClient $kroger
    ) {}

    public function getOrCreateCart(
        int $storeId,
        ?int $userId,
        ?string $sessionId,
        string $fulfillmentMode = 'instore'
    ): array {
        $cart = $this->repo->getOrCreateCart($userId, $sessionId, $storeId, $fulfillmentMode);
        return $this->hydrateCart($cart);
    }

    public function getCart(int $cartId): array {
        $cart = $this->repo->getCartById($cartId);
        if (!$cart) {
            throw new RuntimeException('Cart not found.');
        }
        return $this->hydrateCart($cart);
    }

    public function addItem(int $cartId, array $item, ?string $krogerAccessToken = null): array {
        $cart = $this->repo->getCartById($cartId);
        if (!$cart) {
            throw new RuntimeException('Cart not found.');
        }

        $savedItem = $this->repo->upsertItem($cartId, $item);
        if ($krogerAccessToken !== null && $krogerAccessToken !== '') {
            $requestPayload = [
                'items' => [[
                    'upc' => (string) $savedItem['upc'],
                    'quantity' => (int) $savedItem['quantity'],
                    'modality' => (string) ($cart['fulfillment_mode'] ?? 'instore'),
                ]],
            ];

            try {
                $response = $this->kroger->addToCart($requestPayload['items'], $krogerAccessToken, (string) ($cart['fulfillment_mode'] ?? 'instore'));
                $this->repo->logSync($cartId, 'add', $requestPayload, $response, 200);
                $this->repo->updateSyncMetadata($cartId, $this->extractKrogerCartId($response));
            } catch (Throwable $e) {
                $this->repo->logSync($cartId, 'add', $requestPayload, ['error' => $e->getMessage()], $this->extractHttpStatus($e));
            }
        }

        return $this->getCart($cartId);
    }

    public function updateItemQuantity(int $cartId, string $upc, int $quantity, ?string $krogerAccessToken = null): array {
        $cart = $this->repo->getCartById($cartId);
        if (!$cart) {
            throw new RuntimeException('Cart not found.');
        }

        $updatedItem = $this->repo->updateItemQuantity($cartId, $upc, $quantity);

        if ($krogerAccessToken !== null && $krogerAccessToken !== '') {
            $requestPayload = [
                'items' => [[
                    'upc' => $upc,
                    'quantity' => max(0, $quantity),
                    'modality' => (string) ($cart['fulfillment_mode'] ?? 'instore'),
                ]],
            ];

            try {
                if ($quantity > 0) {
                    $response = $this->kroger->updateCartItem($upc, $quantity, $krogerAccessToken, (string) ($cart['fulfillment_mode'] ?? 'instore'));
                    $this->repo->logSync($cartId, 'update', $requestPayload, $response, 200);
                } else {
                    $response = $this->kroger->removeFromCart($requestPayload['items'], $krogerAccessToken);
                    $this->repo->logSync($cartId, 'remove', $requestPayload, $response, 200);
                }
                $this->repo->updateSyncMetadata($cartId, $this->extractKrogerCartId($response));
            } catch (Throwable $e) {
                $this->repo->logSync(
                    $cartId,
                    $quantity > 0 ? 'update' : 'remove',
                    $requestPayload,
                    ['error' => $e->getMessage()],
                    $this->extractHttpStatus($e)
                );
            }
        }

        if ($updatedItem === null && $quantity <= 0) {
            return $this->getCart($cartId);
        }

        return $this->getCart($cartId);
    }

    public function removeItem(int $cartId, string $upc, ?string $krogerAccessToken = null): array {
        $cart = $this->repo->getCartById($cartId);
        if (!$cart) {
            throw new RuntimeException('Cart not found.');
        }

        $this->repo->removeItem($cartId, $upc);
        if ($krogerAccessToken !== null && $krogerAccessToken !== '') {
            $requestPayload = [
                'items' => [[
                    'upc' => $upc,
                    'quantity' => 0,
                ]],
            ];
            try {
                $response = $this->kroger->removeFromCart($requestPayload['items'], $krogerAccessToken);
                $this->repo->logSync($cartId, 'remove', $requestPayload, $response, 200);
                $this->repo->updateSyncMetadata($cartId, $this->extractKrogerCartId($response));
            } catch (Throwable $e) {
                $this->repo->logSync($cartId, 'remove', $requestPayload, ['error' => $e->getMessage()], $this->extractHttpStatus($e));
            }
        }

        return $this->getCart($cartId);
    }

    public function syncCart(int $cartId, ?string $krogerAccessToken): array {
        if ($krogerAccessToken === null || trim($krogerAccessToken) === '') {
            throw new RuntimeException('kroger_access_token is required to sync cart.');
        }

        $cart = $this->repo->getCartById($cartId);
        if (!$cart) {
            throw new RuntimeException('Cart not found.');
        }
        $items = $this->repo->listItems($cartId);

        $requestItems = array_map(static function (array $item) use ($cart): array {
            return [
                'upc' => (string) $item['upc'],
                'quantity' => (int) $item['quantity'],
                'modality' => (string) ($cart['fulfillment_mode'] ?? 'instore'),
            ];
        }, $items);

        $requestPayload = ['items' => $requestItems];
        try {
            $response = $this->kroger->addToCart($requestItems, $krogerAccessToken, (string) ($cart['fulfillment_mode'] ?? 'instore'));
            $this->repo->logSync($cartId, 'sync', $requestPayload, $response, 200);
            $this->repo->updateSyncMetadata($cartId, $this->extractKrogerCartId($response));
        } catch (Throwable $e) {
            $this->repo->logSync($cartId, 'sync', $requestPayload, ['error' => $e->getMessage()], $this->extractHttpStatus($e));
            throw $e;
        }

        return $this->getCart($cartId);
    }

    public function mergeSessionCart(
        string $sessionId,
        int $userId,
        int $storeId,
        ?string $krogerAccessToken = null,
        string $fulfillmentMode = 'instore'
    ): array {
        if (trim($sessionId) === '') {
            throw new RuntimeException('session_id is required.');
        }
        $cart = $this->repo->mergeSessionCartIntoUserCart($sessionId, $userId, $storeId, $fulfillmentMode);
        $this->repo->logSync((int) $cart['id'], 'merge', ['session_id' => $sessionId], ['ok' => true], 200);

        if ($krogerAccessToken !== null && $krogerAccessToken !== '') {
            try {
                return $this->syncCart((int) $cart['id'], $krogerAccessToken);
            } catch (Throwable $e) {
                return $this->getCart((int) $cart['id']);
            }
        }

        return $this->getCart((int) $cart['id']);
    }

    private function hydrateCart(array $cart): array {
        $cartId = (int) ($cart['id'] ?? 0);
        $items = $this->repo->listItems($cartId);
        $logs = $this->repo->getRecentSyncLogs($cartId, 10);
        $freshCart = $this->repo->getCartById($cartId) ?? $cart;

        return [
            'cart' => $freshCart,
            'items' => $items,
            'sync_logs' => $logs,
        ];
    }

    private function extractKrogerCartId(array $response): ?string {
        $value = $response['data']['cartId']
            ?? $response['data']['id']
            ?? $response['cartId']
            ?? $response['id']
            ?? null;
        return $value !== null ? (string) $value : null;
    }

    private function extractHttpStatus(Throwable $e): ?int {
        $code = (int) $e->getCode();
        return $code >= 100 && $code <= 599 ? $code : null;
    }
}
