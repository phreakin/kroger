import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    minify: 'esbuild',
    manifest: true,
    outDir: 'public/dist',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        cart: 'public/js/cart.js',
      },
      output: {
        entryFileNames: 'cart.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
});
