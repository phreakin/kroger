<aside class="cart-drawer" data-cart-drawer>
    <header class="cart-drawer__header">
        <h3>Your Cart</h3>
    </header>

    <div class="cart-drawer__items" data-cart-drawer-items>
        @forelse(($items ?? []) as $item)
            <div class="cart-drawer-item" data-upc="{{ $item['upc'] }}">
                <div class="cart-drawer-item__content">
                    <div class="cart-drawer-item__name">{{ $item['description'] ?? $item['upc'] }}</div>
                    <div class="cart-drawer-item__price">
                        ${{ number_format((float)($item['sale_price'] ?? $item['regular_price'] ?? $item['national_price'] ?? 0), 2) }}
                    </div>
                </div>
                <div class="cart-drawer-item__actions">
                    <button type="button" data-action="decrease" data-upc="{{ $item['upc'] }}">−</button>
                    <input type="number" min="1" value="{{ (int)($item['quantity'] ?? 1) }}" data-qty-input="{{ $item['upc'] }}">
                    <button type="button" data-action="increase" data-upc="{{ $item['upc'] }}">+</button>
                    <button type="button" data-action="remove" data-upc="{{ $item['upc'] }}">Remove</button>
                </div>
            </div>
        @empty
            <div class="cart-drawer__empty">Your cart is empty.</div>
        @endforelse
    </div>

    <footer class="cart-drawer__footer">
        <div class="cart-drawer__totals">
            <div>Subtotal: $<span data-cart-subtotal>{{ number_format((float)($cart['subtotal'] ?? 0), 2) }}</span></div>
            <div>Total: $<span data-cart-total>{{ number_format((float)($cart['total'] ?? 0), 2) }}</span></div>
        </div>
        <button type="button" class="cart-drawer__checkout">Checkout</button>
    </footer>
</aside>
