import { defineConfig } from 'vite';
import vuePlugin from '@vitejs/plugin-vue';

/**
 * One entry in, two files out: dist/accessUi.js and dist/accessUi.css.
 *
 * Built as an IIFE rather than an ES module so a page can load it with a plain `<script src>` — no
 * `type="module"`, no import map, nothing for a host application's asset pipeline to agree with. The
 * bundle assigns `window.accessUi` itself, which is why `output.name` is absent: Rollup would declare
 * a second global for the module's exports, and there are none to declare.
 *
 * Everything is bundled in, Vue included. That is deliberate: the panel has to work on a machine with
 * no route to the internet, so nothing may be left to a CDN at runtime.
 */
export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        assetsDir: '.',

        // One stylesheet for one entry, named after it rather than the `style.css` default.
        cssCodeSplit: false,

        // es2020 covers optional chaining and nullish coalescing as-is; the class fields and private
        // methods in the vendored httpUi are lowered by esbuild.
        target: 'es2020',
        sourcemap: false,

        rollupOptions: {
            input: 'resources/js/accessUi.js',
            output: {
                format: 'iife',

                // A single file, so a `<script>` tag is the entire integration.
                inlineDynamicImports: true,

                entryFileNames: 'accessUi.js',
                chunkFileNames: 'accessUi-[name].js',
                assetFileNames: 'accessUi.[ext]',
            },
        },
    },

    plugins: [
        vuePlugin({
            template: {
                transformAssetUrls: {
                    // No asset URLs are referenced from components — the icons are inline paths — so
                    // there is nothing to rewrite, and rewriting would only invent a base path the
                    // host never agreed to.
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
