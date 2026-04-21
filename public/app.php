<?php
declare(strict_types=1);

use App\Controllers\Api\CartController;
use App\Controllers\Api\OrderController;
use App\Controllers\Api\ProductController;
use App\Controllers\Api\StoreController;
use App\Controllers\Web\HomeController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\JsonMiddleware;
use App\Http\Request;
use App\Http\Router;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StoreRepository;
use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\Kroger\CatalogV2Api;
use App\Services\Kroger\DiscountHistoryApi;
use App\Services\Kroger\KrogerApiClient;
use App\Services\Kroger\LocationApi;
use App\Services\Kroger\NationalPricingApi;
use App\Services\Kroger\PriceHistoryApi;
use App\Services\Kroger\ProductsApi;
use App\Services\LocationService;
use App\Services\OrderService;
use App\Services\PricingSyncService;
use App\View\View;

$config = require __DIR__ . '/../bootstrap/app.php';
$legacy = $config['legacy'];

$pdo = new PDO(
    $legacy['db']['dsn'],
    $legacy['db']['user'],
    $legacy['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$client = new KrogerApiClient(
    getenv('KROGER_BASE_URL') ?: ($legacy['kroger']['base_url'] ?? 'https://api.kroger.com/v1'),
    getenv('KROGER_CLIENT_ID') ?: ($legacy['kroger']['client_id'] ?? ''),
    getenv('KROGER_CLIENT_SECRET') ?: ($legacy['kroger']['client_secret'] ?? '')
);

$productRepo = new ProductRepository($pdo);
$storeRepo = new StoreRepository($pdo);
$cartRepo = new CartRepository($pdo);
$orderRepo = new OrderRepository($pdo);

$catalogService = new CatalogService($productRepo, new ProductsApi($client), new CatalogV2Api($client));
$locationService = new LocationService($storeRepo, new LocationApi($client));
$cartService = new CartService($cartRepo);
$orderService = new OrderService($orderRepo);
$pricingService = new PricingSyncService(new PriceHistoryApi($client), new NationalPricingApi($client), new DiscountHistoryApi($client));
unset($pricingService);

$container = [
    'home' => new HomeController(new View(), $productRepo, $storeRepo),
    'products' => new ProductController($catalogService),
    'stores' => new StoreController($locationService),
    'cart' => new CartController($cartService),
    'orders' => new OrderController($orderService),
];

$router = new Router();
$router->middleware('json', new JsonMiddleware());
$router->middleware('auth', new AuthMiddleware());

$routes = require __DIR__ . '/../config/routes.php';
$routes($router, $container);

$request = Request::capture();
$response = $router->dispatch($request);
$response->send();
