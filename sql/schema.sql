CREATE DATABASE IF NOT EXISTS kroger
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kroger;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    lat_lng VARCHAR(64) NULL,
    timezone VARCHAR(64) NULL,
    gmt_offset VARCHAR(64) NULL,
    open_24 TINYINT(1) NULL,
    hours_json LONGTEXT NULL,
    departments_json LONGTEXT NULL,
    raw_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_stores_location (kroger_location_id),
    KEY idx_stores_city_state (city, state_code),
    KEY idx_stores_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kroger_product_id VARCHAR(64) NOT NULL,
    upc VARCHAR(64) NULL,
    description VARCHAR(255) NOT NULL,
    receipt_description VARCHAR(255) NULL,
    product_page_uri VARCHAR(1024) NULL,
    brand VARCHAR(190) NULL,
    country_origin VARCHAR(190) NULL,
    categories_json LONGTEXT NULL,
    alias_product_ids_json LONGTEXT NULL,
    alcohol TINYINT(1) NULL,
    alcohol_proof INT NULL,
    age_restriction TINYINT(1) NULL,
    snap_eligible TINYINT(1) NULL,
    manufacturer_declarations_json LONGTEXT NULL,
    sweetening_methods_json LONGTEXT NULL,
    allergens_json LONGTEXT NULL,
    allergens_description TEXT NULL,
    certified_for_passover TINYINT(1) NULL,
    hypoallergenic TINYINT(1) NULL,
    non_gmo TINYINT(1) NULL,
    non_gmo_claim_name VARCHAR(255) NULL,
    organic_claim_name VARCHAR(255) NULL,
    warnings TEXT NULL,
    restrictions_json LONGTEXT NULL,
    item_information_json LONGTEXT NULL,
    temperature_json LONGTEXT NULL,
    ratings_and_reviews_json LONGTEXT NULL,
    nutrition_information_json LONGTEXT NULL,
    size VARCHAR(64) NULL,
    image_url VARCHAR(1024) NULL,
    images_json LONGTEXT NULL,
    aisle_locations_json LONGTEXT NULL,
    items_json LONGTEXT NULL,
    inventory_level VARCHAR(64) NULL,
    fulfillment_instore TINYINT(1) NOT NULL DEFAULT 0,
    fulfillment_shiptohome TINYINT(1) NOT NULL DEFAULT 0,
    fulfillment_delivery TINYINT(1) NOT NULL DEFAULT 0,
    fulfillment_curbside TINYINT(1) NOT NULL DEFAULT 0,
    current_regular_price DECIMAL(10,2) NULL,
    current_promo_price DECIMAL(10,2) NULL,
    current_regular_per_unit_estimate DECIMAL(10,2) NULL,
    current_promo_per_unit_estimate DECIMAL(10,2) NULL,
    current_price_effective_at DATETIME NULL,
    current_price_expires_at DATETIME NULL,
    national_regular_price DECIMAL(10,2) NULL,
    national_promo_price DECIMAL(10,2) NULL,
    national_regular_per_unit_estimate DECIMAL(10,2) NULL,
    national_promo_per_unit_estimate DECIMAL(10,2) NULL,
    national_price_effective_at DATETIME NULL,
    national_price_expires_at DATETIME NULL,
    raw_json LONGTEXT NULL,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_products_kroger_product_id (kroger_product_id),
    KEY idx_products_upc (upc),
    KEY idx_products_brand (brand),
    KEY idx_products_inventory (inventory_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    KEY idx_grocery_list_user_checked (user_id, is_checked),
    KEY idx_grocery_list_product_id (product_id),
    KEY idx_grocery_list_store_id (store_id),
    CONSTRAINT fk_grocery_list_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_grocery_list_store
        FOREIGN KEY (store_id) REFERENCES stores(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_grocery_list_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usual_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    custom_name VARCHAR(255) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_usual_items_user (user_id, sort_order, created_at),
    KEY idx_usual_items_product (product_id),
    CONSTRAINT fk_usual_items_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_usual_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    KEY idx_price_history_product_day (product_id, captured_on),
    KEY idx_price_history_upc_day (upc, captured_on),
    KEY idx_price_history_location_day (kroger_location_id, captured_on),
    CONSTRAINT fk_price_history_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shopping_cart_sync_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT UNSIGNED NOT NULL,
    action ENUM('add','update','remove','sync','merge') NOT NULL,
    request_json LONGTEXT NULL,
    response_json LONGTEXT NULL,
    http_status INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cart_sync_log_cart (cart_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    store_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    base_price DECIMAL(10,2) NULL,
    promo_price DECIMAL(10,2) NULL,
    total_price DECIMAL(10,2) NULL,
    tax_rate DECIMAL(10,2) NULL,
    tax_amount DECIMAL(10,2) NULL,
    total_tax_amount DECIMAL(10,2) NULL,
    total_price_after_tax DECIMAL(10,2) NULL,
    payment_method ENUM('credit_card', 'debit_card', 'cash', 'check', 'gift_card', 'ebt_card', 'other') NULL,
    currency VARCHAR(32) NOT NULL DEFAULT 'USD',
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    order_date DATE NOT NULL,
    order_time TIME NOT NULL,
    order_number VARCHAR(64) NULL,
    order_type VARCHAR(32) NULL,
    order_source VARCHAR(32) NULL,
    order_items JSON NULL,
    raw_json LONGTEXT NULL,
    notes TEXT NULL,
    is_gift TINYINT(1) NOT NULL DEFAULT 0,
    gift_message TEXT NULL,
    gift_recipient_name VARCHAR(255) NULL,
    gift_recipient_email VARCHAR(255) NULL,
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_order_history_user (user_id, purchased_at),
    KEY idx_order_history_store (store_id, purchased_at),
    KEY idx_order_history_product (product_id, purchased_at),
    CONSTRAINT fk_order_history_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_history_store
        FOREIGN KEY (store_id) REFERENCES stores(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_order_history_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



INSERT INTO users (id, email, display_name)
VALUES (1, 'default@local.test', 'Default User')
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name);
