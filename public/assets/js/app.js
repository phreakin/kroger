function toggleJSON(el) {
    const panel = el.nextElementSibling;
    panel.classList.toggle('open');
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    const state = {
        items: [],
        deals: [],
        usualItems: [],
    };

    const searchInput = document.getElementById('search-input');
    const topSearchInput = document.getElementById('top-search-input');
    const storeInput = document.getElementById('store-select');
    const selectedStoreLabel = document.getElementById('selected-store-label');
    const searchButton = document.getElementById('search-btn');
    const zipCodeInput = document.getElementById('zip-code-input');
    const storeLookupButton = document.getElementById('store-lookup-btn');
    const storeResults = document.getElementById('store-results');
    const storeResultsList = document.getElementById('store-results-list');
    const storeFinderPanel = document.getElementById('store-finder-panel');
    const changeStoreButton = document.getElementById('change-store-btn');
    const dropdownContainers = Array.from(document.querySelectorAll('[data-dropdown]'));
    const searchResults = document.getElementById('search-results');
    const searchState = document.getElementById('search-state');
    const listItems = document.getElementById('list-items');
    const usualItems = document.getElementById('usual-items');
    const usualItemName = document.getElementById('usual-item-name');
    const usualItemQty = document.getElementById('usual-item-qty');
    const usualAddButton = document.getElementById('usual-add-btn');
    const usualAddAllButton = document.getElementById('usual-add-all-btn');
    const dealsTable = document.getElementById('deals-table');
    const departmentSelector = document.getElementById('department-selector');

    let appConfig = {
        defaultStoreId: '',
        defaultZipCode: '',
        defaultLocationId: '',
    };
    let currentLocations = [];

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
            },
            ...options,
        });

        const json = await response.json();
        if (!json.ok) {
            throw new Error(json.error || 'Request failed');
        }

        return json;
    }

    function currency(value) {
        return `$${Number(value || 0).toFixed(2)}`;
    }

    function formatTrend(changeAmount, changePercent, direction) {
        if (changeAmount === null || Number.isNaN(Number(changeAmount))) {
            return 'No history yet';
        }

        if (Number(changeAmount) === 0) {
            return 'No change';
        }

        const sign = Number(changeAmount) > 0 ? '+' : '';
        const percentText = changePercent === null || Number.isNaN(Number(changePercent))
            ? ''
            : ` (${sign}${Number(changePercent).toFixed(2)}%)`;

        if (direction === 'down') {
            return `${currency(changeAmount)}${percentText}`;
        }

        return `${sign}${currency(changeAmount)}${percentText}`;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function setCookie(name, value, days = 30) {
        const expires = new Date(Date.now() + (days * 24 * 60 * 60 * 1000)).toUTCString();
        document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
    }

    function getCookie(name) {
        const prefix = `${encodeURIComponent(name)}=`;
        const parts = document.cookie.split(';').map((part) => part.trim());
        for (const part of parts) {
            if (part.startsWith(prefix)) {
                return decodeURIComponent(part.slice(prefix.length));
            }
        }
        return '';
    }

    function openExternal(url) {
        if (!url) {
            return;
        }
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function debounce(fn, delay) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    function getTrendColors(direction) {
        if (direction === 'up') {
            return {
                border: '#b11f24',
                fill: 'rgba(177, 31, 36, 0.12)',
                badge: 'trend-up',
            };
        }

        if (direction === 'down') {
            return {
                border: '#2c7a53',
                fill: 'rgba(44, 122, 83, 0.14)',
                badge: 'trend-down',
            };
        }

        return {
            border: '#8c6f67',
            fill: 'rgba(120, 97, 90, 0.14)',
            badge: 'trend-flat',
        };
    }

    function setStoreFinderVisibility(visible) {
        if (storeFinderPanel) {
            storeFinderPanel.classList.toggle('is-hidden', !visible);
        }

        if (changeStoreButton) {
            changeStoreButton.classList.toggle('is-hidden', visible);
        }
    }

    async function loadPriceHistoryChart() {
        const canvas = document.getElementById('priceHistoryChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const itemId = Number(canvas.dataset.itemId || 0);
        if (!itemId) {
            return;
        }

        const latestEl = document.getElementById('price-history-latest');
        const startEl = document.getElementById('price-history-start');
        const changeEl = document.getElementById('price-history-change');
        const summaryEl = document.getElementById('price-history-summary');
        const containerEl = canvas.closest('.price-history-chart-container');

        try {
            const json = await requestJson(`api.php?action=get_price_history&id=${encodeURIComponent(itemId)}`);
            const history = json.history || {};
            const labels = Array.isArray(history.labels) ? history.labels : [];
            const values = Array.isArray(history.values) ? history.values : [];
            const summary = history.summary || {};
            const colors = getTrendColors(summary.direction || 'flat');

            if (latestEl) {
                latestEl.textContent = summary.latest_price !== null && summary.latest_price !== undefined
                    ? currency(summary.latest_price)
                    : 'N/A';
            }

            if (startEl) {
                startEl.textContent = summary.first_price !== null && summary.first_price !== undefined
                    ? currency(summary.first_price)
                    : 'N/A';
            }

            if (changeEl) {
                changeEl.textContent = formatTrend(summary.change_amount, summary.change_percent, summary.direction || 'flat');
                changeEl.classList.remove('trend-up', 'trend-down', 'trend-flat');
                changeEl.classList.add(colors.badge);
            }

            if (!labels.length || !values.some((value) => value !== null)) {
                if (containerEl) {
                    containerEl.innerHTML = '<div class="empty-state">No daily price history yet. Search this item again or run the daily refresh to start tracking it.</div>';
                }
                if (summaryEl) {
                    summaryEl.classList.add('price-history-summary-empty');
                }
                return;
            }

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Daily Price',
                        data: values,
                        borderColor: colors.border,
                        backgroundColor: colors.fill,
                        pointBackgroundColor: colors.border,
                        pointBorderColor: '#fffaf3',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.28,
                        spanGaps: false,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => `Price: ${currency(context.parsed.y)}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: '#78615a',
                            },
                        },
                        y: {
                            beginAtZero: false,
                            ticks: {
                                color: '#78615a',
                                callback: (value) => currency(value),
                            },
                            grid: {
                                color: 'rgba(132, 40, 32, 0.08)',
                            },
                        },
                    },
                },
            });
        } catch (error) {
            if (containerEl) {
                containerEl.innerHTML = `<div class="empty-state">${escapeHtml(error.message || 'Price history failed to load.')}</div>`;
            }
        }
    }

    function updateSummary(summary) {
        const listCount = document.getElementById('kpi-list-count');
        const dealsCount = document.getElementById('kpi-deals-count');
        const total = document.getElementById('kpi-total');
        const openQty = document.getElementById('summary-open-qty');
        const completed = document.getElementById('summary-completed');
        const cartMeta = document.getElementById('cart-meta');

        if (!listCount) {
            return;
        }

        listCount.textContent = summary.item_count ?? 0;
        dealsCount.textContent = summary.active_deals ?? 0;
        total.textContent = currency(summary.estimated_total ?? 0);
        openQty.textContent = summary.open_quantity ?? 0;
        completed.textContent = summary.completed_count ?? 0;
        cartMeta.textContent = `${summary.item_count ?? 0} saved cart lines`;
    }

    function renderDeals(deals) {
        if (!dealsTable) {
            return;
        }

        dealsTable.innerHTML = '';

        if (!deals.length) {
            dealsTable.innerHTML = '<div class="empty-state">No promo-priced items in the cart yet.</div>';
            return;
        }

        deals.forEach((deal) => {
            const regular = Number(deal.regular_price || 0);
            const sale = Number(deal.sale_price || 0);
            const savings = regular > 0 && sale > 0 ? ((regular - sale) / regular) * 100 : 0;
            let badgeClass = 'savings-low';

            if (savings >= 30) badgeClass = 'savings-high';
            else if (savings >= 15) badgeClass = 'savings-medium';

            const card = document.createElement('article');
            card.className = 'deal-card';
            card.innerHTML = `
                <div class="deal-card-top">
                    <img class="deal-thumb" src="${escapeHtml(deal.image_url || 'https://via.placeholder.com/96x96?text=Item')}" alt="${escapeHtml(deal.description || 'Deal item')}">
                    <div>
                        <div class="deal-title">${escapeHtml(deal.description || 'Unnamed item')}</div>
                        <div class="deal-meta">${escapeHtml([deal.brand, deal.size].filter(Boolean).join(' · '))}</div>
                    </div>
                </div>
                <div class="deal-price-row">
                    <div class="deal-price-sale">${sale ? currency(sale) : 'N/A'}</div>
                    ${regular ? `<div class="deal-price-regular">${currency(regular)}</div>` : ''}
                </div>
                ${savings > 0 ? `<div class="savings-badge ${badgeClass}">${Math.round(savings)}% off</div>` : ''}
                ${deal.promo_description ? `<div class="promo-ribbon">${escapeHtml(deal.promo_description)}</div>` : ''}
            `;
            dealsTable.appendChild(card);
        });
    }

    function getPreferredLocation(locations) {
        return locations.find((location) => String(location.store_number || '').padStart(5, '0') === String(appConfig.defaultStoreId || '').padStart(5, '0')) ||
            locations.find((location) => location.location_id === appConfig.defaultLocationId) ||
            locations[0] ||
            null;
    }

    function selectLocation(locationId) {
        if (!locationId) {
            return;
        }

        const selectedLocation = currentLocations.find((location) => location.location_id === locationId) || null;

        if (storeInput) {
            storeInput.value = locationId;
        }
        if (storeResults) {
            storeResults.value = locationId;
        }
        if (selectedStoreLabel) {
            selectedStoreLabel.textContent = selectedLocation
                ? `${selectedLocation.name} - ${selectedLocation.address_line_1}, ${selectedLocation.city}, ${selectedLocation.state}`
                : 'Store selected';
        }

        setCookie('kroger_location_id', locationId);
        if (selectedLocation?.store_number) {
            setCookie('kroger_store_number', selectedLocation.store_number);
        }
        if (selectedLocation?.zip_code) {
            setCookie('kroger_zip_code', selectedLocation.zip_code);
        }

        document.querySelectorAll('.store-result-card').forEach((card) => {
            const isActive = card.dataset.locationId === locationId;
            card.classList.toggle('store-result-card-active', isActive);
            const action = card.querySelector('.store-result-action');
            if (action) {
                action.textContent = isActive ? 'Selected Store' : 'Use This Store';
            }
        });

        setStoreFinderVisibility(true);
        window.setTimeout(() => setStoreFinderVisibility(false), 150);
    }

    function renderStoreCards(locations, preferredLocationId) {
        if (!storeResultsList) {
            return;
        }

        if (!locations.length) {
            storeResultsList.innerHTML = '<div class="empty-state compact">No stores to display.</div>';
            return;
        }

        storeResultsList.innerHTML = locations.map((location) => `
            <button
                type="button"
                class="store-result-card${location.location_id === preferredLocationId ? ' store-result-card-active' : ''}"
                data-location-id="${escapeHtml(location.location_id)}"
            >
                <div class="store-result-brand">
                    <img src="assets/img/kroger_logo.svg" alt="Kroger" class="store-result-logo">
                    <div class="store-result-title">${escapeHtml(location.name)}</div>
                </div>
                <div class="store-result-meta">${escapeHtml(location.address_line_1)}</div>
                <div class="store-result-meta">${escapeHtml(location.city)}, ${escapeHtml(location.state)} ${escapeHtml(location.zip_code)}</div>
                <div class="store-result-action">${location.location_id === preferredLocationId ? 'Selected Store' : 'Use This Store'}</div>
            </button>
        `).join('');
    }

    function renderStoreOptions(locations) {
        currentLocations = locations;

        if (storeResults) {
            storeResults.innerHTML = '<option value="">Select a Kroger store</option>';
        }

        if (!locations.length) {
            setStoreFinderVisibility(true);
            renderStoreCards([], '');
            return;
        }

        locations.forEach((location) => {
            if (!storeResults) {
                return;
            }
            const option = document.createElement('option');
            option.value = location.location_id;
            option.textContent = `${location.name} - ${location.city}, ${location.state}`;
            storeResults.appendChild(option);
        });

        const preferred = getPreferredLocation(locations);
        const preferredLocationId = preferred ? preferred.location_id : '';
        setStoreFinderVisibility(true);
        renderStoreCards(locations, preferredLocationId);
        selectLocation(preferredLocationId);
    }

    function renderList(items) {
        if (!listItems) {
            return;
        }

        state.items = items;
        listItems.innerHTML = '';

        if (!items.length) {
            listItems.innerHTML = '<div class="empty-state">Your cart is empty. Search and add an item.</div>';
            return;
        }

        items.forEach((item) => {
            const row = document.createElement('article');
            row.className = `list-item${Number(item.is_checked) ? ' checked' : ''}`;
            row.dataset.id = item.id;

            const effectivePrice = item.sale_price ?? item.regular_price;
            const extendedTotal = effectivePrice ? Number(effectivePrice) * Number(item.quantity || 1) : null;

            row.innerHTML = `
                <div class="list-item-main">
                    <button class="list-checkbox ${Number(item.is_checked) ? 'checked' : ''}" data-action="toggle" data-id="${item.id}" aria-label="Toggle item"></button>
                    <img class="list-item-thumb" src="${escapeHtml(item.image_url || 'https://via.placeholder.com/80x80?text=Item')}" alt="${escapeHtml(item.description || 'Cart item')}">
                    <div class="list-item-copy">
                        <div class="list-item-name">${escapeHtml(item.custom_name || item.description || 'Untitled item')}</div>
                        <div class="list-item-meta">${escapeHtml([item.brand, item.size, item.promo_description].filter(Boolean).join(' · ') || 'Saved cart item')}</div>
                    </div>
                </div>
                <div class="list-item-actions">
                    <div class="quantity-stepper">
                        <button class="btn-secondary btn-icon" data-action="decrement" data-id="${item.id}">-</button>
                        <span class="quantity-value">${Number(item.quantity || 1)}</span>
                        <button class="btn-secondary btn-icon" data-action="increment" data-id="${item.id}">+</button>
                    </div>
                    <div class="list-price">${extendedTotal !== null ? currency(extendedTotal) : 'N/A'}</div>
                    <a class="btn-secondary" href="item.php?id=${item.id}">Detail</a>
                    <button class="btn-secondary btn-danger" data-action="remove" data-id="${item.id}">Remove</button>
                </div>
            `;

            listItems.appendChild(row);
        });
    }

    function renderUsualItems(items) {
        if (!usualItems) {
            return;
        }

        state.usualItems = items;

        if (!items.length) {
            usualItems.innerHTML = '<div class="empty-state">No usual items saved yet.</div>';
            return;
        }

        usualItems.innerHTML = items.map((item) => `
            <article class="usual-item">
                <div class="usual-item-copy">
                    <div class="usual-item-name">${escapeHtml(item.custom_name || item.description || 'Usual item')}</div>
                    <div class="usual-item-meta">${escapeHtml([item.brand, item.size, `Qty ${Number(item.quantity || 1)}`].filter(Boolean).join(' · '))}</div>
                </div>
                <div class="usual-item-actions">
                    <button class="btn-secondary" data-usual-action="add-one" data-id="${item.id}" data-quantity="${Number(item.quantity || 1)}" data-product-id="${item.product_id || ''}" data-name="${escapeHtml(item.custom_name || '')}">Add</button>
                    <button class="btn-secondary btn-danger" data-usual-action="remove" data-id="${item.id}">Remove</button>
                </div>
            </article>
        `).join('');
    }

    function renderSearchResults(results) {
        if (!searchResults) {
            return;
        }

        searchResults.innerHTML = '';

        if (!results.length) {
            searchResults.innerHTML = '<div class="empty-state">No Kroger products matched that search.</div>';
            return;
        }

        results.forEach((product) => {
            const card = document.createElement('article');
            card.className = 'result-item';
            const primaryPrice = product.sale_price ?? product.regular_price;

            card.innerHTML = `
                <div class="result-main">
                    <img class="result-thumb" src="${escapeHtml(product.image_url || 'https://via.placeholder.com/88x88?text=Item')}" alt="${escapeHtml(product.description || 'Product')}">
                    <div class="result-copy">
                        <div class="result-title">${escapeHtml(product.description || 'Unnamed product')}</div>
                        <div class="result-meta">${escapeHtml([product.brand, product.size, product.categories].filter(Boolean).join(' · '))}</div>
                        <div class="result-meta">${escapeHtml(product.aisle_locations || product.upc || '')}</div>
                    </div>
                </div>
                <div class="result-actions">
                    <div class="result-price">${primaryPrice ? currency(primaryPrice) : 'N/A'}</div>
                    <button class="btn-primary add-result-btn" data-product-id="${product.db_id}">Add to cart</button>
                    <button class="btn-secondary save-usual-btn" data-product-id="${product.db_id}" data-name="${escapeHtml(product.description || '')}">Save as usual</button>
                </div>
            `;

            searchResults.appendChild(card);
        });
    }

    async function loadCart() {
        const json = await requestJson('api.php?action=get_list');
        state.deals = json.deals || [];
        renderList(json.items || []);
        renderUsualItems(json.usualItems || []);
        renderDeals(state.deals);
        updateSummary(json.summary || {});
    }

    async function lookupStores() {
        const zipCode = zipCodeInput?.value.trim() || '';
        if (!zipCode) {
            if (searchState) {
                searchState.textContent = 'Enter a ZIP code first.';
            }
            return;
        }

        if (storeLookupButton) {
            storeLookupButton.disabled = true;
        }

        if (searchState) {
            searchState.textContent = 'Looking up nearby Kroger stores...';
        }

        try {
            const json = await requestJson(`api.php?action=search_locations&zipCode=${encodeURIComponent(zipCode)}`);
            renderStoreOptions(json.locations || []);
            if (searchState) {
                searchState.textContent = json.locations.length
                    ? `Found ${json.locations.length} stores near ${json.zipCode}. Select a store, then search products.`
                    : `No stores found near ${json.zipCode}.`;
            }
        } catch (error) {
            if (searchState) {
                searchState.textContent = error.message;
            }
        } finally {
            if (storeLookupButton) {
                storeLookupButton.disabled = false;
            }
        }
    }

    async function loadConfig() {
        try {
            const json = await requestJson('api.php?action=config');
            appConfig = {
                defaultStoreId: getCookie('kroger_store_number') || json.defaultStoreId || '',
                defaultZipCode: getCookie('kroger_zip_code') || json.defaultZipCode || '',
                defaultLocationId: getCookie('kroger_location_id') || json.defaultLocationId || '',
            };

            if (zipCodeInput && appConfig.defaultZipCode && !zipCodeInput.value.trim()) {
                zipCodeInput.value = appConfig.defaultZipCode;
            }

            if (storeInput && appConfig.defaultLocationId) {
                storeInput.value = appConfig.defaultLocationId;
            }
        } catch (error) {
            if (searchState) {
                searchState.textContent = error.message;
            }
        }
    }

    async function runSearch() {
        if (!searchInput || !storeInput || !searchButton || !searchState) {
            return;
        }

        const term = (topSearchInput?.value.trim() || searchInput.value.trim());
        const locationId = storeInput.value.trim();

        searchInput.value = term;
        if (topSearchInput) {
            topSearchInput.value = term;
        }

        if (!term) {
            searchState.textContent = 'Enter a search term first.';
            renderSearchResults([]);
            return;
        }

        if (!locationId) {
            searchState.textContent = 'Select a store first.';
            return;
        }

        searchButton.disabled = true;
        searchState.textContent = 'Searching Kroger products...';

        try {
            const json = await requestJson(`api.php?action=search_products&q=${encodeURIComponent(term)}&locationId=${encodeURIComponent(locationId)}`);
            renderSearchResults(json.results || []);
            searchState.textContent = `Loaded ${json.results.length} results for your selected store.`;
        } catch (error) {
            searchState.textContent = error.message;
            if (searchResults) {
                searchResults.innerHTML = '<div class="empty-state">Search failed. Check credentials and store selection.</div>';
            }
        } finally {
            searchButton.disabled = false;
        }
    }

    searchButton?.addEventListener('click', runSearch);
    storeLookupButton?.addEventListener('click', lookupStores);
    changeStoreButton?.addEventListener('click', () => {
        setStoreFinderVisibility(true);
        storeFinderPanel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    storeResults?.addEventListener('change', () => {
        selectLocation(storeResults.value);
    });
    storeResultsList?.addEventListener('click', (event) => {
        const card = event.target.closest('.store-result-card');
        if (!card) {
            return;
        }
        selectLocation(card.dataset.locationId || '');
    });
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            runSearch();
        }
    });
    topSearchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            document.getElementById('search-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            runSearch();
        }
    });
    zipCodeInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            lookupStores();
        }
    });

    searchResults?.addEventListener('click', async (event) => {
        const addButton = event.target.closest('.add-result-btn');
        const saveUsualButton = event.target.closest('.save-usual-btn');

        try {
            if (addButton) {
                addButton.disabled = true;
                await requestJson('api.php?action=add_list_item', {
                    method: 'POST',
                    body: JSON.stringify({
                        product_id: Number(addButton.dataset.productId),
                        quantity: 1,
                    }),
                });
                await loadCart();
                addButton.disabled = false;
            }

            if (saveUsualButton) {
                await requestJson('api.php?action=add_usual_item', {
                    method: 'POST',
                    body: JSON.stringify({
                        product_id: Number(saveUsualButton.dataset.productId),
                        custom_name: null,
                        quantity: 1,
                    }),
                });
                await loadCart();
            }
        } catch (error) {
            window.alert(error.message);
        }
    });

    usualAddButton?.addEventListener('click', async () => {
        const customName = usualItemName?.value.trim() || '';
        const quantity = Math.max(1, Number(usualItemQty?.value || 1));

        if (!customName) {
            window.alert('Enter a usual item name.');
            return;
        }

        try {
            const json = await requestJson('api.php?action=add_usual_item', {
                method: 'POST',
                body: JSON.stringify({
                    custom_name: customName,
                    quantity,
                }),
            });
            renderUsualItems(json.usualItems || []);
            if (usualItemName) {
                usualItemName.value = '';
            }
            if (usualItemQty) {
                usualItemQty.value = '1';
            }
        } catch (error) {
            window.alert(error.message);
        }
    });

    usualAddAllButton?.addEventListener('click', async () => {
        try {
            const json = await requestJson('api.php?action=add_all_usual_items', {
                method: 'POST',
                body: JSON.stringify({}),
            });
            renderUsualItems(json.usualItems || []);
            renderList(json.items || []);
            renderDeals(json.deals || []);
            updateSummary(json.summary || {});
        } catch (error) {
            window.alert(error.message);
        }
    });

    usualItems?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-usual-action]');
        if (!button) {
            return;
        }

        try {
            if (button.dataset.usualAction === 'remove') {
                const json = await requestJson('api.php?action=remove_usual_item', {
                    method: 'POST',
                    body: JSON.stringify({ id: Number(button.dataset.id) }),
                });
                renderUsualItems(json.usualItems || []);
            }

            if (button.dataset.usualAction === 'add-one') {
                await requestJson('api.php?action=add_list_item', {
                    method: 'POST',
                    body: JSON.stringify({
                        product_id: button.dataset.productId ? Number(button.dataset.productId) : null,
                        custom_name: button.dataset.productId ? null : button.dataset.name,
                        quantity: Number(button.dataset.quantity || 1),
                    }),
                });
                await loadCart();
            }
        } catch (error) {
            window.alert(error.message);
        }
    });

    listItems?.addEventListener('click', async (event) => {
        const control = event.target.closest('[data-action]');
        if (!control) {
            return;
        }

        const itemId = Number(control.dataset.id);
        const current = state.items.find((item) => Number(item.id) === itemId);
        if (!current) {
            return;
        }

        const action = control.dataset.action;
        let endpoint = null;
        const payload = { id: itemId };

        if (action === 'toggle') {
            endpoint = 'toggle_item';
            payload.is_checked = Number(current.is_checked) ? 0 : 1;
        }

        if (action === 'increment') {
            endpoint = 'update_quantity';
            payload.quantity = Number(current.quantity || 1) + 1;
        }

        if (action === 'decrement') {
            endpoint = 'update_quantity';
            payload.quantity = Math.max(1, Number(current.quantity || 1) - 1);
        }

        if (action === 'remove') {
            endpoint = 'remove_item';
        }

        if (!endpoint) {
            return;
        }

        try {
            await requestJson(`api.php?action=${endpoint}`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await loadCart();
        } catch (error) {
            window.alert(error.message);
        }
    });

    const navButtons = Array.from(document.querySelectorAll('[data-scroll-target]'));
    navButtons.forEach((button) => {
        button.addEventListener('click', () => {
            navButtons.forEach((navButton) => {
                navButton.classList.remove('nav-item-active');
            });

            button.classList.add('nav-item-active');
            const target = document.getElementById(button.dataset.scrollTarget);
            target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelectorAll('[data-external-url]').forEach((button) => {
        button.addEventListener('click', () => openExternal(button.dataset.externalUrl));
    });

    departmentSelector?.addEventListener('change', (event) => {
        const target = event.target.value;
        if (target) {
            const section = document.getElementById(target);
            section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            event.target.value = '';
        }
    });

    dropdownContainers.forEach((container) => {
        const trigger = container.querySelector('[data-dropdown-trigger]');
        const menu = container.querySelector('[data-dropdown-menu]');
        if (!trigger || !menu) {
            return;
        }

        const setOpen = (open) => {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            trigger.dataset.open = open ? 'true' : 'false';
            menu.hidden = !open;
        };

        setOpen(false);

        trigger.addEventListener('click', () => {
            const isOpen = trigger.getAttribute('aria-expanded') === 'true';
            setOpen(!isOpen);
        });

        menu.addEventListener('click', (event) => {
            if (event.target.closest('[data-scroll-target], [data-external-url]')) {
                setOpen(false);
            }
        });

        document.addEventListener('click', (event) => {
            if (!container.contains(event.target)) {
                setOpen(false);
            }
        });
    });

    if ('IntersectionObserver' in window) {
        const sections = navButtons
            .map((button) => document.getElementById(button.dataset.scrollTarget))
            .filter(Boolean);

        const observer = new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

            if (!visible?.target?.id) {
                return;
            }

            navButtons.forEach((button) => {
                button.classList.toggle('nav-item-active', button.dataset.scrollTarget === visible.target.id);
            });
        }, {
            root: document.querySelector('.main'),
            threshold: [0.2, 0.45, 0.7],
        });

        sections.forEach((section) => observer.observe(section));
    }

    if (listItems || dealsTable || usualItems) {
        loadCart().catch((error) => {
            if (searchState) {
                searchState.textContent = error.message;
            }
            if (listItems) {
                listItems.innerHTML = '<div class="empty-state">Failed to load cart from the database.</div>';
            }
            if (dealsTable) {
                dealsTable.innerHTML = '<div class="empty-state">Deals are unavailable until the cart loads.</div>';
            }
            if (usualItems) {
                usualItems.innerHTML = '<div class="empty-state">Usual items are unavailable until the app loads.</div>';
            }
        });
    }

    loadConfig().then(() => {
        if (zipCodeInput?.value.trim()) {
            lookupStores();
        }
    });

    const handleTopSearchInput = debounce(async () => {
        const term = topSearchInput?.value.trim() || '';
        const locationId = storeInput?.value.trim() || '';

        if (!term || !locationId) {
            return;
        }

        try {
            const json = await requestJson(`api.php?action=search_products&q=${encodeURIComponent(term)}&locationId=${encodeURIComponent(locationId)}`);
            renderSearchResults(json.results || []);
            if (searchState) {
                searchState.textContent = `Showing ${json.results.length} results as you type.`;
            }
        } catch (error) {
            // Silently fail during live search
        }
    }, 500);

    topSearchInput?.addEventListener('input', handleTopSearchInput);

    const handleUsualItemInput = debounce(async () => {
        const term = usualItemName?.value.trim() || '';
        if (!term || term.length < 2) {
            return;
        }

        const locationId = storeInput?.value.trim() || '';
        if (!locationId) {
            return;
        }

        try {
            const json = await requestJson(`api.php?action=search_products&q=${encodeURIComponent(term)}&locationId=${encodeURIComponent(locationId)}`);
            if (json.results && json.results.length > 0) {
                const firstResult = json.results[0];
                usualItemName.dataset.productId = firstResult.db_id;
                usualItemName.placeholder = `${firstResult.description}`;
            }
        } catch (error) {
            // Silently fail during live search
        }
    }, 600);

    usualItemName?.addEventListener('input', handleUsualItemInput);

    loadPriceHistoryChart();
});
