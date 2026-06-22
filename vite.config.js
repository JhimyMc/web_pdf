import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/dark-toggle.js',
                'resources/css/crear-sala.css',  
                'resources/js/crear-sala.js',   
                'resources/css/modo-examen.css', 
                'resources/js/modo-examen.js',  
                'resources/css/sala-configurar.css',
                'resources/js/sala-configurar.js',
                'resources/css/sala-dashboard.css',
                'resources/js/sala-dashboard.js',
                'resources/css/mapa-mental.css',
                'resources/js/mapa-mental.js',
            ],
            refresh: true,
        }),
    ],
});