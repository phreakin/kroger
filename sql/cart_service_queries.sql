-- SELECT cart
SELECT *
FROM shopping_cart
WHERE id = :cart_id
LIMIT 1;

-- INSERT cart
INSERT INTO shopping_cart (user_id, session_id, store_id, fulfillment_mode, item_count)
VALUES (:user_id, :session_id, :store_id, :fulfillment_mode, 0);

-- UPDATE cart totals
UPDATE shopping_cart
SET
    item_count = :item_count,
    subtotal = :subtotal,
    total = :total,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :cart_id;

-- INSERT/UPDATE cart item
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
) VALUES (
    :cart_id,
    :product_id,
    :kroger_product_id,
    :upc,
    :quantity,
    :regular_price,
    :sale_price,
    :national_price,
    :promo_description,
    :raw_json
)
ON DUPLICATE KEY UPDATE
    quantity = VALUES(quantity),
    regular_price = VALUES(regular_price),
    sale_price = VALUES(sale_price),
    national_price = VALUES(national_price),
    promo_description = VALUES(promo_description),
    raw_json = VALUES(raw_json),
    updated_at = CURRENT_TIMESTAMP;

-- DELETE cart item
DELETE FROM shopping_cart_items
WHERE cart_id = :cart_id
  AND upc = :upc;

-- SELECT cart items
SELECT *
FROM shopping_cart_items
WHERE cart_id = :cart_id
ORDER BY created_at ASC, id ASC;

-- MERGE carts
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
    updated_at = CURRENT_TIMESTAMP;
