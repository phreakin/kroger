<?php
declare(strict_types=1);

use App\Controllers\Api\CartController;
use App\Controllers\Api\OrderController;
use App\Controllers\Api\ProductController;
use App\Controllers\Api\StoreController;
use App\Controllers\Web\HomeController;
use App\Http\Router;

return static function (Router $router, array $container): void {
    /** @var HomeController $home */
    $home = $container['home'];
    /** @var ProductController $products */
    $products = $container['products'];
    /** @var StoreController $stores */
    $stores = $container['stores'];
    /** @var CartController $cart */
    $cart = $container['cart'];
    /** @var OrderController $orders */
    $orders = $container['orders'];

    $router->add('GET', '/app.php', [$home, 'index']);
    $router->add('GET', '/api/v1/products', [$products, 'index'], ['json']);
    $router->add('GET', '/api/v1/stores', [$stores, 'index'], ['json']);
    $router->add('GET', '/api/v1/cart', [$cart, 'index'], ['json']);
    $router->add('POST', '/api/v1/cart/items', [$cart, 'add'], ['json']);
    $router->add('GET', '/api/v1/orders', [$orders, 'index'], ['json', 'auth']);
};
