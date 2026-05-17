import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/crear-sala.css',  
                'resources/js/crear-sala.js',   
                'resources/css/modo-examen.css', 
                'resources/js/modo-examen.js',   
            ],
            refresh: true,
        }),
    ],
});