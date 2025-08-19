import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        // Disable HTTPS for Vite to avoid SSL issues
        https: false,
        hmr: false,
        watch: {
            usePolling: true,
        },
        cors: {
            origin: ['http://localhost:8003', 'http://127.0.0.1:8003'],
            credentials: true,
        },
        proxy: {
            '/api': {
                target: 'http://localhost:8003',
                changeOrigin: true,
                secure: false,
            },
        },
    },
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    ssr: {
        noExternal: ['vue'],
    },
});
