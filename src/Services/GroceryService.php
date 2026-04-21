<?php
class GroceryService {
    public function __construct(
        private PDO $db,
        private KrogerClient $kroger
    ) {}

    public function searchProducts(string $term, string $locationId): array {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        if (!preg_match('/^[A-Za-z0-9]{8}$/', $locationId)) {
            throw new RuntimeException("Invalid Kroger location ID. Use the store lookup to pick a valid 8-character store ID.");
        }

        $response = $this->kroger->searchProducts($term, $locationId, 24);
        $products = $response['data'] ?? [];

        $results = [];
        foreach ($products as $product) {
            $normalized = $this->normalizeProduct($product);
            $normalized['db_id'] = $this->upsertProduct($normalized);
            $this->recordPriceHistorySnapshot($normalized['db_id'], $locationId, $normalized, 'search');
            $results[] = $normalized;
        }

        return $results;
    }

    public function searchLocations(string $zipCode): array {
        $zipCode = trim($zipCode);
        if ($zipCode === '') {
            throw new RuntimeException('Enter a ZIP code to find Kroger stores.');
        }

        $response = $this->kroger->searchLocations($zipCode, 8);
        $locations = $response['data'] ?? [];

        return array_map(function (array $location): array {
            $address = $location['address'] ?? [];
            $geolocation = $location['geolocation'] ?? [];
            $hours = $location['hours'] ?? [];
            
            return [
                'kroger_location_id' => (string) ($location['locationId'] ?? ''),
                'location_id' => (string) ($location['locationId'] ?? ''),
                'store_number' => (string) ($location['storeNumber'] ?? ''),
                'division_number' => (string) ($location['divisionNumber'] ?? ''),
                'name' => (string) ($location['name'] ?? 'Unknown store'),
                'chain' => (string) ($location['chain'] ?? ''),
                'city' => (string) ($address['city'] ?? ''),
                'county' => (string) ($address['county'] ?? ''),
                'state' => (string) ($address['state'] ?? ''),
                'state_code' => (string) ($address['state'] ?? ''),
                'zip_code' => (string) ($address['zipCode'] ?? ''),
                'postal_code' => (string) ($address['zipCode'] ?? ''),
                'address_line_1' => (string) ($address['addressLine1'] ?? ''),
                'address_line_2' => (string) ($address['addressLine2'] ?? ''),
                'phone' => (string) ($location['phone'] ?? ''),
                'latitude' => isset($geolocation['latitude']) ? (float) $geolocation['latitude'] : null,
                'longitude' => isset($geolocation['longitude']) ? (float) $geolocation['longitude'] : null,
                'timezone' => (string) ($hours['timezone'] ?? ''),
                'hours_json' => json_encode($hours, JSON_UNESCAPED_SLASHES),
                'raw_json' => json_encode($location, JSON_UNESCAPED_SLASHES),
            ];
        }, $locations);
    }

    public function resolveLocationId(string $locationId, string $zipCode, string $storeId = ''): string {
        $locationId = trim($locationId);
        if (preg_match('/^[A-Za-z0-9]{8}$/', $locationId)) {
            return $locationId;
        }

        $storeId = trim($storeId);
        if ($storeId === '') {
            throw new RuntimeException('Invalid Kroger location ID. Use the store lookup to pick a valid 8-character store ID.');
        }

        $locations = $this->searchLocations($zipCode);
        foreach ($locations as $location) {
            if (str_pad((string) $location['store_number'], 5, '0', STR_PAD_LEFT) === str_pad($storeId, 5, '0', STR_PAD_LEFT)) {
                return (string) $location['location_id'];
            }
        }

        throw new RuntimeException("Unable to match store {$storeId} near ZIP {$zipCode} to a Kroger location ID.");
    }

    public function getCartSummary(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS item_count,
                COALESCE(SUM(CASE WHEN gli.is_checked = 0 THEN gli.quantity ELSE 0 END), 0) AS open_quantity,
                COALESCE(SUM(CASE WHEN gli.is_checked = 1 THEN 1 ELSE 0 END), 0) AS completed_count,
                COALESCE(SUM(CASE WHEN gli.is_checked = 0 THEN gli.quantity * COALESCE(p.sale_price, p.regular_price, 0) ELSE 0 END), 0) AS estimated_total,
                COALESCE(SUM(CASE WHEN gli.is_checked = 0 AND p.sale_price IS NOT NULL THEN 1 ELSE 0 END), 0) AS active_deals
            FROM grocery_list_items gli
            LEFT JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :uid
        ");
        $stmt->execute([':uid' => $userId]);
        $summary = $stmt->fetch() ?: [];

        return [
            'item_count' => (int) ($summary['item_count'] ?? 0),
            'open_quantity' => (int) ($summary['open_quantity'] ?? 0),
            'completed_count' => (int) ($summary['completed_count'] ?? 0),
            'estimated_total' => (float) ($summary['estimated_total'] ?? 0),
            'active_deals' => (int) ($summary['active_deals'] ?? 0),
        ];
    }

    public function getDealItems(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT
                gli.id AS list_item_id,
                gli.quantity,
                p.description,
                p.brand,
                p.size,
                p.image_url,
                p.regular_price,
                p.sale_price,
                p.promo_description
            FROM grocery_list_items gli
            INNER JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :uid
              AND gli.is_checked = 0
              AND p.sale_price IS NOT NULL
            ORDER BY ((p.regular_price - p.sale_price) / NULLIF(p.regular_price, 0)) DESC, gli.created_at DESC
            LIMIT 8
        ");
        $stmt->execute([':uid' => $userId]);

        return $stmt->fetchAll();
    }

    public function getPriceHistoryForListItem(int $listItemId, ?string $locationId = null): array {
        $stmt = $this->db->prepare("
            SELECT gli.id, gli.product_id, p.description, p.kroger_product_id, p.upc
            FROM grocery_list_items gli
            INNER JOIN products p ON p.id = gli.product_id
            WHERE gli.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $listItemId]);
        $item = $stmt->fetch();

        if (!$item || empty($item['product_id'])) {
            throw new RuntimeException('Price history is unavailable for this item.');
        }

        return $this->getPriceHistoryByReference(
            (string) ($item['description'] ?? ''),
            $locationId,
            !empty($item['upc']) ? (string) $item['upc'] : null,
            (int) $item['product_id']
        );
    }

    public function getPriceHistoryByUpc(string $upc, ?string $locationId = null): array {
        $upc = trim($upc);
        if ($upc === '') {
            throw new RuntimeException('UPC is required for historical price lookup.');
        }

        $stmt = $this->db->prepare("
            SELECT description, id
            FROM products
            WHERE upc = :upc
            ORDER BY last_seen_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':upc' => $upc]);
        $product = $stmt->fetch();

        return $this->getPriceHistoryByReference(
            (string) ($product['description'] ?? 'UPC ' . $upc),
            $locationId,
            $upc,
            isset($product['id']) ? (int) $product['id'] : null
        );
    }

    private function getPriceHistoryByReference(string $description, ?string $locationId, ?string $upc, ?int $productId): array {
        $sql = "
            SELECT captured_on, regular_price, sale_price, effective_price, kroger_location_id, upc, product_id
            FROM product_price_history
            WHERE 1 = 1
        ";
        $params = [];

        if ($upc !== null && $upc !== '') {
            $sql .= " AND upc = :upc";
            $params[':upc'] = $upc;
        } elseif ($productId !== null) {
            $sql .= " AND product_id = :product_id";
            $params[':product_id'] = $productId;
        } else {
            throw new RuntimeException('Price history is unavailable for this product.');
        }

        if ($locationId !== null && $locationId !== '') {
            $sql .= " AND kroger_location_id = :location_id";
            $params[':location_id'] = $locationId;
        }

        $sql .= " ORDER BY captured_on ASC";
        $historyStmt = $this->db->prepare($sql);
        $historyStmt->execute($params);
        $history = $historyStmt->fetchAll();

        $labels = [];
        $values = [];
        foreach ($history as $row) {
            $labels[] = $row['captured_on'];
            $values[] = $row['effective_price'] !== null ? (float) $row['effective_price'] : null;
        }

        $firstValue = null;
        $latestValue = null;
        foreach ($values as $value) {
            if ($value !== null && $firstValue === null) {
                $firstValue = $value;
            }
            if ($value !== null) {
                $latestValue = $value;
            }
        }

        $changeAmount = ($firstValue !== null && $latestValue !== null) ? round($latestValue - $firstValue, 2) : null;
        $changePercent = ($firstValue && $latestValue !== null) ? round((($latestValue - $firstValue) / $firstValue) * 100, 2) : null;
        $direction = 'flat';

        if ($changeAmount !== null) {
            if ($changeAmount > 0) {
                $direction = 'up';
            } elseif ($changeAmount < 0) {
                $direction = 'down';
            }
        }

        return [
            'product_id' => $productId,
            'upc' => $upc,
            'description' => $description,
            'location_id' => $locationId,
            'labels' => $labels,
            'values' => $values,
            'points' => $history,
            'summary' => [
                'first_price' => $firstValue,
                'latest_price' => $latestValue,
                'change_amount' => $changeAmount,
                'change_percent' => $changePercent,
                'direction' => $direction,
            ],
        ];
    }

    public function refreshTrackedPriceHistory(int $userId, GroceryListRepository $repo, string $locationId, string $zipCode, string $storeId = ''): array {
        $resolvedLocationId = $this->resolveLocationId($locationId, $zipCode, $storeId);
        $tracked = $repo->getTrackedProductsForUser($userId);

        $updated = 0;
        $errors = [];

        foreach ($tracked as $product) {
            try {
                $this->refreshProductPriceHistory((int) $product['id'], $resolvedLocationId);
                $updated++;
            } catch (Throwable $e) {
                $errors[] = [
                    'product_id' => (int) $product['id'],
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'location_id' => $resolvedLocationId,
            'updated_count' => $updated,
            'errors' => $errors,
        ];
    }

    public function refreshProductPriceHistory(int $productId, string $locationId): array {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if (!$product || empty($product['kroger_product_id'])) {
            throw new RuntimeException('Tracked product could not be found.');
        }

        $response = $this->kroger->getProduct((string) $product['kroger_product_id'], $locationId);
        $raw = $response['data'] ?? null;
        if (!$raw || !is_array($raw)) {
            throw new RuntimeException('Kroger product lookup returned no data.');
        }

        $normalized = $this->normalizeProduct($raw);
        $normalized['db_id'] = $this->upsertProduct($normalized);
        $this->recordPriceHistorySnapshot($normalized['db_id'], $locationId, $normalized, 'refresh');

        return $normalized;
    }

    private function normalizeProduct(array $product): array {
        $items = $product['items'] ?? [];
        $primaryItem = $items[0] ?? [];
        $price = $primaryItem['price'] ?? [];
        $nationalPrice = $primaryItem['nationalPrice'] ?? [];
        $inventory = $primaryItem['inventory'] ?? [];
        $fulfillment = $primaryItem['fulfillment'] ?? [];
        $itemInfo = $product['itemInformation'] ?? [];

        return [
            'kroger_product_id' => (string) ($product['productId'] ?? ''),
            'upc' => (string) ($product['upc'] ?? ''),
            'alias_upcs' => !empty($product['aliasProductIds']) ? implode('|', (array) $product['aliasProductIds']) : null,
            'gtin14' => null,
            'description' => trim((string) ($product['description'] ?? 'Unnamed product')),
            'brand' => (string) ($product['brand'] ?? ''),
            'size' => (string) ($primaryItem['size'] ?? ''),
            'image_url' => $this->extractImageUrl($product['images'] ?? []),
            'product_page_url' => (string) ($product['productPageURI'] ?? ''),
            'aisle_locations' => $this->flattenAisleLocations($product['aisleLocations'] ?? []),
            'categories' => implode(', ', (array) ($product['categories'] ?? [])),
            'categories_json' => !empty($product['categories']) ? json_encode($product['categories'], JSON_UNESCAPED_SLASHES) : null,
            'country_origin' => (string) ($product['countryOrigin'] ?? ''),
            'temperature' => $this->normalizeTemperature($product['temperature'] ?? null),
            'regular_price' => isset($price['regular']) ? (float) $price['regular'] : null,
            'sale_price' => isset($price['promo']) ? (float) $price['promo'] : null,
            'national_price' => isset($nationalPrice['regular']) ? (float) $nationalPrice['regular'] : (isset($price['regular']) ? (float) $price['regular'] : null),
            'promo_description' => $this->extractPromoDescription($items),
            'inventory_level' => (string) ($inventory['stockLevel'] ?? 'UNKNOWN'),
            'fulfillment_instore' => isset($fulfillment['instore']) ? (int) (bool) $fulfillment['instore'] : 0,
            'fulfillment_shiptohome' => isset($fulfillment['shiptohome']) ? (int) (bool) $fulfillment['shiptohome'] : 0,
            'fulfillment_delivery' => isset($fulfillment['delivery']) ? (int) (bool) $fulfillment['delivery'] : 0,
            'fulfillment_curbside' => isset($fulfillment['curbside']) ? (int) (bool) $fulfillment['curbside'] : 0,
            'snap_eligible' => isset($product['snapEligible']) ? (int) (bool) $product['snapEligible'] : 0,
            'restricted_item' => isset($product['retstrictions']) ? (int) (bool) !empty($product['retstrictions']) : 0,
            'age_restricted' => isset($product['ageRestriction']) ? (int) (bool) $product['ageRestriction'] : 0,
            'alcoholic' => isset($product['alcohol']) ? (int) (bool) $product['alcohol'] : 0,
            'alcohol_proof' => isset($product['alcoholProof']) ? (int) $product['alcoholProof'] : null,
            'nutritional_preferences' => !empty($product['manufacturerDeclarations']) ? implode('|', (array) $product['manufacturerDeclarations']) : null,
            'package_length' => (string) ($itemInfo['width'] ?? ''),
            'package_width' => (string) ($itemInfo['depth'] ?? ''),
            'package_height' => (string) ($itemInfo['height'] ?? ''),
            'package_weight' => (string) ($itemInfo['netWeight'] ?? $itemInfo['grossWeight'] ?? ''),
            'receipt_description' => (string) ($product['receiptDescription'] ?? ''),
            'raw_json' => json_encode($product, JSON_UNESCAPED_SLASHES),
        ];
    }

    private function flattenAisleLocations(array $aisleLocations): string {
        $locations = [];
        foreach ($aisleLocations as $aisle) {
            $parts = [];
            if (!empty($aisle['description'])) {
                $parts[] = $aisle['description'];
            }
            if (!empty($aisle['shelfNumber'])) {
                $parts[] = 'Shelf ' . $aisle['shelfNumber'];
            }
            if (!empty($parts)) {
                $locations[] = implode(', ', $parts);
            }
        }
        return implode('; ', $locations);
    }

    private function extractImageUrl(array $images): ?string {
        foreach ($images as $imageGroup) {
            foreach (($imageGroup['sizes'] ?? []) as $size) {
                if (!empty($size['url'])) {
                    return $size['url'];
                }
            }
        }

        return null;
    }

    private function flattenLocationValues(array $aisleLocations): string {
        $parts = [];
        foreach ($aisleLocations as $location) {
            foreach (['description', 'number', 'numberOfFacings', 'sequenceNumber'] as $key) {
                if (!empty($location[$key])) {
                    $parts[] = (string) $location[$key];
                }
            }
        }

        return implode(', ', array_unique($parts));
    }

    private function extractPromoDescription(array $items): ?string {
        foreach ($items as $item) {
            $promos = $item['price']['promoDescription'] ?? [];
            if (is_string($promos) && trim($promos) !== '') {
                return trim($promos);
            }

            if (is_array($promos)) {
                foreach ($promos as $promo) {
                    if (is_string($promo) && trim($promo) !== '') {
                        return trim($promo);
                    }
                }
            }
        }

        return null;
    }

    private function normalizeTemperature(mixed $temperature): string {
        if (is_string($temperature)) {
            return $temperature;
        }

        if (is_array($temperature)) {
            return (string) ($temperature['indicator'] ?? '');
        }

        return '';
    }

    private function upsertProduct(array $product): int {
        $existing = $this->db->prepare("SELECT id FROM products WHERE upc = :upc LIMIT 1");
        $existing->execute([':upc' => $product['upc']]);
        $row = $existing->fetch();

        if ($row) {
            $stmt = $this->db->prepare("
                UPDATE products
                SET
                    kroger_product_id = :kroger_product_id,
                    alias_upcs = :alias_upcs,
                    gtin14 = :gtin14,
                    description = :description,
                    brand = :brand,
                    size = :size,
                    image_url = :image_url,
                    product_page_url = :product_page_url,
                    aisle_locations = :aisle_locations,
                    categories = :categories,
                    categories_json = :categories_json,
                    country_origin = :country_origin,
                    temperature = :temperature,
                    regular_price = :regular_price,
                    sale_price = :sale_price,
                    national_price = :national_price,
                    promo_description = :promo_description,
                    inventory_level = :inventory_level,
                    fulfillment_instore = :fulfillment_instore,
                    fulfillment_shiptohome = :fulfillment_shiptohome,
                    fulfillment_delivery = :fulfillment_delivery,
                    fulfillment_curbside = :fulfillment_curbside,
                    snap_eligible = :snap_eligible,
                    restricted_item = :restricted_item,
                    age_restricted = :age_restricted,
                    alcoholic = :alcoholic,
                    alcohol_proof = :alcohol_proof,
                    nutritional_preferences = :nutritional_preferences,
                    package_length = :package_length,
                    package_width = :package_width,
                    package_height = :package_height,
                    package_weight = :package_weight,
                    receipt_description = :receipt_description,
                    raw_json = :raw_json,
                    last_seen_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $row['id'],
                ':kroger_product_id' => $product['kroger_product_id'],
                ':alias_upcs' => $product['alias_upcs'],
                ':gtin14' => $product['gtin14'],
                ':description' => $product['description'],
                ':brand' => $product['brand'],
                ':size' => $product['size'],
                ':image_url' => $product['image_url'],
                ':product_page_url' => $product['product_page_url'],
                ':aisle_locations' => $product['aisle_locations'],
                ':categories' => $product['categories'],
                ':categories_json' => $product['categories_json'],
                ':country_origin' => $product['country_origin'],
                ':temperature' => $product['temperature'],
                ':regular_price' => $product['regular_price'],
                ':sale_price' => $product['sale_price'],
                ':national_price' => $product['national_price'],
                ':promo_description' => $product['promo_description'],
                ':inventory_level' => $product['inventory_level'],
                ':fulfillment_instore' => $product['fulfillment_instore'],
                ':fulfillment_shiptohome' => $product['fulfillment_shiptohome'],
                ':fulfillment_delivery' => $product['fulfillment_delivery'],
                ':fulfillment_curbside' => $product['fulfillment_curbside'],
                ':snap_eligible' => $product['snap_eligible'],
                ':restricted_item' => $product['restricted_item'],
                ':age_restricted' => $product['age_restricted'],
                ':alcoholic' => $product['alcoholic'],
                ':alcohol_proof' => $product['alcohol_proof'],
                ':nutritional_preferences' => $product['nutritional_preferences'],
                ':package_length' => $product['package_length'],
                ':package_width' => $product['package_width'],
                ':package_height' => $product['package_height'],
                ':package_weight' => $product['package_weight'],
                ':receipt_description' => $product['receipt_description'],
                ':raw_json' => $product['raw_json'],
            ]);

            return (int) $row['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO products (
                kroger_product_id,
                upc,
                alias_upcs,
                gtin14,
                description,
                brand,
                size,
                image_url,
                product_page_url,
                aisle_locations,
                categories,
                categories_json,
                country_origin,
                temperature,
                regular_price,
                sale_price,
                national_price,
                promo_description,
                inventory_level,
                fulfillment_instore,
                fulfillment_shiptohome,
                fulfillment_delivery,
                fulfillment_curbside,
                snap_eligible,
                restricted_item,
                age_restricted,
                alcoholic,
                alcohol_proof,
                nutritional_preferences,
                package_length,
                package_width,
                package_height,
                package_weight,
                receipt_description,
                raw_json,
                last_seen_at
            ) VALUES (
                :kroger_product_id,
                :upc,
                :alias_upcs,
                :gtin14,
                :description,
                :brand,
                :size,
                :image_url,
                :product_page_url,
                :aisle_locations,
                :categories,
                :categories_json,
                :country_origin,
                :temperature,
                :regular_price,
                :sale_price,
                :national_price,
                :promo_description,
                :inventory_level,
                :fulfillment_instore,
                :fulfillment_shiptohome,
                :fulfillment_delivery,
                :fulfillment_curbside,
                :snap_eligible,
                :restricted_item,
                :age_restricted,
                :alcoholic,
                :alcohol_proof,
                :nutritional_preferences,
                :package_length,
                :package_width,
                :package_height,
                :package_weight,
                :receipt_description,
                :raw_json,
                CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':kroger_product_id' => $product['kroger_product_id'],
            ':upc' => $product['upc'],
            ':alias_upcs' => $product['alias_upcs'],
            ':gtin14' => $product['gtin14'],
            ':description' => $product['description'],
            ':brand' => $product['brand'],
            ':size' => $product['size'],
            ':image_url' => $product['image_url'],
            ':product_page_url' => $product['product_page_url'],
            ':aisle_locations' => $product['aisle_locations'],
            ':categories' => $product['categories'],
            ':categories_json' => $product['categories_json'],
            ':country_origin' => $product['country_origin'],
            ':temperature' => $product['temperature'],
            ':regular_price' => $product['regular_price'],
            ':sale_price' => $product['sale_price'],
            ':national_price' => $product['national_price'],
            ':promo_description' => $product['promo_description'],
            ':inventory_level' => $product['inventory_level'],
            ':fulfillment_instore' => $product['fulfillment_instore'],
            ':fulfillment_shiptohome' => $product['fulfillment_shiptohome'],
            ':fulfillment_delivery' => $product['fulfillment_delivery'],
            ':fulfillment_curbside' => $product['fulfillment_curbside'],
            ':snap_eligible' => $product['snap_eligible'],
            ':restricted_item' => $product['restricted_item'],
            ':age_restricted' => $product['age_restricted'],
            ':alcoholic' => $product['alcoholic'],
            ':alcohol_proof' => $product['alcohol_proof'],
            ':nutritional_preferences' => $product['nutritional_preferences'],
            ':package_length' => $product['package_length'],
            ':package_width' => $product['package_width'],
            ':package_height' => $product['package_height'],
            ':package_weight' => $product['package_weight'],
            ':receipt_description' => $product['receipt_description'],
            ':raw_json' => $product['raw_json'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function recordPriceHistorySnapshot(int $productId, string $locationId, array $product, string $source): void {
        $effectivePrice = $product['sale_price'] ?? $product['regular_price'];
        $upc = trim((string) ($product['upc'] ?? ''));

        $stmt = $this->db->prepare("
            INSERT INTO product_price_history (
                product_id,
                upc,
                kroger_location_id,
                regular_price,
                sale_price,
                effective_price,
                captured_on,
                captured_at,
                source
            ) VALUES (
                :product_id,
                :upc,
                :location_id,
                :regular_price,
                :sale_price,
                :effective_price,
                CURRENT_DATE,
                CURRENT_TIMESTAMP,
                :source
            )
            ON DUPLICATE KEY UPDATE
                product_id = VALUES(product_id),
                upc = VALUES(upc),
                regular_price = VALUES(regular_price),
                sale_price = VALUES(sale_price),
                effective_price = VALUES(effective_price),
                captured_at = CURRENT_TIMESTAMP,
                source = VALUES(source)
        ");
        $stmt->execute([
            ':product_id' => $productId,
            ':upc' => $upc !== '' ? $upc : null,
            ':location_id' => $locationId,
            ':regular_price' => $product['regular_price'],
            ':sale_price' => $product['sale_price'],
            ':effective_price' => $effectivePrice,
            ':source' => $source,
        ]);
    }
}
