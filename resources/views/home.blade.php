<?php ob_start(); ?>
<main class="mx-auto max-w-7xl p-6">
    <h1 class="text-3xl font-bold">FrysFood.com Clone Scaffold</h1>
    <p class="mt-2 text-sm text-slate-600">Products, stores, cart, pricing, and order modules are scaffolded.</p>

    <section class="mt-8">
        <h2 class="text-xl font-semibold">Latest Products</h2>
        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($products as $product): ?>
                <article class="rounded border bg-white p-4">
                    <h3 class="font-medium"><?= htmlspecialchars((string) ($product['description'] ?? 'Product'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-sm text-slate-500"><?= htmlspecialchars((string) ($product['brand'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-semibold">Recent Stores</h2>
        <ul class="mt-4 space-y-2">
            <?php foreach ($stores as $store): ?>
                <li class="rounded border bg-white p-3">
                    <?= htmlspecialchars((string) ($store['name'] ?? 'Store'), ENT_QUOTES, 'UTF-8') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
<?php $content = (string) ob_get_clean(); require __DIR__ . '/layouts/app.blade.php'; ?>
