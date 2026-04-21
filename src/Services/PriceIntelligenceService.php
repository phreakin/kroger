<?php

class PriceIntelligenceService {
    public function __construct(private PDO $db) {}

    public function recordPrice(int $productId, string $locationId, float $regularPrice, ?float $salePrice, string $source = 'api'): void {
        $effectivePrice = $salePrice ?? $regularPrice;
        $stmt = $this->db->prepare("
            INSERT INTO product_price_history (product_id, kroger_location_id, regular_price, sale_price, effective_price, captured_on, source)
            VALUES (:product_id, :location_id, :regular_price, :sale_price, :effective_price, CURDATE(), :source)
            ON DUPLICATE KEY UPDATE
                regular_price = VALUES(regular_price),
                sale_price = VALUES(sale_price),
                effective_price = VALUES(effective_price),
                captured_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':product_id' => $productId,
            ':location_id' => $locationId,
            ':regular_price' => $regularPrice,
            ':sale_price' => $salePrice,
            ':effective_price' => $effectivePrice,
            ':source' => $source,
        ]);
    }

    public function getPriceHistory(int $productId, string $locationId, int $days = 90): array {
        $stmt = $this->db->prepare("
            SELECT captured_on, regular_price, sale_price, effective_price
            FROM product_price_history
            WHERE product_id = :product_id
              AND kroger_location_id = :location_id
              AND captured_on >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ORDER BY captured_on ASC
        ");
        $stmt->execute([
            ':product_id' => $productId,
            ':location_id' => $locationId,
            ':days' => $days,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function detectSalePattern(int $productId, string $locationId): array {
        $history = $this->getPriceHistory($productId, $locationId, 180);
        if (count($history) < 3) {
            return ['pattern' => 'insufficient_data', 'confidence' => 0];
        }

        $minPrice = min(array_column($history, 'effective_price'));
        $maxPrice = max(array_column($history, 'effective_price'));
        $avgPrice = array_sum(array_column($history, 'effective_price')) / count($history);
        
        $saleOccurrences = count(array_filter($history, fn($h) => $h['sale_price'] !== null));
        $saleFrequency = $saleOccurrences / count($history);

        $pattern = 'regular';
        $confidence = 0;

        if ($saleFrequency > 0.5) {
            $pattern = 'frequently_on_sale';
            $confidence = min(100, (int) ($saleFrequency * 100));
        } elseif ($saleFrequency > 0.2) {
            $pattern = 'occasionally_on_sale';
            $confidence = min(100, (int) ($saleFrequency * 100));
        }

        return [
            'pattern' => $pattern,
            'confidence' => $confidence,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'avg_price' => round($avgPrice, 2),
            'current_price' => $history[count($history) - 1]['effective_price'] ?? null,
            'is_sale' => end($history)['sale_price'] !== null,
            'discount_percent' => end($history)['sale_price'] ? round(((end($history)['regular_price'] - end($history)['sale_price']) / end($history)['regular_price']) * 100, 1) : 0,
        ];
    }

    public function findBestBuyingTime(int $productId, string $locationId): array {
        $stmt = $this->db->prepare("
            SELECT 
                DAYNAME(captured_on) as day_of_week,
                AVG(effective_price) as avg_price,
                MIN(effective_price) as min_price,
                MAX(effective_price) as max_price,
                COUNT(*) as occurrences
            FROM product_price_history
            WHERE product_id = :product_id
              AND kroger_location_id = :location_id
              AND captured_on >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY DAYNAME(captured_on)
            ORDER BY avg_price ASC
        ");
        $stmt->execute([':product_id' => $productId, ':location_id' => $locationId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($results)) {
            return ['recommendation' => 'insufficient_data'];
        }

        return [
            'best_day' => $results[0]['day_of_week'] ?? null,
            'best_price' => $results[0]['min_price'] ?? null,
            'worst_day' => end($results)['day_of_week'] ?? null,
            'worst_price' => end($results)['max_price'] ?? null,
            'price_variance' => round(((end($results)['max_price'] - $results[0]['min_price']) / $results[0]['min_price']) * 100, 2),
            'all_days' => $results,
        ];
    }

    public function comparePricesAcrossStores(int $productId, array $locationIds): array {
        $placeholders = implode(',', array_fill(0, count($locationIds), '?'));
        $stmt = $this->db->prepare("
            SELECT 
                s.id as store_id,
                s.name as store_name,
                s.city,
                s.postal_code,
                s.latitude,
                s.longitude,
                pph.regular_price,
                pph.sale_price,
                pph.effective_price,
                pph.captured_on
            FROM product_price_history pph
            JOIN stores s ON s.kroger_location_id = pph.kroger_location_id
            WHERE pph.product_id = :product_id
              AND pph.kroger_location_id IN ($placeholders)
              AND pph.captured_on = (
                SELECT MAX(captured_on) FROM product_price_history
                WHERE product_id = :product_id AND kroger_location_id = pph.kroger_location_id
              )
            ORDER BY pph.effective_price ASC
        ");
        
        $params = array_merge([$productId], $locationIds);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDealsNearExpiration(string $locationId, int $daysUntilExpiry = 7): array {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                p.description,
                p.brand,
                p.image_url,
                pph.regular_price,
                pph.sale_price,
                pph.effective_price,
                ROUND(((pph.regular_price - pph.sale_price) / pph.regular_price) * 100, 1) as discount_percent,
                DATEDIFF(pph.captured_on, CURDATE()) as days_remaining
            FROM product_price_history pph
            JOIN products p ON p.id = pph.product_id
            WHERE pph.kroger_location_id = :location_id
              AND pph.sale_price IS NOT NULL
              AND pph.captured_on <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
              AND pph.captured_on >= CURDATE()
            ORDER BY pph.captured_on ASC, discount_percent DESC
            LIMIT 50
        ");
        $stmt->execute([
            ':location_id' => $locationId,
            ':days' => $daysUntilExpiry,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
