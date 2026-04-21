<?php
require __DIR__ . '/../src/Core/Database.php';
require __DIR__ . '/../src/Core/KrogerClient.php';
require __DIR__ . '/../src/Repositories/DatabaseApiRepository.php';
require __DIR__ . '/../src/Repositories/GroceryListRepository.php';
require __DIR__ . '/../src/Repositories/ShoppingCartRepository.php';
require __DIR__ . '/../src/Services/GroceryService.php';
require __DIR__ . '/../src/Services/ShoppingCartService.php';

$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    $env = parse_ini_file($envPath);
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
        }
    }
}

$config = require __DIR__ . '/../config/config.php';
$db = Database::getConnection();
$kroger = new KrogerClient($config['kroger']);
$service = new GroceryService($db, $kroger);
$repo = new GroceryListRepository($db);
$dataRepo = new DatabaseApiRepository($db);
$shoppingCartRepo = new ShoppingCartRepository($db);
$shoppingCartService = new ShoppingCartService($shoppingCartRepo, $kroger);
$userId = 1;

$action = $_GET['action'] ?? null;

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'search_products':
            $term = $_GET['q'] ?? '';
            $locationId = $_GET['locationId'] ?? $config['kroger']['default_location_id'];
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 24;
            $resolvedLocationId = $service->resolveLocationId(
                (string) $locationId,
                (string) $config['kroger']['default_zip_code'],
                (string) $config['kroger']['default_store_id']
            );
            echo json_encode([
                'ok' => true,
                'locationId' => $resolvedLocationId,
                'results' => $service->searchProducts($term, $resolvedLocationId, $limit),
            ]);
            break;

        case 'typeahead_products':
            $term = $_GET['q'] ?? '';
            $locationId = (string) ($_GET['locationId'] ?? '');
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 12;
            $limit = max(1, min(50, $limit));

            $local = $service->searchProductsLocal((string) $term, $limit);
            $results = $local;

            $needsRemote = count($local) < $limit;
            $validLocation = preg_match('/^[A-Za-z0-9]{8}$/', $locationId) === 1;

            if ($needsRemote && $validLocation) {
                $remote = $service->searchProducts((string) $term, $locationId, $limit);
                $seen = [];
                foreach ($local as $row) {
                    if (!empty($row['upc'])) {
                        $seen['upc:' . $row['upc']] = true;
                    }
                    if (!empty($row['kroger_product_id'])) {
                        $seen['pid:' . $row['kroger_product_id']] = true;
                    }
                }

                foreach ($remote as $row) {
                    $key = !empty($row['upc']) ? 'upc:' . $row['upc'] : (!empty($row['kroger_product_id']) ? 'pid:' . $row['kroger_product_id'] : null);
                    if ($key && isset($seen[$key])) {
                        continue;
                    }
                    $row['source'] = 'api';
                    $results[] = $row;
                    if ($key) {
                        $seen[$key] = true;
                    }
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }

            echo json_encode([
                'ok' => true,
                'locationId' => $locationId,
                'results' => array_slice($results, 0, $limit),
                'sources' => [
                    'db' => count($local),
                    'api' => max(0, count($results) - count($local)),
                ],
            ]);
            break;

        case 'search_locations':
            $zipCode = $_GET['zipCode'] ?? $config['kroger']['default_zip_code'];
            $locations = $service->searchLocations($zipCode);
            foreach ($locations as $location) {
                $dataRepo->upsertStore([
                    'kroger_location_id' => $location['kroger_location_id'],
                    'name' => $location['name'],
                    'chain' => $location['chain'],
                    'address_line_1' => $location['address_line_1'],
                    'address_line_2' => $location['address_line_2'] ?? null,
                    'city' => $location['city'],
                    'county' => $location['county'] ?? null,
                    'state_code' => $location['state_code'],
                    'postal_code' => $location['postal_code'],
                    'phone' => $location['phone'] ?? null,
                    'store_number' => $location['store_number'] ?? null,
                    'division_number' => $location['division_number'] ?? null,
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'timezone' => $location['timezone'] ?? null,
                    'hours_json' => $location['hours_json'] ?? null,
                    'raw_json' => $location['raw_json'] ?? null,
                ]);
            }
            echo json_encode([
                'ok' => true,
                'zipCode' => $zipCode,
                'locations' => $locations,
            ]);
            break;

        case 'get_list':
            echo json_encode([
                'ok' => true,
                'items' => $repo->getListForUser($userId),
                'usualItems' => $repo->getUsualItemsForUser($userId),
                'summary' => $service->getCartSummary($userId),
                'deals' => $service->getDealItems($userId),
            ]);
            break;

        case 'add_list_item':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = $repo->addItem(
                $userId,
                isset($payload['product_id']) ? (int) $payload['product_id'] : null,
                $payload['custom_name'] ?? null,
                isset($payload['quantity']) ? (int) $payload['quantity'] : 1
            );
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'summary' => $service->getCartSummary($userId),
            ]);
            break;

        case 'add_usual_item':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = $repo->addUsualItem(
                $userId,
                isset($payload['product_id']) ? (int) $payload['product_id'] : null,
                $payload['custom_name'] ?? null,
                isset($payload['quantity']) ? (int) $payload['quantity'] : 1
            );
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'usualItems' => $repo->getUsualItemsForUser($userId),
            ]);
            break;

        case 'remove_usual_item':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $repo->removeUsualItem((int) ($payload['id'] ?? 0));
            echo json_encode([
                'ok' => true,
                'usualItems' => $repo->getUsualItemsForUser($userId),
            ]);
            break;

        case 'add_all_usual_items':
            $added = $repo->addAllUsualItemsToCart($userId);
            echo json_encode([
                'ok' => true,
                'added' => $added,
                'items' => $repo->getListForUser($userId),
                'usualItems' => $repo->getUsualItemsForUser($userId),
                'summary' => $service->getCartSummary($userId),
                'deals' => $service->getDealItems($userId),
            ]);
            break;

        case 'toggle_item':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $repo->updateChecked((int) ($payload['id'] ?? 0), (int) ($payload['is_checked'] ?? 0));
            echo json_encode(['ok' => true, 'summary' => $service->getCartSummary($userId)]);
            break;

        case 'update_quantity':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $repo->updateQuantity((int) ($payload['id'] ?? 0), (int) ($payload['quantity'] ?? 1));
            echo json_encode(['ok' => true, 'summary' => $service->getCartSummary($userId)]);
            break;

        case 'remove_item':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $repo->removeItem((int) ($payload['id'] ?? 0));
            echo json_encode(['ok' => true, 'summary' => $service->getCartSummary($userId)]);
            break;

        case 'get_item_detail':
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo->getItemById($id);

            if (!$item) {
                throw new RuntimeException('Item not found.');
            }

            echo json_encode(['ok' => true, 'item' => $item]);
            break;

        case 'get_price_history':
            $id = (int) ($_GET['id'] ?? 0);
            $locationId = $_GET['locationId'] ?? $config['kroger']['default_location_id'];
            $resolvedLocationId = $service->resolveLocationId(
                (string) $locationId,
                (string) $config['kroger']['default_zip_code'],
                (string) $config['kroger']['default_store_id']
            );
            echo json_encode([
                'ok' => true,
                'locationId' => $resolvedLocationId,
                'history' => $service->getPriceHistoryForListItem($id, $resolvedLocationId),
            ]);
            break;

        case 'get_price_history_by_upc':
            $upc = trim((string) ($_GET['upc'] ?? ''));
            $locationId = $_GET['locationId'] ?? $config['kroger']['default_location_id'];
            $resolvedLocationId = $service->resolveLocationId(
                (string) $locationId,
                (string) $config['kroger']['default_zip_code'],
                (string) $config['kroger']['default_store_id']
            );
            echo json_encode([
                'ok' => true,
                'locationId' => $resolvedLocationId,
                'history' => $service->getPriceHistoryByUpc($upc, $resolvedLocationId),
            ]);
            break;

        case 'refresh_price_history':
            $locationId = $_GET['locationId'] ?? $config['kroger']['default_location_id'];
            echo json_encode([
                'ok' => true,
                'result' => $service->refreshTrackedPriceHistory(
                    $userId,
                    $repo,
                    (string) $locationId,
                    (string) $config['kroger']['default_zip_code'],
                    (string) $config['kroger']['default_store_id']
                ),
            ]);
            break;

        case 'config':
            echo json_encode([
                'ok' => true,
                'defaultLocationId' => $config['kroger']['default_location_id'],
                'defaultStoreId' => $config['kroger']['default_store_id'],
                'defaultZipCode' => $config['kroger']['default_zip_code'],
            ]);
            break;

        case 'get_or_create_shopping_cart':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $storeId = (int) ($payload['store_id'] ?? ($_GET['store_id'] ?? 0));
            if ($storeId <= 0) {
                throw new RuntimeException('store_id is required.');
            }

            $sessionId = trim((string) ($payload['session_id'] ?? ($_GET['session_id'] ?? '')));
            $fulfillmentMode = (string) ($payload['fulfillment_mode'] ?? ($_GET['fulfillment_mode'] ?? 'instore'));
            $useUserId = isset($payload['user_id']) || isset($_GET['user_id'])
                ? (int) ($payload['user_id'] ?? $_GET['user_id'])
                : $userId;

            echo json_encode([
                'ok' => true,
                ...$shoppingCartService->getOrCreateCart(
                    $storeId,
                    $useUserId > 0 ? $useUserId : null,
                    $sessionId !== '' ? $sessionId : null,
                    $fulfillmentMode
                ),
            ]);
            break;

        case 'get_shopping_cart':
            $cartId = (int) ($_GET['cart_id'] ?? 0);
            if ($cartId <= 0) {
                throw new RuntimeException('cart_id is required.');
            }
            echo json_encode([
                'ok' => true,
                ...$shoppingCartService->getCart($cartId),
            ]);
            break;

        case 'add_shopping_cart_item':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $cartId = (int) ($payload['cart_id'] ?? 0);
            if ($cartId <= 0) {
                throw new RuntimeException('cart_id is required.');
            }
            echo json_encode([
                'ok' => true,
                ...$shoppingCartService->addItem(
                    $cartId,
                    [
                        'product_id' => $payload['product_id'] ?? null,
                        'kroger_product_id' => $payload['kroger_product_id'] ?? null,
                        'upc' => $payload['upc'] ?? '',
                        'quantity' => $payload['quantity'] ?? 1,
                        'regular_price' => $payload['regular_price'] ?? null,
                        'sale_price' => $payload['sale_price'] ?? null,
                        'national_price' => $payload['national_price'] ?? null,
                        'promo_description' => $payload['promo_description'] ?? null,
                        'raw_json' => $payload['raw_json'] ?? null,
                    ],
                    isset($payload['kroger_access_token']) ? (string) $payload['kroger_access_token'] : null
                ),
            ]);
            break;

        case 'update_shopping_cart_item':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $cartId = (int) ($payload['cart_id'] ?? 0);
            $upc = trim((string) ($payload['upc'] ?? ''));
            if ($cartId <= 0 || $upc === '') {
                throw new RuntimeException('cart_id and upc are required.');
            }

            echo json_encode([
                'ok' => true,
                ...$shoppingCartService->updateItemQuantity(
                    $cartId,
                    $upc,
                    isset($payload['quantity']) ? (int) $payload['quantity'] : 1,
                    isset($payload['kroger_access_token']) ? (string) $payload['kroger_access_token'] : null
                ),
            ]);
            break;

        case 'remove_shopping_cart_item':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $cartId = (int) ($payload['cart_id'] ?? 0);
            $upc = trim((string) ($payload['upc'] ?? ''));
            if ($cartId <= 0 || $upc === '') {
                throw new RuntimeException('cart_id and upc are required.');
            }

            echo json_encode([
                'ok' => true,
                ...$shoppingCartService->removeItem(
                    $cartId,
                    $upc,
                    isset($payload['kroger_access_token']) ? (string) $payload['kroger_access_token'] : null
                ),
            ]);
            break;

        case 'sync_shopping_cart':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $cartId = (int) ($payload['cart_id'] ?? 0);
            if ($cartId <= 0) {
                throw new RuntimeException('cart_id is required.');
            }
            echo json_encode([
                'ok' => true,
                ...$shoppingCartService->syncCart(
                    $cartId,
                    isset($payload['kroger_access_token']) ? (string) $payload['kroger_access_token'] : null
                ),
            ]);
            break;

        case 'merge_shopping_cart':
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $sessionId = trim((string) ($payload['session_id'] ?? ''));
            $storeId = (int) ($payload['store_id'] ?? 0);
            $targetUserId = isset($payload['user_id']) ? (int) $payload['user_id'] : $userId;
            if ($sessionId === '' || $storeId <= 0 || $targetUserId <= 0) {
                throw new RuntimeException('session_id, user_id, and store_id are required.');
            }

            echo json_encode([
                'ok' => true,
                ...$shoppingCartService->mergeSessionCart(
                    $sessionId,
                    $targetUserId,
                    $storeId,
                    isset($payload['kroger_access_token']) ? (string) $payload['kroger_access_token'] : null,
                    isset($payload['fulfillment_mode']) ? (string) $payload['fulfillment_mode'] : 'instore'
                ),
            ]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
