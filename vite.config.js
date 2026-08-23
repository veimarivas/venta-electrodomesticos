import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Velzon está construido sobre Bootstrap 5 y ya trae su CSS compilado en
// public/assets. Vite solo compila los estilos y scripts PROPIOS del
// proyecto, que se cargan encima de la plantilla. Por eso no se usa
// Tailwind aquí: su preflight pisaría los estilos de Bootstrap.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: [
                'resources/views/**',
                'routes/**',
                'config/menu.php',
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/public/assets/**'],
        },
    },
});
