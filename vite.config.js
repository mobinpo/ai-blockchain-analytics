import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

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
        port: 5174,
        // Disable HTTPS for Vite to avoid SSL issues
        https: false,
        hmr: {
            host: '192.168.1.114',
            port: 5174,
        },
        watch: {
            usePolling: true,
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
        },
    },
    ssr: {
        noExternal: ['vue'],
    },
});
