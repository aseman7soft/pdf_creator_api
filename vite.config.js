import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * Admin panel asset pipeline.
 *
 * The panel is a standalone Bootstrap 5 RTL application: Bootstrap RTL,
 * Bootstrap Icons and the Vazirmatn font are all bundled locally so the panel
 * never depends on a CDN. A non-Vite fallback copy of the same files lives in
 * `public/vendor` (see `resources/views/layouts/app.blade.php`).
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
