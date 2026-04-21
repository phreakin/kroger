<?php

class DietaryFilteringService {
    public function __construct(private PDO $db) {}

    public function filterByDietaryNeeds(array $preferences, int $limit = 50): array {
        $sql = "SELECT * FROM products WHERE 1 = 1";
        $params = [];

        if (in_array('snap_eligible', $preferences)) {
            $sql .= " AND snap_eligible = 1";
        }

        if (in_array('organic', $preferences)) {
            $sql .= " AND nutritional_preferences LIKE :organic";
            $params[':organic'] = '%organic%';
        }

        if (in_array('non_gmo', $preferences)) {
            $sql .= " AND nutritional_preferences LIKE :non_gmo";
            $params[':non_gmo'] = '%NON_GMO%';
        }

        if (in_array('gluten_free', $preferences)) {
            $sql .= " AND nutritional_preferences LIKE :gluten_free";
            $params[':gluten_free'] = '%GLUTEN_FREE%';
        }

        if (in_array('vegan', $preferences)) {
            $sql .= " AND nutritional_preferences LIKE :vegan";
            $params[':vegan'] = '%vegan%';
        }

        if (in_array('kosher', $preferences)) {
            $sql .= " AND nutritional_preferences LIKE :kosher";
            $params[':kosher'] = '%Kosher%';
        }

        if (in_array('no_alcohol', $preferences)) {
            $sql .= " AND alcoholic = 0";
        }

        $sql .= " ORDER BY COALESCE(sale_price, regular_price) ASC LIMIT " . max(1, (int) $limit);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function checkAllergens(int $productId, array $allergenRestrictions): array {
        $stmt = $this->db->prepare("SELECT raw_json FROM products WHERE id = :id");
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch() ?: [];

        if (empty($row['raw_json'])) {
            return ['safe' => false, 'reason' => 'No allergen data available'];
        }

        $product = json_decode($row['raw_json'], true);
        $allergens = $product['allergens'] ?? [];
        $containsLevel = 'Free from';
        $foundAllergens = [];

        foreach ($allergens as $allergen) {
            $name = strtolower($allergen['name'] ?? '');
            foreach ($allergenRestrictions as $restricted) {
                if (strpos($name, strtolower($restricted)) !== false) {
                    if ($allergen['levelOfContainmentName'] !== $containsLevel) {
                        $foundAllergens[] = [
                            'allergen' => $allergen['name'],
                            'containment_level' => $allergen['levelOfContainmentName'],
                            'severity' => $allergen['levelOfContainmentName'] === 'Free from' ? 'safe' : 'warning',
                        ];
                    }
                }
            }
        }

        return [
            'safe' => empty($foundAllergens),
            'allergens_found' => $foundAllergens,
            'description' => $product['allergensDescription'] ?? '',
        ];
    }

    public function findSubstitutes(int $productId, array $filters = []): array {
        $product = $this->getProductDetails($productId);
        if (!$product) {
            return [];
        }

        $sql = "
            SELECT 
                p.id,
                p.description,
                p.brand,
                p.image_url,
                p.size,
                p.regular_price,
                p.sale_price,
                ROUND(((p.regular_price - COALESCE(p.sale_price, p.regular_price)) / p.regular_price) * 100, 1) as discount_percent
            FROM products p
            WHERE p.categories LIKE :category
              AND p.id != :product_id
        ";
        $params = [
            ':category' => '%' . trim(explode(',', $product['categories'])[0]) . '%',
            ':product_id' => $productId,
        ];

        if (in_array('cheaper', $filters)) {
            $sql .= " AND COALESCE(p.sale_price, p.regular_price) < " . (float) ($product['sale_price'] ?? $product['regular_price']);
        }

        if (in_array('on_sale', $filters)) {
            $sql .= " AND p.sale_price IS NOT NULL";
        }

        if (in_array('snap_eligible', $filters)) {
            $sql .= " AND p.snap_eligible = 1";
        }

        $sql .= " ORDER BY COALESCE(p.sale_price, p.regular_price) ASC LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function suggestComplementaryItems(int $productId): array {
        $product = $this->getProductDetails($productId);
        if (!$product) {
            return [];
        }

        $complementaryPairs = [
            'bread' => ['butter', 'jam', 'cheese'],
            'milk' => ['cereal', 'cookies', 'bread'],
            'cheese' => ['crackers', 'bread', 'wine'],
            'chicken' => ['rice', 'vegetables', 'sauce'],
            'pasta' => ['sauce', 'olive oil', 'parmesan'],
            'coffee' => ['milk', 'sugar', 'cream'],
            'eggs' => ['milk', 'butter', 'bread'],
        ];

        $keywords = [];
        foreach ($complementaryPairs as $key => $items) {
            if (stripos($product['description'], $key) !== false) {
                $keywords = $items;
                break;
            }
        }

        if (empty($keywords)) {
            return [];
        }

        $suggestions = [];
        foreach ($keywords as $keyword) {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    description,
                    brand,
                    image_url,
                    COALESCE(sale_price, regular_price) as price
                FROM products
                WHERE (description LIKE :keyword OR categories LIKE :keyword)
                  AND id != :product_id
                ORDER BY sale_price IS NOT NULL DESC, COALESCE(sale_price, regular_price) ASC
                LIMIT 2
            ");
            $stmt->execute([
                ':keyword' => '%' . $keyword . '%',
                ':product_id' => $productId,
            ]);
            $suggestions = array_merge($suggestions, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        return array_slice(array_unique($suggestions, SORT_REGULAR), 0, 5);
    }

    private function getProductDetails(int $productId): ?array {
        $stmt = $this->db->prepare("
            SELECT id, description, brand, categories, regular_price, sale_price
            FROM products
            WHERE id = :id
        ");
        $stmt->execute([':id' => $productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
