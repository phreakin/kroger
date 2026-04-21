<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ProductRepository;
use App\Repositories\StoreRepository;
use App\View\View;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly ProductRepository $products,
        private readonly StoreRepository $stores,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('home', [
            'title' => 'FrysFood Clone',
            'products' => $this->products->latest(12),
            'stores' => $this->stores->latest(6),
        ]));
    }
}
