<?php
declare(strict_types=1);

namespace App\Services\Cart;

use PDO;
use RuntimeException;
use Throwable;

class CartService
{
    private const SQL_SELECT_CART = '
        SELECT *
        FROM shopping_cart
        WHERE id = :cart_id
        LIMIT 1
    ';

    private const SQL_SELECT_CART_BY_USER_STORE = '
        SELECT *
        FROM shopping_cart
        WHERE user_id = :user_id
          AND store_id = :store_id
        LIMIT 1
    ';

    private const SQL_SELECT_CART_BY_SESSION_STORE = '
        SELECT *
        FROM shopping_cart
        WHERE session_id = :session_id
          AND store_id = :store_id
        LIMIT 1
    ';

    private const SQL_INSERT_CART = '
        INSERT INTO shopping_cart (user_id, session_id, store_id, fulfillment_mode, item_count)
        VALUES (:user_id, :session_id, :store_id, :fulfillment_mode, 0)
    ';

    private const SQL_SELECT_CART_ITEM = '
        SELECT *
        FROM shopping_cart_items
        WHERE cart_id = :cart_id
          AND upc = :upc
        LIMIT 1
    ';

    private const SQL_INSERT_CART_ITEM = '
        INSERT INTO shopping_cart_items (
            cart_id,
            product_id,
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
            :upc,
            :quantity,
            :regular_price,
            :sale_price,
            :national_price,
            :promo_description,
            :raw_json
        )
    ';

    private const SQL_UPDATE_CART_ITEM = '
        UPDATE shopping_cart_items
        SET
            quantity = :quantity,
            updated_at = CURRENT_TIMESTAMP
        WHERE cart_id = :cart_id
          AND upc = :upc
    ';

    private const SQL_DELETE_CART_ITEM = '
        DELETE FROM shopping_cart_items
        WHERE cart_id = :cart_id
          AND upc = :upc
    ';

    private const SQL_DELETE_ALL_CART_ITEMS = '
        DELETE FROM shopping_cart_items
        WHERE cart_id = :cart_id
    ';

    private const SQL_SELECT_CART_ITEMS = '
        SELECT *
        FROM shopping_cart_items
        WHERE cart_id = :cart_id
        ORDER BY created_at ASC, id ASC
    ';

    private const SQL_SELECT_CART_TOTALS = '
        SELECT
            COALESCE(SUM(quantity), 0) AS item_count,
            COALESCE(SUM(quantity * COALESCE(sale_price, regular_price, national_price, 0)), 0) AS subtotal
        FROM shopping_cart_items
        WHERE cart_id = :cart_id
    ';

    private const SQL_UPDATE_CART_TOTALS = '
        UPDATE shopping_cart
        SET
            item_count = :item_count,
            subtotal = :subtotal,
            total = :total,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :cart_id
    ';

    private const SQL_SNAPSHOT_PRICES = '
        UPDATE shopping_cart_items
        SET
            regular_price = :regular_price,
            sale_price = :sale_price,
            national_price = :national_price,
            promo_description = :promo_description,
            raw_json = :raw_json,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :cart_item_id
    ';

    private const SQL_MERGE_CART_ITEMS = '
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
        )
        SELECT
            :target_cart_id AS cart_id,
            s.product_id,
            s.kroger_product_id,
            s.upc,
            s.quantity,
            s.regular_price,
            s.sale_price,
            s.national_price,
            s.promo_description,
            s.raw_json
        FROM shopping_cart_items s
        WHERE s.cart_id = :source_cart_id
        ON DUPLICATE KEY UPDATE
            quantity = shopping_cart_items.quantity + VALUES(quantity),
            regular_price = VALUES(regular_price),
            sale_price = VALUES(sale_price),
            national_price = VALUES(national_price),
            promo_description = VALUES(promo_description),
            raw_json = VALUES(raw_json),
            updated_at = CURRENT_TIMESTAMP
    ';

    private const SQL_DELETE_SOURCE_CART_ITEMS = '
        DELETE FROM shopping_cart_items
        WHERE cart_id = :source_cart_id
    ';

    private const SQL_DELETE_SOURCE_CART = '
        DELETE FROM shopping_cart
        WHERE id = :source_cart_id
    ';

    public function __construct(private PDO $db)
    {
    }

    public function getOrCreateCart($userId, $sessionId, $storeId): array
    {
        $userId = $userId !== null ? (int) $userId : null;
        $sessionId = $sessionId !== null ? trim((string) $sessionId) : null;
        $storeId = (int) $storeId;

        if ($storeId <= 0) {
            throw new RuntimeException('storeId is required.');
        }

        if ($userId !== null && $userId > 0) {
            $stmt = $this->db->prepare(self::SQL_SELECT_CART_BY_USER_STORE);
            $stmt->execute([
                ':user_id' => $userId,
                ':store_id' => $storeId,
            ]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cart) {
                return $cart;
            }
        }

        if ($sessionId !== null && $sessionId !== '') {
            $stmt = $this->db->prepare(self::SQL_SELECT_CART_BY_SESSION_STORE);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':store_id' => $storeId,
            ]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cart) {
                return $cart;
            }
        }

        $stmt = $this->db->prepare(self::SQL_INSERT_CART);
        $stmt->execute([
            ':user_id' => $userId > 0 ? $userId : null,
            ':session_id' => $sessionId !== '' ? $sessionId : null,
            ':store_id' => $storeId,
            ':fulfillment_mode' => 'instore',
        ]);

        return $this->getCartById((int) $this->db->lastInsertId());
    }

    public function addItem($cartId, $productId, $upc, $quantity): array
    {
        $cartId = (int) $cartId;
        $productId = $productId !== null ? (int) $productId : null;
        $upc = trim((string) $upc);
        $quantity = max(1, (int) $quantity);

        if ($cartId <= 0 || $upc === '') {
            throw new RuntimeException('cartId and upc are required.');
        }

        $existing = $this->findCartItem($cartId, $upc);
        if ($existing) {
            $this->updateItem($cartId, $upc, (int) $existing['quantity'] + $quantity);
            return $this->findCartItem($cartId, $upc) ?? [];
        }

        $product = $this->findProductByReference($productId, $upc);
        $stmt = $this->db->prepare(self::SQL_INSERT_CART_ITEM);
        $stmt->execute([
            ':cart_id' => $cartId,
            ':product_id' => $productId > 0 ? $productId : ($product['id'] ?? null),
            ':upc' => $upc,
            ':quantity' => $quantity,
            ':regular_price' => $product['regular_price'] ?? null,
            ':sale_price' => $product['sale_price'] ?? null,
            ':national_price' => $product['national_price'] ?? null,
            ':promo_description' => $product['promo_description'] ?? null,
            ':raw_json' => null,
        ]);

        $this->calculateTotals($cartId);
        return $this->findCartItem($cartId, $upc) ?? [];
    }

    public function updateItem($cartId, $upc, $quantity): ?array
    {
        $cartId = (int) $cartId;
        $upc = trim((string) $upc);
        $quantity = (int) $quantity;

        if ($cartId <= 0 || $upc === '') {
            throw new RuntimeException('cartId and upc are required.');
        }

        if ($quantity <= 0) {
            $this->removeItem($cartId, $upc);
            return null;
        }

        $stmt = $this->db->prepare(self::SQL_UPDATE_CART_ITEM);
        $stmt->execute([
            ':cart_id' => $cartId,
            ':upc' => $upc,
            ':quantity' => max(1, $quantity),
        ]);
        $this->calculateTotals($cartId);

        return $this->findCartItem($cartId, $upc);
    }

    public function removeItem($cartId, $upc): bool
    {
        $cartId = (int) $cartId;
        $upc = trim((string) $upc);

        if ($cartId <= 0 || $upc === '') {
            throw new RuntimeException('cartId and upc are required.');
        }

        $stmt = $this->db->prepare(self::SQL_DELETE_CART_ITEM);
        $stmt->execute([
            ':cart_id' => $cartId,
            ':upc' => $upc,
        ]);

        $this->calculateTotals($cartId);
        return $stmt->rowCount() > 0;
    }

    public function clearCart($cartId): void
    {
        $cartId = (int) $cartId;
        if ($cartId <= 0) {
            throw new RuntimeException('cartId is required.');
        }

        $stmt = $this->db->prepare(self::SQL_DELETE_ALL_CART_ITEMS);
        $stmt->execute([':cart_id' => $cartId]);
        $this->calculateTotals($cartId);
    }

    public function calculateTotals($cartId): array
    {
        $cartId = (int) $cartId;
        if ($cartId <= 0) {
            throw new RuntimeException('cartId is required.');
        }

        $stmt = $this->db->prepare(self::SQL_SELECT_CART_TOTALS);
        $stmt->execute([':cart_id' => $cartId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['item_count' => 0, 'subtotal' => 0];

        $itemCount = (int) ($totals['item_count'] ?? 0);
        $subtotal = (float) ($totals['subtotal'] ?? 0);
        $total = $subtotal;

        $update = $this->db->prepare(self::SQL_UPDATE_CART_TOTALS);
        $update->execute([
            ':cart_id' => $cartId,
            ':item_count' => $itemCount,
            ':subtotal' => $subtotal,
            ':total' => $total,
        ]);

        return [
            'item_count' => $itemCount,
            'subtotal' => $subtotal,
            'total' => $total,
        ];
    }

    public function snapshotPrices($cartItemId, $krogerItemJson): array
    {
        $cartItemId = (int) $cartItemId;
        if ($cartItemId <= 0) {
            throw new RuntimeException('cartItemId is required.');
        }

        $item = is_array($krogerItemJson) ? $krogerItemJson : (json_decode((string) $krogerItemJson, true) ?: []);
        $price = $item['price'] ?? [];
        $national = $item['nationalPrice'] ?? [];

        $stmt = $this->db->prepare(self::SQL_SNAPSHOT_PRICES);
        $stmt->execute([
            ':cart_item_id' => $cartItemId,
            ':regular_price' => $price['regular'] ?? null,
            ':sale_price' => $price['promo'] ?? null,
            ':national_price' => $national['regular'] ?? null,
            ':promo_description' => $this->extractPromoDescription($item),
            ':raw_json' => json_encode($item, JSON_UNESCAPED_SLASHES),
        ]);

        $fetch = $this->db->prepare('SELECT * FROM shopping_cart_items WHERE id = :id LIMIT 1');
        $fetch->execute([':id' => $cartItemId]);
        $row = $fetch->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Cart item not found.');
        }

        $this->calculateTotals((int) $row['cart_id']);
        return $row;
    }

    public function mergeCarts($sourceCartId, $targetCartId): array
    {
        $sourceCartId = (int) $sourceCartId;
        $targetCartId = (int) $targetCartId;

        if ($sourceCartId <= 0 || $targetCartId <= 0 || $sourceCartId === $targetCartId) {
            throw new RuntimeException('sourceCartId and targetCartId must be valid and different.');
        }

        $this->db->beginTransaction();
        try {
            $merge = $this->db->prepare(self::SQL_MERGE_CART_ITEMS);
            $merge->execute([
                ':source_cart_id' => $sourceCartId,
                ':target_cart_id' => $targetCartId,
            ]);

            $deleteItems = $this->db->prepare(self::SQL_DELETE_SOURCE_CART_ITEMS);
            $deleteItems->execute([':source_cart_id' => $sourceCartId]);

            $deleteCart = $this->db->prepare(self::SQL_DELETE_SOURCE_CART);
            $deleteCart->execute([':source_cart_id' => $sourceCartId]);

            $this->calculateTotals($targetCartId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->getCartById($targetCartId);
    }

    public function getCartById(int $cartId): array
    {
        $stmt = $this->db->prepare(self::SQL_SELECT_CART);
        $stmt->execute([':cart_id' => $cartId]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cart) {
            throw new RuntimeException('Cart not found.');
        }
        return $cart;
    }

    public function getCartItems(int $cartId): array
    {
        $stmt = $this->db->prepare(self::SQL_SELECT_CART_ITEMS);
        $stmt->execute([':cart_id' => $cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getSqlQueries(): array
    {
        return [
            'SELECT cart' => self::SQL_SELECT_CART,
            'INSERT cart' => self::SQL_INSERT_CART,
            'UPDATE cart totals' => self::SQL_UPDATE_CART_TOTALS,
            'INSERT/UPDATE cart item' => self::SQL_INSERT_CART_ITEM . "\n\n" . self::SQL_UPDATE_CART_ITEM,
            'DELETE cart item' => self::SQL_DELETE_CART_ITEM,
            'SELECT cart items' => self::SQL_SELECT_CART_ITEMS,
            'MERGE carts' => self::SQL_MERGE_CART_ITEMS,
        ];
    }

    private function findCartItem(int $cartId, string $upc): ?array
    {
        $stmt = $this->db->prepare(self::SQL_SELECT_CART_ITEM);
        $stmt->execute([
            ':cart_id' => $cartId,
            ':upc' => $upc,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findProductByReference(?int $productId, string $upc): ?array
    {
        if ($productId !== null && $productId > 0) {
            $stmt = $this->db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) {
                return $product;
            }
        }

        $stmt = $this->db->prepare('SELECT * FROM products WHERE upc = :upc ORDER BY id DESC LIMIT 1');
        $stmt->execute([':upc' => $upc]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product ?: null;
    }

    private function extractPromoDescription(array $item): ?string
    {
        $promo = $item['price']['promoDescription'] ?? null;
        if (is_string($promo) && trim($promo) !== '') {
            return trim($promo);
        }
        if (is_array($promo)) {
            foreach ($promo as $entry) {
                if (is_string($entry) && trim($entry) !== '') {
                    return trim($entry);
                }
            }
        }
        return null;
    }
}
