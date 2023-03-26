import { defineConfig } from 'vite';
import vuePlugin from '@vitejs/plugin-vue';

export default defineConfig({
    build: {
        //manifest: false,
        //minify: false,
        //target: 'es2020',
        outDir: './dist',
        emptyOutDir: true,
        assetsDir: '.',
        rollupOptions: {
            input: [
                //'./resources/js/accessUi.ukit.js'
                './resources/js/accessUi.bt.js'
            ],
            output: {
                assetFileNames: '[name].[ext]',
                chunkFileNames: '[name]-[hash].js',
                entryFileNames: '[name].js',
            },
        },
    },
    plugins: [
        vuePlugin({
            template: {
                transformAssetUrls: {
                    // The Vue plugin will re-write asset URLs, when referenced
                    // in Single File Components, to point to the Laravel web
                    // server. Setting this to `null` allows the Laravel plugin
                    // to instead re-write asset URLs to point to the Vite
                    // server instead.
                    base: null,

                    // The Vue plugin will parse absolute URLs and treat them
                    // as absolute paths to files on disk. Setting this to
                    // `false` will leave absolute URLs un-touched so they can
                    // reference assets in the public directory as expected.
                    //includeAbsolute: false,
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
