import { createApp } from 'vue';
import Alpine from 'alpinejs';
import ProductSearch from './components/ProductSearch.vue';

Alpine.start();

createApp({
  components: { ProductSearch },
  template: '<ProductSearch />',
}).mount('#app');
