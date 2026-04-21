import { reactive } from 'vue';

export const useStore = () => reactive({
  locationId: '',
  query: '',
  products: [],
});
