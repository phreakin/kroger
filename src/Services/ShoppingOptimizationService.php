<?php

class ShoppingOptimizationService {
    public function __construct(private PDO $db) {}

    public function groupCartByAisle(int $userId, string $locationId): array {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                p.description,
                p.brand,
                p.image_url,
                p.size,
                p.aisle_locations,
                gli.quantity,
                gli.is_checked,
                COALESCE(p.sale_price, p.regular_price) as price
            FROM grocery_list_items gli
            JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :user_id
              AND gli.is_checked = 0
            ORDER BY p.aisle_locations, p.description
        ");
        $stmt->execute([':user_id' => $userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($items as $item) {
            $aisle = trim($item['aisle_locations'] ?? 'Unknown Aisle');
            if (!isset($grouped[$aisle])) {
                $grouped[$aisle] = [];
            }
            $grouped[$aisle][] = $item;
        }

        return $grouped;
    }

    public function suggestAlternatives(int $productId, int $maxPrice = null): array {
        $stmt = $this->db->prepare("
            SELECT p1.id as product_id,
                   p2.id as alternative_id,
                   p2.description,
                   p2.brand,
                   p2.price,
                   p2.regular_price,
                   p2.sale_price,
                   p2.image_url
            FROM products p1
            JOIN products p2 ON (
                p2.categories = p1.categories OR 
                SUBSTRING_INDEX(p2.categories, ',', 1) = SUBSTRING_INDEX(p1.categories, ',', 1)
            )
            WHERE p1.id = :product_id
              AND p2.id != :product_id
              AND p2.regular_price IS NOT NULL
            ORDER BY p2.sale_price IS NOT NULL DESC, 
                     COALESCE(p2.sale_price, p2.regular_price) ASC
            LIMIT 5
        ");
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function detectDuplicates(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT 
                p.description,
                p.brand,
                COUNT(*) as count,
                SUM(gli.quantity) as total_qty,
                GROUP_CONCAT(gli.id) as item_ids,
                COALESCE(p.sale_price, p.regular_price) as price
            FROM grocery_list_items gli
            JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :user_id
              AND gli.is_checked = 0
            GROUP BY p.id
            HAVING count > 1
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function estimateCartTotal(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as item_count,
                SUM(gli.quantity) as total_qty,
                SUM(gli.quantity * COALESCE(p.sale_price, p.regular_price, 0)) as subtotal,
                COUNT(CASE WHEN p.sale_price IS NOT NULL THEN 1 END) as items_on_sale,
                SUM(CASE WHEN p.sale_price IS NOT NULL THEN (p.regular_price - p.sale_price) * gli.quantity ELSE 0 END) as total_savings
            FROM grocery_list_items gli
            LEFT JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :user_id
              AND gli.is_checked = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'item_count' => (int) ($result['item_count'] ?? 0),
            'total_qty' => (int) ($result['total_qty'] ?? 0),
            'subtotal' => (float) ($result['subtotal'] ?? 0),
            'items_on_sale' => (int) ($result['items_on_sale'] ?? 0),
            'total_savings' => round((float) ($result['total_savings'] ?? 0), 2),
            'average_per_item' => $result['item_count'] ? round((float) ($result['subtotal'] ?? 0) / (int) $result['item_count'], 2) : 0,
        ];
    }

    public function warnAboutExpiringDeals(int $userId, string $locationId): array {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                p.description,
                p.brand,
                p.image_url,
                pph.sale_price,
                pph.regular_price,
                ROUND(((pph.regular_price - pph.sale_price) / pph.regular_price) * 100, 1) as discount_percent,
                DATEDIFF(pph.captured_on, CURDATE()) as days_until_expiry,
                gli.id as list_item_id,
                gli.quantity
            FROM product_price_history pph
            JOIN products p ON p.id = pph.product_id
            LEFT JOIN grocery_list_items gli ON gli.product_id = p.id AND gli.user_id = :user_id
            WHERE pph.kroger_location_id = :location_id
              AND pph.sale_price IS NOT NULL
              AND pph.captured_on <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              AND pph.captured_on >= CURDATE()
            ORDER BY pph.captured_on ASC, discount_percent DESC
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':location_id' => $locationId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
