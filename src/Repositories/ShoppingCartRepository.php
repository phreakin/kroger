<?php
class ShoppingCartRepository {
    public function __construct(private PDO $db) {}

    public function getOrCreateCart(?int $userId, ?string $sessionId, int $storeId, string $fulfillmentMode = 'instore'): array {
        $cart = null;
        if ($userId !== null) {
            $cart = $this->findByUserStore($userId, $storeId);
        }

        if ($cart === null && $sessionId !== null && $sessionId !== '') {
            $cart = $this->findBySessionStore($sessionId, $storeId);
        }

        if ($cart !== null) {
            if (($cart['fulfillment_mode'] ?? 'instore') !== $fulfillmentMode) {
                $stmt = $this->db->prepare("UPDATE shopping_cart SET fulfillment_mode = :mode WHERE id = :id");
                $stmt->execute([
                    ':mode' => $this->normalizeFulfillmentMode($fulfillmentMode),
                    ':id' => (int) $cart['id'],
                ]);
                $cart = $this->getCartById((int) $cart['id']);
            }
            return $cart;
        }

        $stmt = $this->db->prepare("
            INSERT INTO shopping_cart (user_id, session_id, store_id, fulfillment_mode, item_count)
            VALUES (:user_id, :session_id, :store_id, :mode, 0)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':session_id' => $sessionId !== '' ? $sessionId : null,
            ':store_id' => $storeId,
            ':mode' => $this->normalizeFulfillmentMode($fulfillmentMode),
        ]);

        return $this->getCartById((int) $this->db->lastInsertId());
    }

    public function getCartById(int $cartId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM shopping_cart WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $cartId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUserStore(int $userId, int $storeId): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM shopping_cart
            WHERE user_id = :user_id
              AND store_id = :store_id
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':store_id' => $storeId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySessionStore(string $sessionId, int $storeId): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM shopping_cart
            WHERE session_id = :session_id
              AND store_id = :store_id
            LIMIT 1
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':store_id' => $storeId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listItems(int $cartId): array {
        $stmt = $this->db->prepare("
            SELECT sci.*, p.description, p.brand, p.size, p.image_url
            FROM shopping_cart_items sci
            LEFT JOIN products p ON p.upc = sci.upc
            WHERE sci.cart_id = :cart_id
            ORDER BY sci.created_at ASC, sci.id ASC
        ");
        $stmt->execute([':cart_id' => $cartId]);
        return $stmt->fetchAll();
    }

    public function upsertItem(int $cartId, array $item): array {
        $upc = trim((string) ($item['upc'] ?? ''));
        if ($upc === '') {
            throw new RuntimeException('UPC is required.');
        }

        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $product = $this->findProductByReference($item);

        $existing = $this->db->prepare("
            SELECT id
            FROM shopping_cart_items
            WHERE cart_id = :cart_id
              AND upc = :upc
            LIMIT 1
        ");
        $existing->execute([
            ':cart_id' => $cartId,
            ':upc' => $upc,
        ]);
        $row = $existing->fetch();

        $payload = [
            ':cart_id' => $cartId,
            ':product_id' => $product['id'] ?? null,
            ':kroger_product_id' => $item['kroger_product_id'] ?? ($product['kroger_product_id'] ?? null),
            ':upc' => $upc,
            ':quantity' => $quantity,
            ':regular_price' => $item['regular_price'] ?? ($product['regular_price'] ?? null),
            ':sale_price' => $item['sale_price'] ?? ($product['sale_price'] ?? null),
            ':national_price' => $item['national_price'] ?? ($product['national_price'] ?? null),
            ':promo_description' => $item['promo_description'] ?? ($product['promo_description'] ?? null),
            ':raw_json' => isset($item['raw_json']) ? (is_string($item['raw_json']) ? $item['raw_json'] : json_encode($item['raw_json'], JSON_UNESCAPED_SLASHES)) : null,
        ];

        if ($row) {
            $stmt = $this->db->prepare("
                UPDATE shopping_cart_items
                SET
                    product_id = :product_id,
                    kroger_product_id = :kroger_product_id,
                    quantity = :quantity,
                    regular_price = :regular_price,
                    sale_price = :sale_price,
                    national_price = :national_price,
                    promo_description = :promo_description,
                    raw_json = :raw_json
                WHERE id = :id
            ");
            $payload[':id'] = (int) $row['id'];
            $stmt->execute($payload);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO shopping_cart_items (
                    cart_id,
                    product_id,
                    kroger_product_id,
                    upc,
                    quantity,
                    regular_price,
                    sale_price,
                    national_price,
                    promo_description,
                    raw_json
                ) VALUES (
                    :cart_id,
                    :product_id,
                    :kroger_product_id,
                    :upc,
                    :quantity,
                    :regular_price,
                    :sale_price,
                    :national_price,
                    :promo_description,
                    :raw_json
                )
            ");
            $stmt->execute($payload);
        }

        $this->recalculateTotals($cartId);
        return $this->getItemByUpc($cartId, $upc);
    }

    public function updateItemQuantity(int $cartId, string $upc, int $quantity): ?array {
        if ($quantity <= 0) {
            $this->removeItem($cartId, $upc);
            return null;
        }

        $stmt = $this->db->prepare("
            UPDATE shopping_cart_items
            SET quantity = :quantity
            WHERE cart_id = :cart_id AND upc = :upc
        ");
        $stmt->execute([
            ':quantity' => max(1, $quantity),
            ':cart_id' => $cartId,
            ':upc' => $upc,
        ]);
        $this->recalculateTotals($cartId);
        return $this->getItemByUpc($cartId, $upc);
    }

    public function getItemByUpc(int $cartId, string $upc): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM shopping_cart_items
            WHERE cart_id = :cart_id
              AND upc = :upc
            LIMIT 1
        ");
        $stmt->execute([
            ':cart_id' => $cartId,
            ':upc' => $upc,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function removeItem(int $cartId, string $upc): bool {
        $stmt = $this->db->prepare("
            DELETE FROM shopping_cart_items
            WHERE cart_id = :cart_id
              AND upc = :upc
        ");
        $stmt->execute([
            ':cart_id' => $cartId,
            ':upc' => $upc,
        ]);
        $removed = $stmt->rowCount() > 0;
        $this->recalculateTotals($cartId);
        return $removed;
    }

    public function recalculateTotals(int $cartId): void {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(quantity), 0) AS item_count,
                COALESCE(SUM(quantity * COALESCE(sale_price, regular_price, national_price, 0)), 0) AS subtotal
            FROM shopping_cart_items
            WHERE cart_id = :cart_id
        ");
        $stmt->execute([':cart_id' => $cartId]);
        $totals = $stmt->fetch() ?: [];

        $itemCount = (int) ($totals['item_count'] ?? 0);
        $subtotal = $totals['subtotal'] !== null ? (float) $totals['subtotal'] : 0.0;
        $total = $subtotal;

        $update = $this->db->prepare("
            UPDATE shopping_cart
            SET
                item_count = :item_count,
                subtotal = :subtotal,
                total = :total
            WHERE id = :id
        ");
        $update->execute([
            ':item_count' => $itemCount,
            ':subtotal' => $subtotal,
            ':total' => $total,
            ':id' => $cartId,
        ]);
    }

    public function updateSyncMetadata(int $cartId, ?string $krogerCartId, ?string $syncedAt = null): void {
        $stmt = $this->db->prepare("
            UPDATE shopping_cart
            SET
                kroger_cart_id = :kroger_cart_id,
                last_synced_at = :last_synced_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':kroger_cart_id' => $krogerCartId,
            ':last_synced_at' => $syncedAt ?: date('Y-m-d H:i:s'),
            ':id' => $cartId,
        ]);
    }

    public function logSync(
        int $cartId,
        string $action,
        ?array $requestPayload,
        ?array $responsePayload,
        ?int $httpStatus
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO shopping_cart_sync_log (cart_id, action, request_json, response_json, http_status)
            VALUES (:cart_id, :action, :request_json, :response_json, :http_status)
        ");
        $stmt->execute([
            ':cart_id' => $cartId,
            ':action' => $action,
            ':request_json' => $requestPayload !== null ? json_encode($requestPayload, JSON_UNESCAPED_SLASHES) : null,
            ':response_json' => $responsePayload !== null ? json_encode($responsePayload, JSON_UNESCAPED_SLASHES) : null,
            ':http_status' => $httpStatus,
        ]);
    }

    public function getRecentSyncLogs(int $cartId, int $limit = 20): array {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare("
            SELECT *
            FROM shopping_cart_sync_log
            WHERE cart_id = :cart_id
            ORDER BY id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([':cart_id' => $cartId]);
        return $stmt->fetchAll();
    }

    public function mergeSessionCartIntoUserCart(string $sessionId, int $userId, int $storeId, string $fulfillmentMode = 'instore'): array {
        $sessionCart = $this->findBySessionStore($sessionId, $storeId);
        $userCart = $this->getOrCreateCart($userId, null, $storeId, $fulfillmentMode);

        if ($sessionCart === null) {
            return $userCart;
        }

        if ((int) $sessionCart['id'] === (int) $userCart['id']) {
            return $userCart;
        }

        $sessionItems = $this->listItems((int) $sessionCart['id']);
        foreach ($sessionItems as $sessionItem) {
            $existing = $this->getItemByUpc((int) $userCart['id'], (string) $sessionItem['upc']);
            if ($existing) {
                $this->updateItemQuantity(
                    (int) $userCart['id'],
                    (string) $sessionItem['upc'],
                    (int) $existing['quantity'] + (int) $sessionItem['quantity']
                );
            } else {
                $this->upsertItem((int) $userCart['id'], [
                    'product_id' => $sessionItem['product_id'],
                    'kroger_product_id' => $sessionItem['kroger_product_id'],
                    'upc' => $sessionItem['upc'],
                    'quantity' => $sessionItem['quantity'],
                    'regular_price' => $sessionItem['regular_price'],
                    'sale_price' => $sessionItem['sale_price'],
                    'national_price' => $sessionItem['national_price'],
                    'promo_description' => $sessionItem['promo_description'],
                    'raw_json' => $sessionItem['raw_json'],
                ]);
            }
        }

        $this->db->beginTransaction();
        try {
            $deleteItems = $this->db->prepare("DELETE FROM shopping_cart_items WHERE cart_id = :cart_id");
            $deleteItems->execute([':cart_id' => (int) $sessionCart['id']]);
            $deleteCart = $this->db->prepare("DELETE FROM shopping_cart WHERE id = :id");
            $deleteCart->execute([':id' => (int) $sessionCart['id']]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $this->recalculateTotals((int) $userCart['id']);
        return $this->getCartById((int) $userCart['id']) ?? $userCart;
    }

    private function findProductByReference(array $item): ?array {
        if (!empty($item['product_id'])) {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int) $item['product_id']]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        if (!empty($item['upc'])) {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE upc = :upc ORDER BY id DESC LIMIT 1");
            $stmt->execute([':upc' => (string) $item['upc']]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        if (!empty($item['kroger_product_id'])) {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE kroger_product_id = :pid ORDER BY id DESC LIMIT 1");
            $stmt->execute([':pid' => (string) $item['kroger_product_id']]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    private function normalizeFulfillmentMode(string $fulfillmentMode): string {
        $mode = strtolower(trim($fulfillmentMode));
        $allowed = ['instore', 'delivery', 'pickup', 'shiptohome'];
        return in_array($mode, $allowed, true) ? $mode : 'instore';
    }
}
