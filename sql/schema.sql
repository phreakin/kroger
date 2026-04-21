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
    timezone VARCHAR(64) NULL,
    hours_json LONGTEXT NULL,
    raw_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_stores_location (kroger_location_id),
    KEY idx_stores_city_state (city, state_code),
    KEY idx_stores_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    UNIQUE KEY uniq_products_upc (upc),
    KEY idx_products_kroger_id (kroger_product_id),
    KEY idx_products_brand (brand),
    KEY idx_products_inventory (inventory_level),
    KEY idx_products_restricted (restricted_item),
    KEY idx_products_age_restricted (age_restricted)
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
    KEY idx_price_history_location_day (kroger_location_id, captured_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (id, email, display_name)
VALUES (1, 'default@local.test', 'Default User')
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name);
