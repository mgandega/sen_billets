import { defineConfig } from 'vite';
import { resolve } from 'path';
import vitePluginSymfony from 'vite-plugin-symfony';

export default defineConfig({
  plugins: [
    vitePluginSymfony()
  ],
  root: './',
  publicDir: 'public',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html')
      }
    }
  },
  server: {
    port: 8000,
    open: true,
    watch: {
      usePolling: true
    }
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'assets'),
      'bootstrap': resolve(__dirname, 'node_modules/bootstrap'),
      'bootstrap-icons': resolve(__dirname, 'node_modules/bootstrap-icons'),
      '@symfony/stimulus-bridge': resolve(__dirname, 'node_modules/@symfony/stimulus-bridge'),
      './webpack/loader!@symfony/stimulus-bridge/controllers.json': resolve(__dirname, 'assets/controllers.json')
    }
  },
  css: {
    devSourcemap: true
  },
  optimizeDeps: {
    include: ['bootstrap', '@hotwired/stimulus'],
    exclude: ['@symfony/stimulus-bridge']
  }
});