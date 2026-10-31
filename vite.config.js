import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import lunarPanelPlugin from '@lunarphp/panel-vite-plugin';

// Compiles resources/js/addon.ts to a single IIFE bundle that shares the
// panel's Vue instance (window.Vue) instead of bundling its own copy.
// `root` pins resolution to this package so the config works whether Vite is
// invoked here or from the host app's root.
export default defineConfig({
    root: fileURLToPath(new URL('.', import.meta.url)),
    plugins: [
        vue(),
        // Kept for its manifest relocation (.vite/manifest.json -> manifest.json);
        // its rollupOptions block is ignored by rolldown-vite 8, so the
        // externals/IIFE settings below are declared as rolldownOptions.
        lunarPanelPlugin({ name: 'LunarPanelMedia' }),
    ],
    publicDir: false,
    build: {
        outDir: 'build',
        emptyOutDir: true,
        manifest: true,
        rolldownOptions: {
            input: 'resources/js/addon.ts',
            external: ['vue', '@inertiajs/vue3', 'vue-i18n', '@lunarphp/panel'],
            output: {
                format: 'iife',
                name: 'LunarPanelMedia',
                globals: {
                    'vue': 'Vue',
                    '@inertiajs/vue3': 'InertiaVue3',
                    'vue-i18n': 'VueI18n',
                    '@lunarphp/panel': 'LunarPanelUI',
                },
            },
        },
    },
});
