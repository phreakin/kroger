<template>
  <section class="mt-6">
    <div class="flex gap-2">
      <input v-model="state.query" class="rounded border px-3 py-2" placeholder="Search products" />
      <button class="rounded bg-blue-600 px-3 py-2 text-white" @click="search">Search</button>
    </div>
    <ul class="mt-4 space-y-2">
      <li v-for="(item, idx) in state.products" :key="idx" class="rounded border bg-white p-3">
        {{ item.description || item.brand || item.upc || 'Product' }}
      </li>
    </ul>
  </section>
</template>

<script setup>
import { useStore } from '../modules/store';
import { fetchProducts } from '../modules/api';

const state = useStore();

const search = async () => {
  const data = await fetchProducts(state.query, state.locationId);
  const results = data.results?.remote || data.results?.local || data.results || [];
  state.products = Array.isArray(results) ? results : [];
};
</script>
