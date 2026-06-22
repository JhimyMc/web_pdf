/**
 * dark-toggle.js — Toggle de modo oscuro / modo claro
 * Persiste la preferencia en localStorage y aplica la clase en <html>
 */
document.addEventListener('DOMContentLoaded', () => {
    const html = document.documentElement;
    const STORAGE_KEY = 'playdf-theme';

    // Cargar tema guardado o detectar preferencia del sistema
    function getInitialTheme() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'light' || saved === 'dark') return saved;
        // Si no hay guardado, usar preferencia del sistema
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            return 'light';
        }
        return 'dark';
    }

    function applyTheme(theme) {
        if (theme === 'light') {
            html.classList.add('light-mode');
        } else {
            html.classList.remove('light-mode');
        }
        localStorage.setItem(STORAGE_KEY, theme);
    }

    // Aplicar tema inicial
    const currentTheme = getInitialTheme();
    applyTheme(currentTheme);

    // Función global para toggle
    window.toggleTheme = function () {
        const isLight = html.classList.contains('light-mode');
        applyTheme(isLight ? 'dark' : 'light');
    };

    // Escuchar cambios del sistema
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem(STORAGE_KEY)) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }
});
