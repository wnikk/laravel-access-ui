import { defineConfig } from 'vite';
import vuePlugin from '@vitejs/plugin-vue';

export default defineConfig({
    build: {
        outDir: './dist',
        emptyOutDir: true,
        assetsDir: '.',
        rollupOptions: {
            input: [
                './resources/js/index.js'
            ],
        },
    },
    plugins: [
        vuePlugin({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: true,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': './resources/vue',
        }
    },
});
