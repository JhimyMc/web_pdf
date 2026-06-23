import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/welcome.css',
                'resources/css/crear-sala.css',
                'resources/css/modo-examen.css',
                'resources/css/sala-configurar.css',
                'resources/css/sala-dashboard.css',
                'resources/css/sala-historial.css',
                'resources/css/sala-play.css',
                'resources/css/sala-reporte.css',
                'resources/css/solo-exam.css',
                'resources/css/mapa-mental.css',
                'resources/css/tarjetas-estudio.css',
                'resources/css/repeticion-espaciada.css',
                'resources/css/ahorcado.css',
                'resources/js/app.js',
                'resources/js/welcome.js',
                'resources/js/dark-toggle.js',
                'resources/js/crear-sala.js',
                'resources/js/modo-examen.js',
                'resources/js/sala-configurar.js',
                'resources/js/sala-dashboard.js',
                'resources/js/sala-historial.js',
                'resources/js/sala-play.js',
                'resources/js/sala-reporte.js',
                'resources/js/solo-exam-play.js',
                'resources/js/mapa-mental.js',
                'resources/js/tarjetas-estudio.js',
                'resources/js/repeticion-espaciada.js',
            ],
            refresh: true,
        }),
    ],
});
