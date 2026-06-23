<script>
document.addEventListener('DOMContentLoaded', function() {
    @auth
    fetch('/ajax/gamification/stats')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.gamification) {
                var g = data.gamification;
                var streak = document.getElementById('header-streak');
                var level = document.getElementById('header-level');
                if (streak) streak.textContent = g.current_streak + ' Días';
                if (level) level.textContent = g.level;
            }
        })
        .catch(function() {});
    @endauth

    var btnAbrir = document.getElementById('btn-abrir-menu-movil');
    var btnCerrar = document.getElementById('btn-cerrar-menu-movil');
    var menuMovil = document.getElementById('menu-movil-drawer');
    var fondoOscuro = document.getElementById('fondo-oscuro-menu');

    function abrirMenu() {
        if (menuMovil) menuMovil.classList.add('activo');
        if (fondoOscuro) fondoOscuro.classList.add('activo');
    }

    function cerrarMenu() {
        if (menuMovil) menuMovil.classList.remove('activo');
        if (fondoOscuro) fondoOscuro.classList.remove('activo');
    }

    window.cerrarMenuMovilId = cerrarMenu;

    if (btnAbrir) btnAbrir.addEventListener('click', abrirMenu);
    if (btnCerrar) btnCerrar.addEventListener('click', cerrarMenu);
    if (fondoOscuro) fondoOscuro.addEventListener('click', cerrarMenu);

    var btnTogglePdfs = document.getElementById('btn-toggle-pdfs-movil');
    var seccionPdfs = document.getElementById('seccion-pdfs-movil');
    var iconoFlecha = document.getElementById('icono-flecha-pdfs');

    if (btnTogglePdfs) {
        btnTogglePdfs.addEventListener('click', function() {
            if (seccionPdfs) seccionPdfs.classList.toggle('hidden');
            if (iconoFlecha) iconoFlecha.classList.toggle('rotate-180');
        });
    }

    var spinnerBtn = document.getElementById('user-spinner-btn');
    var dropdown = document.getElementById('user-dropdown');
    var arrow = document.getElementById('spinner-arrow');
    var btnInstallApp = document.getElementById('btn-install-app');

    if (spinnerBtn && dropdown) {
        spinnerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var isOpen = !dropdown.classList.contains('hidden');
            dropdown.classList.toggle('hidden');
            if (arrow) arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
        });
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && e.target !== spinnerBtn) {
                dropdown.classList.add('hidden');
                if (arrow) arrow.style.transform = '';
            }
        });
        if (btnInstallApp) {
            btnInstallApp.addEventListener('click', function() {
                dropdown.classList.add('hidden');
                if (arrow) arrow.style.transform = '';
            });
        }
    }
});
</script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').then(function(reg) {
        reg.addEventListener('updatefound', function() {
            var newWorker = reg.installing;
            newWorker.addEventListener('statechange', function() {
                if (newWorker.state === 'activated') {
                    window.location.reload();
                }
            });
        });
    }).catch(function() {});
    navigator.serviceWorker.ready.then(function(reg) { reg.update(); });
}
</script>
