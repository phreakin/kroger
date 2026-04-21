<?php
class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/../../config/config.php';

            self::$pdo = new PDO(
                $config['db']['dsn'],
                $config['db']['user'],
                $config['db']['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true,
                ]
            );

            self::ensureSchema(self::$pdo);
        }
        return self::$pdo;
    }

    private static function ensureSchema(PDO $db): void {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                display_name VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS stores (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kroger_location_id VARCHAR(64) NOT NULL,
                name VARCHAR(190) NOT NULL,
                chain VARCHAR(120) NULL,
                address_line_1 VARCHAR(190) NULL,
                address_line_2 VARCHAR(190) NULL,
                city VARCHAR(120) NULL,
                county VARCHAR(120) NULL,
                state_code VARCHAR(32) NULL,
                postal_code VARCHAR(32) NULL,
                phone VARCHAR(64) NULL,
                store_number VARCHAR(64) NULL,
                division_number VARCHAR(64) NULL,
                latitude DECIMAL(10, 8) NULL,
                longitude DECIMAL(11, 8) NULL,
                timezone VARCHAR(64) NULL,
                hours_json LONGTEXT NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_stores_location (kroger_location_id),
                KEY idx_stores_city_state (city, state_code),
                KEY idx_stores_coordinates (latitude, longitude)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kroger_product_id VARCHAR(64) NULL,
                upc VARCHAR(64) NOT NULL UNIQUE,
                alias_upcs TEXT NULL,
                gtin14 VARCHAR(64) NULL,
                description VARCHAR(255) NOT NULL,
                brand VARCHAR(190) NULL,
                size VARCHAR(64) NULL,
                image_url VARCHAR(1024) NULL,
                product_page_url VARCHAR(1024) NULL,
                aisle_locations TEXT NULL,
                categories TEXT NULL,
                categories_json LONGTEXT NULL,
                country_origin VARCHAR(190) NULL,
                temperature VARCHAR(64) NULL,
                regular_price DECIMAL(10,2) NULL,
                sale_price DECIMAL(10,2) NULL,
                national_price DECIMAL(10,2) NULL,
                promo_description VARCHAR(255) NULL,
                inventory_level VARCHAR(64) NULL,
                fulfillment_instore TINYINT(1) NOT NULL DEFAULT 0,
                fulfillment_shiptohome TINYINT(1) NOT NULL DEFAULT 0,
                fulfillment_delivery TINYINT(1) NOT NULL DEFAULT 0,
                fulfillment_curbside TINYINT(1) NOT NULL DEFAULT 0,
                snap_eligible TINYINT(1) NOT NULL DEFAULT 0,
                restricted_item TINYINT(1) NOT NULL DEFAULT 0,
                age_restricted TINYINT(1) NOT NULL DEFAULT 0,
                alcoholic TINYINT(1) NOT NULL DEFAULT 0,
                alcohol_proof SMALLINT UNSIGNED NULL,
                nutritional_preferences TEXT NULL,
                package_length VARCHAR(64) NULL,
                package_width VARCHAR(64) NULL,
                package_height VARCHAR(64) NULL,
                package_weight VARCHAR(64) NULL,
                receipt_description VARCHAR(255) NULL,
                raw_json LONGTEXT NULL,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_products_kroger_id (kroger_product_id),
                KEY idx_products_brand (brand),
                KEY idx_products_inventory (inventory_level),
                KEY idx_products_restricted (restricted_item),
                KEY idx_products_age_restricted (age_restricted)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS grocery_list_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                store_id INT UNSIGNED NULL,
                product_id INT UNSIGNED NULL,
                custom_name VARCHAR(255) NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                is_checked TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_created (user_id, created_at),
                INDEX idx_product_id (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS usual_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NULL,
                custom_name VARCHAR(255) NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_usual_items_user (user_id, sort_order, created_at),
                INDEX idx_usual_items_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS product_price_history (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                upc VARCHAR(64) NULL,
                kroger_location_id VARCHAR(64) NOT NULL,
                regular_price DECIMAL(10,2) NULL,
                sale_price DECIMAL(10,2) NULL,
                effective_price DECIMAL(10,2) NULL,
                captured_on DATE NOT NULL,
                captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                source VARCHAR(32) NOT NULL DEFAULT 'search',
                UNIQUE KEY uniq_price_history_daily (product_id, kroger_location_id, captured_on),
                UNIQUE KEY uniq_price_history_upc_daily (upc, kroger_location_id, captured_on),
                INDEX idx_price_history_product_day (product_id, captured_on),
                INDEX idx_price_history_upc_day (upc, captured_on),
                INDEX idx_price_history_location_day (kroger_location_id, captured_on)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS shopping_cart (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                session_id VARCHAR(128) NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                kroger_cart_id VARCHAR(128) NULL,
                subtotal DECIMAL(10,2) NULL,
                total DECIMAL(10,2) NULL,
                item_count INT UNSIGNED NOT NULL DEFAULT 0,
                fulfillment_mode ENUM('instore','delivery','pickup','shiptohome') DEFAULT 'instore',
                last_synced_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cart_user_store (user_id, store_id),
                UNIQUE KEY uq_cart_session_store (session_id, store_id),
                KEY idx_cart_store (store_id),
                KEY idx_cart_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS shopping_cart_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cart_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NULL,
                kroger_product_id VARCHAR(64) NULL,
                upc VARCHAR(64) NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                regular_price DECIMAL(10,2) NULL,
                sale_price DECIMAL(10,2) NULL,
                national_price DECIMAL(10,2) NULL,
                promo_description VARCHAR(255) NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cart_item (cart_id, upc),
                KEY idx_cart_items_cart (cart_id),
                KEY idx_cart_items_product (product_id),
                KEY idx_cart_items_upc (upc)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS shopping_cart_sync_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cart_id BIGINT UNSIGNED NOT NULL,
                action ENUM('add','update','remove','sync','merge') NOT NULL,
                request_json LONGTEXT NULL,
                response_json LONGTEXT NULL,
                http_status INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cart_sync_log_cart (cart_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        self::ensureColumn($db, 'products', 'upc', "ALTER TABLE products ADD COLUMN upc VARCHAR(64) NULL AFTER kroger_product_id");
        self::ensureColumn($db, 'products', 'image_url', "ALTER TABLE products ADD COLUMN image_url VARCHAR(1024) NULL AFTER size");
        self::ensureColumn($db, 'products', 'aisle_locations', "ALTER TABLE products ADD COLUMN aisle_locations TEXT NULL AFTER image_url");
        self::ensureColumn($db, 'products', 'categories', "ALTER TABLE products ADD COLUMN categories TEXT NULL AFTER aisle_locations");
        self::ensureColumn($db, 'products', 'country_origin', "ALTER TABLE products ADD COLUMN country_origin VARCHAR(190) NULL AFTER categories");
        self::ensureColumn($db, 'products', 'temperature', "ALTER TABLE products ADD COLUMN temperature VARCHAR(64) NULL AFTER country_origin");
        self::ensureColumn($db, 'products', 'regular_price', "ALTER TABLE products ADD COLUMN regular_price DECIMAL(10,2) NULL AFTER temperature");
        self::ensureColumn($db, 'products', 'sale_price', "ALTER TABLE products ADD COLUMN sale_price DECIMAL(10,2) NULL AFTER regular_price");
        self::ensureColumn($db, 'products', 'promo_description', "ALTER TABLE products ADD COLUMN promo_description VARCHAR(255) NULL AFTER sale_price");
        self::ensureColumn($db, 'products', 'raw_json', "ALTER TABLE products ADD COLUMN raw_json LONGTEXT NULL AFTER promo_description");

        self::ensureColumn($db, 'grocery_list_items', 'quantity', "ALTER TABLE grocery_list_items ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER custom_name");
        self::ensureColumn($db, 'grocery_list_items', 'is_checked', "ALTER TABLE grocery_list_items ADD COLUMN is_checked TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity");
        self::ensureColumn($db, 'grocery_list_items', 'updated_at', "ALTER TABLE grocery_list_items ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        self::ensureColumn($db, 'grocery_list_items', 'store_id', "ALTER TABLE grocery_list_items ADD COLUMN store_id INT UNSIGNED NULL AFTER user_id");
        self::ensureColumn($db, 'usual_items', 'quantity', "ALTER TABLE usual_items ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER custom_name");
        self::ensureColumn($db, 'usual_items', 'sort_order', "ALTER TABLE usual_items ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity");
        self::ensureColumn($db, 'usual_items', 'updated_at', "ALTER TABLE usual_items ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        self::ensureColumn($db, 'product_price_history', 'upc', "ALTER TABLE product_price_history ADD COLUMN upc VARCHAR(64) NULL AFTER product_id");
        self::ensureColumn($db, 'product_price_history', 'effective_price', "ALTER TABLE product_price_history ADD COLUMN effective_price DECIMAL(10,2) NULL AFTER sale_price");
        self::ensureColumn($db, 'product_price_history', 'source', "ALTER TABLE product_price_history ADD COLUMN source VARCHAR(32) NOT NULL DEFAULT 'search' AFTER captured_at");
        self::ensureIndex($db, 'product_price_history', 'uniq_price_history_upc_daily', "ALTER TABLE product_price_history ADD UNIQUE KEY uniq_price_history_upc_daily (upc, kroger_location_id, captured_on)");
        self::ensureIndex($db, 'product_price_history', 'idx_price_history_upc_day', "ALTER TABLE product_price_history ADD KEY idx_price_history_upc_day (upc, captured_on)");

        $db->exec("
            INSERT INTO users (id, email, display_name)
            VALUES (1, 'default@local.test', 'Default User')
            ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)
        ");
    }

    private static function ensureColumn(PDO $db, string $table, string $column, string $sql): void {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute([':column' => $column]);

        if (!$stmt->fetch()) {
            $db->exec($sql);
        }
    }

    private static function ensureIndex(PDO $db, string $table, string $index, string $sql): void {
        $stmt = $db->prepare("
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = :table
              AND index_name = :index_name
            LIMIT 1
        ");
        $stmt->execute([
            ':table' => $table,
            ':index_name' => $index,
        ]);

        if (!$stmt->fetch()) {
            $db->exec($sql);
        }
    }
}
