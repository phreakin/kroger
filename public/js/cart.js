const API_BASE = '/api.php';

async function apiCall(action, payload = {}, method = 'POST') {
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  };

  const url = new URL(API_BASE, window.location.origin);
  url.searchParams.set('action', action);

  if (method !== 'GET') {
    options.body = JSON.stringify(payload);
  } else {
    Object.entries(payload).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        url.searchParams.set(key, String(value));
      }
    });
  }

  const response = await fetch(url.toString(), options);
  const json = await response.json();
  if (!response.ok || !json.ok) {
    throw new Error(json.error || 'Cart request failed');
  }
  return json;
}

export async function addToCart(upc, quantity = 1, cartId = window.CART_ID) {
  if (!upc) throw new Error('UPC is required');
  return apiCall('add_shopping_cart_item', {
    cart_id: Number(cartId),
    upc: String(upc),
    quantity: Number(quantity || 1),
  });
}

export async function updateCartItem(upc, quantity, cartId = window.CART_ID) {
  if (!upc) throw new Error('UPC is required');
  return apiCall('update_shopping_cart_item', {
    cart_id: Number(cartId),
    upc: String(upc),
    quantity: Number(quantity),
  });
}

export async function removeFromCart(upc, cartId = window.CART_ID) {
  if (!upc) throw new Error('UPC is required');
  return apiCall('remove_shopping_cart_item', {
    cart_id: Number(cartId),
    upc: String(upc),
  });
}

export async function refreshCartDrawer(cartId = window.CART_ID) {
  const data = await apiCall('get_shopping_cart', { cart_id: Number(cartId) }, 'GET');
  const container = document.querySelector('[data-cart-drawer-items]');
  const subtotalEl = document.querySelector('[data-cart-subtotal]');
  const totalEl = document.querySelector('[data-cart-total]');

  if (container) {
    container.innerHTML = '';
    (data.items || []).forEach((item) => {
      const row = document.createElement('div');
      row.className = 'cart-drawer-item';
      row.innerHTML = `
        <div class="cart-drawer-item__name">${item.upc}</div>
        <div class="cart-drawer-item__qty">Qty: ${item.quantity}</div>
      `;
      container.appendChild(row);
    });
  }

  if (subtotalEl) {
    subtotalEl.textContent = Number(data.cart?.subtotal || 0).toFixed(2);
  }
  if (totalEl) {
    totalEl.textContent = Number(data.cart?.total || 0).toFixed(2);
  }

  return data;
}
