<?php

class InventoryAlertService {
    public function __construct(private PDO $db) {}

    public function trackInventoryChanges(int $productId, string $locationId, string $newLevel, string $oldLevel = null): void {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_history (product_id, location_id, previous_level, current_level)
            VALUES (:product_id, :location_id, :previous_level, :current_level)
        ");
        $stmt->execute([
            ':product_id' => $productId,
            ':location_id' => $locationId,
            ':previous_level' => $oldLevel,
            ':current_level' => $newLevel,
        ]);
    }

    public function getInventoryStatus(int $productId, array $locationIds): array {
        $placeholders = implode(',', array_fill(0, count($locationIds), '?'));
        $stmt = $this->db->prepare("
            SELECT 
                s.id as store_id,
                s.name as store_name,
                s.city,
                p.inventory_level,
                p.id as product_id
            FROM products p
            JOIN stores s ON s.id = ?
            WHERE p.id = :product_id
        ");
        
        $status = [];
        foreach ($locationIds as $locationId) {
            $stmt = $this->db->prepare("
                SELECT 
                    s.id as store_id,
                    s.name as store_name,
                    s.city,
                    s.postal_code,
                    p.inventory_level,
                    p.fulfillment_delivery,
                    p.fulfillment_curbside,
                    p.fulfillment_instore
                FROM products p, stores s
                WHERE p.id = :product_id
                  AND s.kroger_location_id = :location_id
                LIMIT 1
            ");
            $stmt->execute([
                ':product_id' => $productId,
                ':location_id' => $locationId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $status[] = $row;
            }
        }
        return $status;
    }

    public function createBackInStockAlert(int $userId, int $productId, string $locationId, float $targetPrice = null): void {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_alerts (user_id, product_id, location_id, target_price, alert_type)
            VALUES (:user_id, :product_id, :location_id, :target_price, :alert_type)
            ON DUPLICATE KEY UPDATE
                is_active = 1,
                created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':product_id' => $productId,
            ':location_id' => $locationId,
            ':target_price' => $targetPrice,
            ':alert_type' => 'back_in_stock',
        ]);
    }

    public function createPriceDropAlert(int $userId, int $productId, string $locationId, float $targetPrice): void {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_alerts (user_id, product_id, location_id, target_price, alert_type)
            VALUES (:user_id, :product_id, :location_id, :target_price, :alert_type)
            ON DUPLICATE KEY UPDATE
                target_price = VALUES(target_price),
                is_active = 1,
                created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':product_id' => $productId,
            ':location_id' => $locationId,
            ':target_price' => $targetPrice,
            ':alert_type' => 'price_drop',
        ]);
    }

    public function checkAlertsTriggered(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT 
                ia.id as alert_id,
                ia.alert_type,
                p.description,
                p.brand,
                p.image_url,
                p.inventory_level,
                COALESCE(p.sale_price, p.regular_price) as current_price,
                ia.target_price,
                s.name as store_name,
                s.city
            FROM inventory_alerts ia
            JOIN products p ON p.id = ia.product_id
            JOIN stores s ON s.kroger_location_id = ia.location_id
            WHERE ia.user_id = :user_id
              AND ia.is_active = 1
              AND (
                (ia.alert_type = 'back_in_stock' AND p.inventory_level != 'TEMPORARILY_OUT_OF_STOCK')
                OR (ia.alert_type = 'price_drop' AND COALESCE(p.sale_price, p.regular_price) <= ia.target_price)
              )
        ");
        $stmt->execute([':user_id' => $userId]);
        $triggered = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!empty($triggered)) {
            $stmt = $this->db->prepare("UPDATE inventory_alerts SET is_active = 0, notified_at = CURRENT_TIMESTAMP WHERE id IN (" . implode(',', array_column($triggered, 'alert_id')) . ")");
            $stmt->execute();
        }

        return $triggered;
    }

    public function getSuggestedRefills(int $userId, string $locationId): array {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                p.description,
                p.brand,
                p.image_url,
                p.size,
                COALESCE(p.sale_price, p.regular_price) as price,
                p.inventory_level,
                COUNT(gli.id) as purchase_frequency
            FROM usual_items ui
            JOIN products p ON p.id = ui.product_id
            JOIN grocery_list_items gli ON gli.product_id = p.id AND gli.user_id = :user_id
            WHERE ui.user_id = :user_id
              AND p.inventory_level != 'TEMPORARILY_OUT_OF_STOCK'
            GROUP BY p.id
            ORDER BY purchase_frequency DESC, p.sale_price IS NOT NULL DESC
            LIMIT 10
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createInventoryHistoryTable(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS inventory_history (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                location_id VARCHAR(64) NOT NULL,
                previous_level VARCHAR(64) NULL,
                current_level VARCHAR(64) NOT NULL,
                changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_product_location (product_id, location_id),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function createInventoryAlertsTable(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS inventory_alerts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                location_id VARCHAR(64) NOT NULL,
                alert_type ENUM('back_in_stock', 'price_drop') DEFAULT 'back_in_stock',
                target_price DECIMAL(10,2) NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                notified_at DATETIME NULL,
                UNIQUE KEY uniq_alert (user_id, product_id, location_id, alert_type),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                KEY idx_user_active (user_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
