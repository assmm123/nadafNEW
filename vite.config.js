import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Tajawal', { weights: [400, 500, 700, 800], optimizedFallbacks: false }),
                bunny('Poppins', { weights: [400, 500, 600, 700], optimizedFallbacks: false }),
                bunny('El Messiri', { weights: [400, 500, 600, 700], optimizedFallbacks: false }),
                bunny('Almarai', { weights: [300, 400, 700, 800], optimizedFallbacks: false }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
