<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/icon-192x192.png') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="theme-color" content="#4A90E2">
        <meta name="description" content="Inicia sesión o regístrate en PlayDF — Herramientas de estudio con IA">

        <title>{{ config('app.name', 'PlayDF') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/dark-toggle.js'])
    </head>
    <body class="font-sans text-gray-300 antialiased bg-playdf-dark">

        <div id="pwa-install-banner" class="hidden fixed top-0 left-0 right-0 z-50 p-3 md:p-4">
            <div class="max-w-lg mx-auto bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl shadow-2xl p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fa-solid fa-download text-white text-lg"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-bold leading-tight">Instalar PlayDF</p>
                        <p id="pwa-banner-desc" class="text-blue-100 text-[11px] mt-0.5">Accede más rápido desde tu pantalla de inicio</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button id="pwa-btn-cancel" class="text-blue-200 hover:text-white text-xs font-medium px-3 py-2 rounded-xl transition-colors">
                            Por ahora no
                        </button>
                        <button id="pwa-btn-install" class="bg-white text-blue-700 text-xs font-bold px-4 py-2 rounded-xl hover:bg-blue-50 transition-colors">
                            Instalar
                        </button>
                    </div>
                    <button id="pwa-btn-close" class="absolute top-2 right-2 text-blue-200 hover:text-white text-sm p-1 rounded-lg transition-colors md:relative md:top-auto md:right-auto">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div id="pwa-manual-instructions" class="hidden mt-3 pt-3 border-t border-white/20">
                    <p class="text-white text-[11px] font-semibold mb-2">Instalación manual:</p>
                    <div class="text-blue-100 text-[11px] space-y-1">
                        <p><strong>Opera:</strong> Haz clic en el ícono de instalación en la barra de direcciones (ícono de monitor con flecha)</p>
                        <p><strong>Safari (iPhone):</strong> Toca el botón de compartir <i class="fa-solid fa-arrow-up-from-bracket"></i> y luego "Añadir a pantalla de inicio"</p>
                        <p><strong>Firefox:</strong> Haz clic en los tres puntos y selecciona "Instalar"</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="pwa-manual-modal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);">
            <div style="background:var(--modal-bg);border:1px solid var(--modal-border);border-radius:16px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);padding:24px;max-width:24rem;width:100%;position:relative;">
                <button id="pwa-modal-close" style="position:absolute;top:12px;right:12px;color:var(--modal-subtext);background:none;border:none;cursor:pointer;font-size:18px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div style="text-align:center;margin-bottom:16px;">
                    <div style="width:56px;height:56px;background:rgba(59,130,246,0.15);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                        <i class="fa-solid fa-download" style="color:#3b82f6;font-size:24px;"></i>
                    </div>
                    <h3 style="color:var(--modal-text);font-weight:700;font-size:18px;margin:0;">Instalar PlayDF</h3>
                    <p style="color:var(--modal-subtext);font-size:12px;margin-top:4px;">Sigue estos pasos según tu navegador</p>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div style="background:var(--modal-card-bg);border-radius:12px;padding:12px;">
                        <p style="color:#3b82f6;font-size:12px;font-weight:700;margin:0 0 4px;"><i class="fa-brands fa-opera"></i> Opera / Opera GX</p>
                        <p style="color:var(--modal-subtext);font-size:11px;margin:0;">Haz clic en el ícono de monitor con flecha en la barra de direcciones, o ve al menú <i class="fa-solid fa-ellipsis-vertical"></i> &gt; "Instalar aplicación"</p>
                    </div>
                    <div style="background:var(--modal-card-bg);border-radius:12px;padding:12px;">
                        <p style="color:#3b82f6;font-size:12px;font-weight:700;margin:0 0 4px;"><i class="fa-brands fa-chrome"></i> Chrome</p>
                        <p style="color:var(--modal-subtext);font-size:11px;margin:0;">Haz clic en los tres puntos <i class="fa-solid fa-ellipsis-vertical"></i> &gt; "Instalar PlayDF"</p>
                    </div>
                    <div style="background:var(--modal-card-bg);border-radius:12px;padding:12px;">
                        <p style="color:#3b82f6;font-size:12px;font-weight:700;margin:0 0 4px;"><i class="fa-brands fa-safari"></i> Safari (iPhone)</p>
                        <p style="color:var(--modal-subtext);font-size:11px;margin:0;">Toca el botón de compartir <i class="fa-solid fa-arrow-up-from-bracket"></i> &gt; "Añadir a pantalla de inicio"</p>
                    </div>
                    <div style="background:var(--modal-card-bg);border-radius:12px;padding:12px;">
                        <p style="color:#3b82f6;font-size:12px;font-weight:700;margin:0 0 4px;"><i class="fa-brands fa-edge"></i> Edge</p>
                        <p style="color:var(--modal-subtext);font-size:11px;margin:0;">Haz clic en los tres puntos <i class="fa-solid fa-ellipsis-horizontal"></i> &gt; "Aplicaciones" &gt; "Instalar este sitio web como aplicación"</p>
                    </div>
                </div>
                <button id="pwa-modal-ok" style="width:100%;margin-top:16px;background:#2563eb;color:white;border:none;padding:10px;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;">Entendido</button>
            </div>
        </div>

        <style>
            :root {
                --modal-bg: #1e293b;
                --modal-border: #334155;
                --modal-text: #ffffff;
                --modal-subtext: #94a3b8;
                --modal-card-bg: rgba(51,65,85,0.5);
            }
            html.light-mode {
                --modal-bg: #ffffff;
                --modal-border: #e2e8f0;
                --modal-text: #1e293b;
                --modal-subtext: #64748b;
                --modal-card-bg: #f1f5f9;
            }
        </style>

        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            
            <div class="mb-6 flex justify-center">
                @include('partials.logo')
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-playdf-card shadow-lg overflow-hidden sm:rounded-2xl border border-gray-800">
                {{ $slot }}
            </div>
        </div>
        <button onclick="toggleTheme()" class="theme-toggle-floating" title="Cambiar tema">
            <i class="fa-solid fa-moon icon-moon"></i>
            <i class="fa-solid fa-sun icon-sun"></i>
        </button>

        <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').then(function(reg) {
                console.log('SW registrado, scope:', reg.scope);
            }).catch(function(err) {
                console.log('Error SW:', err);
            });
        }

        (function() {
            var deferredPrompt = null;
            var banner = document.getElementById('pwa-install-banner');
            var btnInstall = document.getElementById('pwa-btn-install');
            var btnCancel = document.getElementById('pwa-btn-cancel');
            var btnClose = document.getElementById('pwa-btn-close');
            var bannerDesc = document.getElementById('pwa-banner-desc');
            var manualInstructions = document.getElementById('pwa-manual-instructions');
            var modal = document.getElementById('pwa-manual-modal');
            var modalClose = document.getElementById('pwa-modal-close');
            var modalOk = document.getElementById('pwa-modal-ok');

            function showModal() { modal.style.display = 'flex'; }
            function hideModal() { modal.style.display = 'none'; }
            function dismissBanner() { banner.classList.add('hidden'); localStorage.setItem('pwa-install-dismissed', '1'); }

            function tryInstall() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function(choice) {
                        if (choice.outcome === 'accepted') {
                            console.log('PWA instalada');
                            dismissBanner();
                        }
                        deferredPrompt = null;
                    });
                } else {
                    showModal();
                }
            }

            window.addEventListener('beforeinstallprompt', function(e) {
                e.preventDefault();
                deferredPrompt = e;
                console.log('beforeinstallprompt capturado');
            });

            window.addEventListener('appinstalled', function() {
                deferredPrompt = null;
                console.log('PWA instalada exitosamente');
            });

            btnInstall.addEventListener('click', tryInstall);
            btnCancel.addEventListener('click', dismissBanner);
            btnClose.addEventListener('click', dismissBanner);
            modalClose.addEventListener('click', hideModal);
            modalOk.addEventListener('click', hideModal);
            modal.addEventListener('click', function(e) { if (e.target === modal) hideModal(); });
        })();
        </script>
    </body>
</html>