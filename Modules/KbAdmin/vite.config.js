import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
// Uncomment the import for your frontend framework:
// import vue from '@vitejs/plugin-vue';
// import { svelte } from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    build: {
        outDir: '../../public/build-kbadmin',
        emptyOutDir: true,
        manifest: true,
    },
    plugins: [
        laravel({
            publicDirectory: '../../public',
            buildDirectory: 'build-kbadmin',
            input: [
                __dirname + '/resources/assets/sass/app.scss',
                __dirname + '/resources/assets/js/app.js'
            ],
            refresh: true,
        }),
        // Uncomment the plugin for your frontend framework:
        // vue({
        //     template: {
        //         transformAssetUrls: {
        //             base: null,
        //             includeAbsolute: false,
        //         },
        //     },
        // }),
        // svelte(),
    ],
    resolve: {
        alias: {
            '@': __dirname + '/resources/js',
        },
    },
});
