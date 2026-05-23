import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  publicDir: false,
  build: {
    outDir: 'public/assets/build',
    emptyOutDir: true,
    manifest: false,
    rollupOptions: {
      input: 'resources/js/main.jsx',
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: '[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'app.css';
          }
          return '[name][extname]';
        },
      },
    },
  },
});
