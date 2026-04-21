import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  build: {
    manifest: true,
    outDir: 'public/dist',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        cart: 'public/js/cart.js',
        main: 'resources/frontend/js/main.js',
        styles: 'resources/frontend/css/app.css',
      },
      output: {
        entryFileNames: (chunk) => `${chunk.name}.js`,
        assetFileNames: (assetInfo) => {
          if ((assetInfo.name || '').endsWith('.css')) return '[name].css';
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
