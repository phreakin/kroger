<?php
class DatabaseApiRepository {
    public function __construct(private PDO $db) {}

    public function listProducts(array $filters = []): array {
        $sql = "
            SELECT *
            FROM products
            WHERE 1 = 1
        ";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (description LIKE :q OR brand LIKE :q OR upc LIKE :q)";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sql .= " ORDER BY last_seen_at DESC, id DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . max(1, (int) $filters['limit']);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getProduct(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listStores(array $filters = []): array {
        $sql = "
            SELECT *
            FROM stores
            WHERE 1 = 1
        ";
        $params = [];

        if (!empty($filters['zip_code'])) {
            $sql .= " AND postal_code = :postal_code";
            $params[':postal_code'] = $filters['zip_code'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (name LIKE :q OR city LIKE :q OR address_line_1 LIKE :q)";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sql .= " ORDER BY updated_at DESC, name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getStore(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM stores WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertStore(array $store): int {
        $existing = $this->db->prepare("SELECT id FROM stores WHERE kroger_location_id = :location_id LIMIT 1");
        $existing->execute([':location_id' => $store['kroger_location_id']]);
        $row = $existing->fetch();

        if ($row) {
            $stmt = $this->db->prepare("
                UPDATE stores
                SET
                    name = :name,
                    chain = :chain,
                    address_line_1 = :address_line_1,
                    address_line_2 = :address_line_2,
                    city = :city,
                    county = :county,
                    state_code = :state_code,
                    postal_code = :postal_code,
                    phone = :phone,
                    store_number = :store_number,
                    division_number = :division_number,
                    latitude = :latitude,
                    longitude = :longitude,
                    timezone = :timezone,
                    hours_json = :hours_json,
                    raw_json = :raw_json,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $row['id'],
                ':name' => $store['name'],
                ':chain' => $store['chain'],
                ':address_line_1' => $store['address_line_1'],
                ':address_line_2' => $store['address_line_2'],
                ':city' => $store['city'],
                ':county' => $store['county'],
                ':state_code' => $store['state_code'],
                ':postal_code' => $store['postal_code'],
                ':phone' => $store['phone'],
                ':store_number' => $store['store_number'],
                ':division_number' => $store['division_number'],
                ':latitude' => $store['latitude'],
                ':longitude' => $store['longitude'],
                ':timezone' => $store['timezone'],
                ':hours_json' => $store['hours_json'],
                ':raw_json' => $store['raw_json'],
            ]);
            return (int) $row['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO stores (
                kroger_location_id,
                name,
                chain,
                address_line_1,
                address_line_2,
                city,
                county,
                state_code,
                postal_code,
                phone,
                store_number,
                division_number,
                latitude,
                longitude,
                timezone,
                hours_json,
                raw_json
            ) VALUES (
                :kroger_location_id,
                :name,
                :chain,
                :address_line_1,
                :address_line_2,
                :city,
                :county,
                :state_code,
                :postal_code,
                :phone,
                :store_number,
                :division_number,
                :latitude,
                :longitude,
                :timezone,
                :hours_json,
                :raw_json
            )
        ");
        $stmt->execute([
            ':kroger_location_id' => $store['kroger_location_id'],
            ':name' => $store['name'],
            ':chain' => $store['chain'],
            ':address_line_1' => $store['address_line_1'],
            ':address_line_2' => $store['address_line_2'],
            ':city' => $store['city'],
            ':county' => $store['county'],
            ':state_code' => $store['state_code'],
            ':postal_code' => $store['postal_code'],
            ':phone' => $store['phone'],
            ':store_number' => $store['store_number'],
            ':division_number' => $store['division_number'],
            ':latitude' => $store['latitude'],
            ':longitude' => $store['longitude'],
            ':timezone' => $store['timezone'],
            ':hours_json' => $store['hours_json'],
            ':raw_json' => $store['raw_json'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listCartItems(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT
                gli.*,
                p.kroger_product_id,
                p.upc,
                p.description,
                p.brand,
                p.size,
                p.image_url,
                p.aisle_locations,
                p.categories,
                p.country_origin,
                p.temperature,
                p.regular_price,
                p.sale_price,
                p.promo_description,
                p.last_seen_at
            FROM grocery_list_items gli
            LEFT JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :uid
            ORDER BY gli.is_checked ASC, gli.created_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getCartItem(int $userId, int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT
                gli.*,
                p.kroger_product_id,
                p.upc,
                p.description,
                p.brand,
                p.size,
                p.image_url,
                p.aisle_locations,
                p.categories,
                p.country_origin,
                p.temperature,
                p.regular_price,
                p.sale_price,
                p.promo_description,
                p.raw_json,
                p.last_seen_at
            FROM grocery_list_items gli
            LEFT JOIN products p ON p.id = gli.product_id
            WHERE gli.user_id = :uid AND gli.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':id' => $id,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createCartItem(int $userId, array $payload): int {
        $stmt = $this->db->prepare("
            INSERT INTO grocery_list_items (user_id, product_id, custom_name, quantity, is_checked)
            VALUES (:uid, :pid, :name, :quantity, :checked)
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':pid' => isset($payload['product_id']) ? (int) $payload['product_id'] : null,
            ':name' => $payload['custom_name'] ?? null,
            ':quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
            ':checked' => !empty($payload['is_checked']) ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateCartItem(int $userId, int $id, array $payload): ?array {
        $current = $this->getCartItem($userId, $id);
        if (!$current) {
            return null;
        }

        $stmt = $this->db->prepare("
            UPDATE grocery_list_items
            SET
                custom_name = :custom_name,
                quantity = :quantity,
                is_checked = :is_checked
            WHERE user_id = :uid AND id = :id
        ");
        $stmt->execute([
            ':custom_name' => array_key_exists('custom_name', $payload) ? $payload['custom_name'] : $current['custom_name'],
            ':quantity' => array_key_exists('quantity', $payload) ? max(1, (int) $payload['quantity']) : (int) $current['quantity'],
            ':is_checked' => array_key_exists('is_checked', $payload) ? ((int) !!$payload['is_checked']) : (int) $current['is_checked'],
            ':uid' => $userId,
            ':id' => $id,
        ]);

        return $this->getCartItem($userId, $id);
    }

    public function deleteCartItem(int $userId, int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM grocery_list_items WHERE user_id = :uid AND id = :id");
        $stmt->execute([
            ':uid' => $userId,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function listUsualItems(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT
                ui.*,
                p.description,
                p.brand,
                p.size,
                p.image_url,
                p.sale_price,
                p.regular_price
            FROM usual_items ui
            LEFT JOIN products p ON p.id = ui.product_id
            WHERE ui.user_id = :uid
            ORDER BY ui.sort_order ASC, ui.created_at ASC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getUsualItem(int $userId, int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT
                ui.*,
                p.description,
                p.brand,
                p.size,
                p.image_url,
                p.sale_price,
                p.regular_price
            FROM usual_items ui
            LEFT JOIN products p ON p.id = ui.product_id
            WHERE ui.user_id = :uid AND ui.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':id' => $id,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createUsualItem(int $userId, array $payload): int {
        $stmt = $this->db->prepare("
            INSERT INTO usual_items (user_id, product_id, custom_name, quantity, sort_order)
            VALUES (
                :uid,
                :pid,
                :name,
                :quantity,
                COALESCE((SELECT MAX(sort_order) + 1 FROM usual_items WHERE user_id = :uid2), 0)
            )
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':uid2' => $userId,
            ':pid' => isset($payload['product_id']) ? (int) $payload['product_id'] : null,
            ':name' => $payload['custom_name'] ?? null,
            ':quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateUsualItem(int $userId, int $id, array $payload): ?array {
        $current = $this->getUsualItem($userId, $id);
        if (!$current) {
            return null;
        }

        $stmt = $this->db->prepare("
            UPDATE usual_items
            SET
                custom_name = :custom_name,
                quantity = :quantity,
                sort_order = :sort_order
            WHERE user_id = :uid AND id = :id
        ");
        $stmt->execute([
            ':custom_name' => array_key_exists('custom_name', $payload) ? $payload['custom_name'] : $current['custom_name'],
            ':quantity' => array_key_exists('quantity', $payload) ? max(1, (int) $payload['quantity']) : (int) $current['quantity'],
            ':sort_order' => array_key_exists('sort_order', $payload) ? max(0, (int) $payload['sort_order']) : (int) $current['sort_order'],
            ':uid' => $userId,
            ':id' => $id,
        ]);

        return $this->getUsualItem($userId, $id);
    }

    public function deleteUsualItem(int $userId, int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM usual_items WHERE user_id = :uid AND id = :id");
        $stmt->execute([
            ':uid' => $userId,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }
}
