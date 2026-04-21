import axios from 'axios';

export const api = axios.create({
  headers: { Accept: 'application/json' },
});

export const fetchProducts = (query, locationId) =>
  api.get('/api/v1/products', { params: { q: query, locationId } }).then((r) => r.data);
