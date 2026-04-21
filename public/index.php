<?php
$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    $env = parse_ini_file($envPath);
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
        }
    }
}

$config = require dirname(__DIR__) . '/config/config.php';
$defaultLocationId = htmlspecialchars($config['kroger']['default_location_id'] ?? '', ENT_QUOTES, 'UTF-8');
$defaultZipCode = htmlspecialchars($config['kroger']['default_zip_code'] ?? '85281', ENT_QUOTES, 'UTF-8');
$departments = [
    ['label' => 'Adult Beverage', 'target' => 'deals-panel', 'icon' => 'coffee'],
    ['label' => 'Baby', 'target' => 'usual-items-panel', 'icon' => 'heart'],
    ['label' => 'Beauty', 'target' => 'account-panel', 'icon' => 'star'],
    ['label' => 'Beverage', 'target' => 'search-panel', 'icon' => 'droplet'],
    ['label' => 'Breakfast', 'target' => 'search-panel', 'icon' => 'sun'],
    ['label' => 'Cleaning & Household', 'target' => 'usual-items-panel', 'icon' => 'home'],
    ['label' => 'Delivered to Your Door', 'target' => 'cart-panel', 'icon' => 'truck'],
    ['label' => 'Electronics', 'target' => 'search-panel', 'icon' => 'monitor'],
    ['label' => 'Everything You Need for Personal Care', 'target' => 'account-panel', 'icon' => 'shield'],
    ['label' => 'Favorites for your favorites.', 'target' => 'usual-items-panel', 'icon' => 'heart'],
    ['label' => 'Floral', 'target' => 'usual-items-panel', 'icon' => 'feather'],
    ['label' => 'Frozen', 'target' => 'search-panel', 'icon' => 'cloud-snow'],
    ['label' => 'Health', 'target' => 'account-panel', 'icon' => 'activity'],
    ['label' => 'Home', 'target' => 'usual-items-panel', 'icon' => 'home'],
    ['label' => 'Home Chef', 'target' => 'usual-items-panel', 'icon' => 'coffee'],
    ['label' => 'Kitchen & Dining', 'target' => 'usual-items-panel', 'icon' => 'package'],
    ['label' => 'More to Explore', 'target' => 'search-panel', 'icon' => 'compass'],
    ['label' => 'Murrays Cheese', 'target' => 'deals-panel', 'icon' => 'gift'],
    ['label' => 'Natural & Organic', 'target' => 'search-panel', 'icon' => 'leaf'],
    ['label' => 'Pantry', 'target' => 'search-panel', 'icon' => 'archive'],
    ['label' => 'Patio & Grilling', 'target' => 'usual-items-panel', 'icon' => 'sun'],
    ['label' => 'Personal Care', 'target' => 'account-panel', 'icon' => 'shield'],
    ['label' => 'Pets', 'target' => 'usual-items-panel', 'icon' => 'heart'],
    ['label' => 'Shop Health, Beauty & Baby', 'target' => 'account-panel', 'icon' => 'plus-circle'],
    ['label' => 'Shop Pet, Toys & Floral', 'target' => 'usual-items-panel', 'icon' => 'shopping-bag'],
    ['label' => 'Shop for Home Essentials', 'target' => 'usual-items-panel', 'icon' => 'shopping-cart'],
    ['label' => 'There’s No Place Like Home', 'target' => 'usual-items-panel', 'icon' => 'home'],
    ['label' => 'Toys', 'target' => 'usual-items-panel', 'icon' => 'smile'],
    ['label' => 'Vitacost', 'target' => 'account-panel', 'icon' => 'plus-square'],
    ['label' => 'Vitamins & Supplements', 'target' => 'account-panel', 'icon' => 'plus'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kroger Grocery Cart</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-dark">
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/img/kroger_logo.svg" alt="Kroger" class="brand-logo">
        </div>

        <div class="sidebar-account-action">
            <button class="btn-primary sidebar-signin-btn" data-external-url="https://www.frysfood.com/auth/signin">
                <i data-feather="user"></i>
                <span>Sign In To Fry's</span>
            </button>
        </div>

        <nav class="sidebar-nav">
            <button class="nav-item nav-item-active" data-scroll-target="overview">
                <i data-feather="activity"></i>
                <span>Overview</span>
            </button>

            <button class="nav-item" data-scroll-target="search-panel">
                <i data-feather="search"></i>
                <span>Browse Items</span>
            </button>

            <button class="nav-item" data-scroll-target="cart-panel">
                <i data-feather="list"></i>
                <span>Cart</span>
            </button>

            <button class="nav-item" data-scroll-target="deals-panel">
                <i data-feather="tag"></i>
                <span>Deals</span>
            </button>

            <button class="nav-item" data-scroll-target="account-panel">
                <i data-feather="external-link"></i>
                <span>Fry's Links</span>
            </button>
        </nav>
    </aside>

    <main class="main">
        <section class="storefront-shell">
            <div class="storefront-topbar">
                <div class="storefront-brand-cluster">
                    <img src="assets/img/kroger_logo.svg" alt="Kroger" class="storefront-logo">
                </div>

                <div class="storefront-actions">
                    <label class="storefront-search" for="top-search-input">
                        <input type="text" id="top-search-input" placeholder="Search Products">
                        <i data-feather="search"></i>
                    </label>

                    <button class="storefront-account" data-external-url="https://www.frysfood.com/auth/signin">
                        <i data-feather="user"></i>
                        <span>Sign In</span>
                    </button>

                    <button class="storefront-cart" data-scroll-target="cart-panel" aria-label="View cart">
                        <i data-feather="shopping-cart"></i>
                    </button>
                </div>
            </div>

            <div class="storefront-utilitybar">
                <button class="delivery-pill" data-scroll-target="search-panel">
                    <i data-feather="map-pin"></i>
                    <span>Delivery to <?= $defaultZipCode ?></span>
                </button>

                <div class="utility-links">
                    <button class="utility-link" data-external-url="https://www.frysfood.com/savings/cl/coupons/">Digital Coupons</button>
                    <button class="utility-link" data-external-url="https://www.frysfood.com/weeklyad/weeklyad">Weekly Ad</button>
                    <button class="utility-link" data-external-url="https://www.frysfood.com/pr/weekly-digital-deals">5 Times Digital Coupons</button>
                    <button class="utility-link" data-external-url="https://www.frysfood.com/savings/">4X Gift Cards</button>
                    <button class="utility-link" data-scroll-target="deals-panel">New Arrivals</button>
                    <button class="utility-link" data-scroll-target="usual-items-panel">Meal Planning & Recipes</button>
                    <button class="utility-link" data-scroll-target="search-panel">Store Locator</button>
                </div>
            </div>

            <div class="storefront-category-dropdown">
                <select id="department-selector" class="input-dark" aria-label="Browse departments">
                    <option value="">Select a department...</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= htmlspecialchars($department['target'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($department['label'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <header class="main-header" id="overview">
            <div class="header-left">
                <h1 class="main-title">
                    <img src="assets/img/kroger_logo.svg" alt="Kroger" class="hero-logo">
                    Kroger Grocery Cart
                </h1>
                <p class="main-subtitle">
                    <i data-feather="activity"></i>
                    Search Kroger inventory, add products to your cart, and inspect saved item details.
                </p>
            </div>

            <div class="header-right">
                <div class="main-kpis">
                    <div class="kpi-card">
                        <div class="kpi-label">Cart Items</div>
                        <div class="kpi-value" id="kpi-list-count">0</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Active Deals</div>
                        <div class="kpi-value kpi-value-teal" id="kpi-deals-count">0</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Estimated Total</div>
                        <div class="kpi-value" id="kpi-total">$0.00</div>
                    </div>
                </div>
            </div>
        </header>

        <section class="timeline-section">
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon"><i data-feather="map-pin"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Live Kroger Product Search</div>
                        <div class="timeline-meta">Credentials load from `.env`. Set `KROGER_LOCATION_ID` if you want a real default store.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-grid">
            <section class="card card-tall" id="search-panel">
                <header class="card-header">
                    <h2 class="card-title">Find a Store & Search Items</h2>
                    <span class="card-meta">Pick your store first, then search Kroger products</span>
                </header>

                <div class="card-body">
                    <div class="store-row">
                        <input type="text" id="zip-code-input" class="input-dark" value="<?= $defaultZipCode ?>" placeholder="ZIP code">
                        <button id="store-lookup-btn" class="btn-secondary">Find Stores</button>
                        <select id="store-results" class="input-dark">
                            <option value="">Select a Kroger store</option>
                        </select>
                    </div>

                    <div id="selected-store-label" class="selected-store-label selected-store-hidden" aria-hidden="true"></div>
                    <button type="button" id="change-store-btn" class="btn-secondary store-change-btn store-change-floating is-hidden">Change Store</button>

                    <div id="store-finder-panel" class="store-finder-panel">
                        <div id="store-results-list" class="store-results-list"></div>
                    </div>

                    <div class="search-row">
                        <input type="text" id="search-input" class="input-dark" placeholder="Search Kroger products">
                        <input type="hidden" id="store-select" value="<?= $defaultLocationId ?>">
                        <button id="search-btn" class="btn-primary">Search</button>
                    </div>

                    <div class="search-state" id="search-state">Find a store by ZIP, then search for products to populate your cart.</div>
                    <div id="search-results" class="results-list"></div>
                </div>
            </section>

            <section class="card card-tall" id="cart-panel">
                <header class="card-header">
                    <h2 class="card-title">Cart</h2>
                    <span class="card-meta" id="cart-meta">Your saved items</span>
                </header>

                <div class="card-body">
                    <div class="cart-summary-grid" id="cart-summary-grid">
                        <div class="summary-pill">
                            <span>Open Qty</span>
                            <strong id="summary-open-qty">0</strong>
                        </div>
                        <div class="summary-pill">
                            <span>Completed</span>
                            <strong id="summary-completed">0</strong>
                        </div>
                    </div>

                    <div id="list-items" class="list-items"></div>
                </div>
            </section>
        </section>

        <section class="section-grid">
            <section class="card" id="usual-items-panel">
                <header class="card-header">
                    <h2 class="card-title">Usual Items</h2>
                    <span class="card-meta">Staples you buy every time</span>
                </header>

                <div class="card-body">
                    <div class="usual-row">
                        <input type="text" id="usual-item-name" class="input-dark" placeholder="Add a usual item">
                        <input type="number" id="usual-item-qty" class="input-dark input-qty" value="1" min="1">
                        <button id="usual-add-btn" class="btn-secondary">Save Usual</button>
                        <button id="usual-add-all-btn" class="btn-primary">Add All To Cart</button>
                    </div>

                    <div id="usual-items" class="usual-items-list"></div>
                </div>
            </section>

            <section class="card" id="deals-panel">
                <header class="card-header">
                    <h2 class="card-title">Deals Snapshot</h2>
                    <span class="card-meta">Saved items with promo pricing</span>
                </header>

                <div class="card-body">
                    <div id="deals-table" class="deals-grid"></div>
                </div>
            </section>
        </section>

        <section class="section-grid">
            <section class="card" id="account-panel">
                <header class="card-header">
                    <h2 class="card-title">Fry's Account</h2>
                    <span class="card-meta">Official account actions</span>
                </header>

                <div class="card-body">
                    <div class="account-hero">
                        <div>
                            <div class="account-title">Use your real Fry's account without leaving this workflow.</div>
                            <div class="account-copy">Sign in on the official Fry's site, then use coupons, weekly deals, and savings pages alongside this app.</div>
                        </div>
                        <button class="btn-primary" data-external-url="https://www.frysfood.com/auth/signin">Open Fry's Sign In</button>
                    </div>

                    <div class="account-links-grid">
                        <button class="account-link-card" data-external-url="https://www.frysfood.com/auth/signin">
                            <div class="account-link-title">Account Login</div>
                            <div class="account-link-meta">Open official Fry's sign-in and account session</div>
                        </button>

                        <button class="account-link-card" data-external-url="https://www.frysfood.com/savings/cl/coupons/">
                            <div class="account-link-title">Digital Coupons</div>
                            <div class="account-link-meta">Clip coupons on the official Fry's coupons page</div>
                        </button>

                        <button class="account-link-card" data-external-url="https://www.frysfood.com/weeklyad/weeklyad">
                            <div class="account-link-title">Weekly Ad</div>
                            <div class="account-link-meta">Browse the live Fry's weekly ad</div>
                        </button>

                        <button class="account-link-card" data-external-url="https://www.frysfood.com/pr/weekly-digital-deals">
                            <div class="account-link-title">Weekly Digital Deals</div>
                            <div class="account-link-meta">Open weekly digital deals and featured coupon promos</div>
                        </button>

                        <button class="account-link-card" data-external-url="https://www.frysfood.com/savings/">
                            <div class="account-link-title">Savings Hub</div>
                            <div class="account-link-meta">Access points, sale items, cash back, and ad links</div>
                        </button>
                    </div>

                    <div class="account-note">
                        Fry's authentication runs on `frysfood.com`, so sign-in opens on the official site. This app keeps the store lookup and cart workflow ready while you use those account pages.
                    </div>
                </div>
            </section>
        </section>
    </main>
</div>

<script src="assets/js/app.js"></script>
<script src="https://unpkg.com/feather-icons"></script>
<script>feather.replace();</script>
</body>
</html>
