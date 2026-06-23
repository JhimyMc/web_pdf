<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4A90E2">
    <meta name="description" content="PlayDF — Herramientas de estudio interactivas con Inteligencia Artificial">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192x192.png') }}">
    <title>PlayDF - Inteligencia Artificial en PDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/js/app.js', 'resources/js/welcome.js', 'resources/js/dark-toggle.js'])

    <script>
        window.isLoggedIn = @json(Auth::check());
        window.loginRoute = "{{ route('login') }}";
    </script>
</head>

<body class="cuerpo-aplicacion font-sans min-h-screen flex flex-col justify-between">

    <header
        class="cabecera-principal px-4 md:px-6 py-4 flex flex-row items-center justify-between shadow-md sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <button id="btn-abrir-menu-movil" class="boton-menu-movil md:hidden text-xl p-1 mr-1" title="Abrir menú">
                <i class="fa-solid fa-bars"></i>
            </button>

            @include('partials.logo')

            <div
                class="hidden sm:flex items-center gap-3 ml-2 md:ml-6 racha-nivel-contenedor px-3 py-1 rounded-full text-xs">
                <span class="text-amber-400"><i class="fa-solid fa-fire"></i> Racha: <span id="header-streak">-</span></span>
                <span class="text-blue-400"><i class="fa-solid fa-star"></i> Nivel <span id="header-level">-</span></span>
            </div>
        </div>

        <div class="flex items-center gap-3 md:gap-4">
            <button onclick="toggleTheme()" class="theme-toggle-btn" title="Cambiar tema">
                <i class="fa-solid fa-moon icon-moon"></i>
                <i class="fa-solid fa-sun icon-sun"></i>
            </button>
            @auth
                <div class="relative" id="user-spinner">
                    <button id="user-spinner-btn" class="flex items-center gap-2 text-xs md:text-sm usuario-identificado px-3 py-1.5 rounded-xl hover:bg-white/10 transition-colors">
                        <i class="fa-solid fa-user"></i>
                        <span class="max-w-[100px] md:max-w-none truncate">{{ Auth::user()->name }}</span>
                        <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" id="spinner-arrow"></i>
                    </button>
                    <div id="user-dropdown" class="hidden absolute right-0 top-full mt-2 w-52 rounded-xl shadow-2xl overflow-hidden z-50" style="background: var(--modal-bg); border: 1px solid var(--modal-border);">
                        <div class="px-4 py-3" style="border-bottom: 1px solid var(--modal-border);">
                            <p class="text-xs" style="color: var(--modal-subtext);">Conectado como</p>
                            <p class="text-sm font-semibold truncate" style="color: var(--modal-text);">{{ Auth::user()->name }}</p>
                        </div>
                        <button id="btn-install-app" class="w-full text-left px-4 py-2.5 text-xs transition-colors flex items-center gap-2.5" style="color: #3b82f6;">
                            <i class="fa-solid fa-download"></i> Instalar PlayDF
                        </button>
                        <div style="border-top: 1px solid var(--modal-border);"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-xs text-red-400 transition-colors flex items-center gap-2.5">
                                <i class="fa-solid fa-right-from-bracket"></i> Salir
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="text-xs md:text-sm enlace-autenticacion">Entrar</a>
                <a href="{{ route('register') }}"
                    class="boton-registrarse text-white text-[11px] md:text-xs font-bold px-2.5 md:px-3 py-2 rounded-lg transition-colors">Registrarse</a>
            @endauth
        </div>
    </header>

    <main
        class="contenedor-principal flex-grow w-full max-w-7xl mx-auto p-2 md:p-4 grid grid-cols-1 md:grid-cols-4 gap-4 relative">

        <aside class="hidden md:flex panel-lateral rounded-2xl p-4 flex-col justify-between h-[80vh]">
            <div>
                <h3 class="seccion-subtitulo text-xs font-bold uppercase tracking-wider mb-3">Fuentes Activas</h3>

                <input type="file" id="pdf-file-input" accept=".pdf" class="hidden">

                <button id="btn-cargar-doc"
                    class="boton-accion-principal w-full text-sm font-semibold py-2.5 px-4 rounded-xl transition-colors flex items-center justify-center gap-2 mb-2">
                    <i class="fa-solid fa-plus text-blue-400"></i> Cargar Documento
                </button>

                <a href="{{ route('sala.historial') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors mb-6">
                    <i class="fa-solid fa-clock-rotate-left text-amber-500"></i> Historial de Examenes
                </a>

                <h4 class="seccion-subtitulo-atenuado text-xs font-bold uppercase tracking-wider mb-2">Archivos
                    Recientes</h4>

                <div class="space-y-2 max-h-[380px] overflow-y-auto pr-1">
                    <div class="lista-pdfs-clase space-y-2">
                        @foreach ($documentos as $doc)
                            <div id="doc-item-{{ $doc->id }}"
                                class="item-documento flex items-center justify-between p-2.5 rounded-xl transition-colors">
                                <button onclick="seleccionarDocumento({{ $doc->id }}, '{{ $doc->name }}')"
                                    class="enlace-documento flex items-center gap-2.5 text-xs font-medium overflow-hidden text-ellipsis whitespace-nowrap text-left flex-1 mr-2">
                                    <i class="fa-solid fa-file-pdf text-sm flex-shrink-0"></i>
                                    <span class="truncate" title="{{ $doc->name }}">{{ $doc->name }}</span>
                                </button>
                                <button onclick="eliminarDocumento(event, {{ $doc->id }})"
                                    class="boton-eliminar p-1 px-2 rounded-lg transition-colors"
                                    title="Eliminar documento">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </aside>

        <section class="col-span-1 md:col-span-2 flex flex-col h-[calc(100vh-140px)] md:h-[80vh]">

            <!-- Zona de contenido principal (flex-grow) -->
            <div class="flex-1 min-h-0 relative rounded-2xl overflow-hidden">
                <!-- Drop zone: overlay absoluto -->
                <div id="zona-drop"
                    class="zona-arrastre absolute inset-0 border-2 border-dashed rounded-2xl p-6 md:p-8 flex flex-col items-center justify-center transition-all cursor-pointer z-10">
                    <div class="insignia-pdf font-black text-xl px-4 py-2 rounded-xl mb-3">PDF</div>

                    <p class="text-sm font-semibold texto-blanco text-center hidden md:block">Arrastra tus archivos aquí
                    </p>
                    <p class="text-sm font-semibold texto-blanco text-center block md:hidden">Selecciona tu PDF aquí</p>

                    <p class="text-xs texto-atenuado mt-1 text-center hidden md:block">Sube un PDF para activar la
                        Inteligencia Artificial</p>
                    <p class="text-xs texto-atenuado mt-1 text-center block md:hidden">Toca el botón para abrir un
                        documento</p>

                    <button id="btn-seleccionar-centro"
                        class="nav-boton-subir mt-4 boton-subir-archivos text-white font-bold text-xs py-2 px-5 rounded-xl shadow-lg transition-colors">
                        Seleccionar Archivos
                    </button>
                </div>

                <!-- Pantalla de carga: overlay absoluto -->
                <div id="pantalla-carga"
                    class="pantalla-carga-ia absolute inset-0 rounded-2xl p-8 hidden flex-col items-center justify-center text-center z-20 backdrop-blur-sm">
                    <div class="relative w-20 h-20 mb-4">
                        <div class="anillo-espera-base absolute inset-0 rounded-full border-4"></div>
                        <div class="anillo-espera-activo absolute inset-0 rounded-full border-4 animate-spin"></div>
                        <i class="fa-solid fa-brain text-2xl absolute inset-0 flex items-center justify-center"></i>
                    </div>
                    <p class="text-sm font-bold texto-blanco animate-pulse">Analizando y guardando tu PDF...</p>
                    <p class="text-xs texto-atenuado mt-1 max-w-xs mx-auto">Extrayendo texto para que la IA responda sin
                        demoras</p>
                </div>

                <!-- Chat: flex child que respeta el espacio del search bar -->
                <div id="contenedor-chat"
                    class="chat-contenedor-ia absolute inset-0 rounded-2xl p-4 hidden flex-col z-0">
                    <div id="historial-chat" class="w-full flex-1 min-h-0 overflow-y-auto space-y-3 pr-1 pb-2">
                    </div>
                </div>
            </div>

            <!-- Barra de búsqueda -->
            <div id="wrapper-busqueda"
                class="barra-busqueda w-full relative flex items-center rounded-2xl px-4 py-3 mt-3 transition-all z-10">
                <i class="fa-solid fa-wand-magic-sparkles mr-3 icono-magia"></i>
                <input type="text" id="input-pregunta"
                    class="w-full bg-transparent focus:outline-none text-sm cursor-not-allowed"
                    placeholder="Primero selecciona o sube un PDF..." disabled>
                <button id="btn-enviar-pregunta" class="boton-enviar-deshabilitado p-1" disabled>
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </section>

        <aside class="hidden md:flex panel-lateral rounded-2xl p-4 flex-col space-y-4 h-[80vh]">
            <div>
                <h3 class="seccion-subtitulo text-xs font-bold uppercase tracking-wider mb-3">Sostenibilidad y Repaso
                </h3>
                <div class="space-y-2">
                    <a href="{{ route('srs.index') }}"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-brain text-amber-500"></i> Repetición Espaciada (SRS)
                    </a>
                    <a href="/modo-examen"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-graduation-cap"></i> Modo Examen
                    </a>
                </div>
            </div>

            <div class="divisor-linea my-4"></div>

            <div>
                <h3 class="seccion-subtitulo text-xs font-bold uppercase tracking-wider mb-3">Herramientas IA</h3>
                <div class="space-y-2">
                    <a href="{{ route('mapa-mental.index') }}"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-sitemap text-purple-400"></i> Generar Mapa Mental
                    </a>
                    <a href="{{ route('solo-exam.configurar') }}"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-pen-to-square text-emerald-400"></i> Crear Cuestionario
                    </a>
                    <a href="{{ route('tarjetas-estudio.index') }}"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-layer-group text-pink-400"></i> Tarjetas de Estudio
                    </a>
                    @auth
                    <a href="{{ route('ahorcado.index') }}"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-puzzle-piece text-violet-400"></i> Ahorcado
                    </a>
                    @endauth
                </div>
            </div>

        </aside>

    </main>

    <div id="fondo-oscuro-menu" class="overlay-menu-movil hidden"></div>
    <aside id="menu-movil-drawer" class="menu-movil-contenedor p-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-6">
                <span class="text-lg font-bold text-white">Menú <span
                        class="text-primario-resaltado">PlayDF</span></span>
                <button id="btn-cerrar-menu-movil" class="text-slate-400 hover:text-white p-1 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <button id="btn-cargar-pdf-movil"
                class="boton-cargar-movil-rojo w-full text-xs font-bold py-3 px-4 rounded-xl flex items-center justify-center gap-2 mb-3 shadow-md">
                <i class="fa-solid fa-cloud-arrow-up text-sm"></i> Cargar nuevo PDF
            </button>

            <button id="btn-toggle-pdfs-movil"
                class="boton-accion-principal w-full text-xs font-bold py-2.5 px-4 rounded-xl flex items-center justify-between mb-4">
                <span><i class="fa-solid fa-folder-open text-red-500 mr-2"></i> Mis PDFs Cargados</span>
                <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-300"
                    id="icono-flecha-pdfs"></i>
            </button>

            <div id="seccion-pdfs-movil" class="hidden space-y-2 max-h-[180px] overflow-y-auto mb-4 px-1">
                @if (count($documentos) == 0)
                    <p class="text-[11px] text-slate-500 text-center py-2">No tienes archivos guardados.</p>
                @else
                    @foreach ($documentos as $doc)
                        <div class="item-documento flex items-center justify-between p-2 rounded-xl">
                            <button
                                onclick="seleccionarDocumento({{ $doc->id }}, '{{ $doc->name }}'); cerrarMenuMovilId();"
                                class="enlace-documento flex items-center gap-2 text-xs font-medium overflow-hidden text-ellipsis whitespace-nowrap text-left flex-1 mr-1">
                                <i class="fa-solid fa-file-pdf text-xs flex-shrink-0"></i>
                                <span class="truncate">{{ $doc->name }}</span>
                            </button>
                            <button onclick="eliminarDocumento(event, {{ $doc->id }})"
                                class="boton-eliminar p-1 px-2 rounded-lg">
                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                            </button>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="divisor-linea my-4"></div>

            <h3 class="seccion-subtitulo text-[11px] font-bold uppercase tracking-wider mb-2">Herramientas IA</h3>
            <div class="space-y-2">
                <a href="{{ route('mapa-mental.index') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                    <i class="fa-solid fa-sitemap text-purple-400"></i> Generar Mapa Mental
                </a>
                <a href="{{ route('solo-exam.configurar') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-pen-to-square text-emerald-400"></i> Crear Cuestionario
                </a>
                <a href="{{ route('sala.historial') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-clock-rotate-left text-amber-500"></i> Historial de Examenes
                </a>
                <a href="{{ route('tarjetas-estudio.index') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-layer-group text-pink-400"></i> Tarjetas de Estudio
                </a>
                @auth
                <a href="{{ route('ahorcado.index') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-puzzle-piece text-violet-400"></i> Ahorcado
                </a>
                @endauth
            </div>

            <div class="divisor-linea my-4"></div>

            <div class="flex items-center justify-between px-2 mb-2">
                <span class="seccion-subtitulo text-[11px] font-bold uppercase tracking-wider">Tema</span>
                <button onclick="toggleTheme()" class="theme-toggle-btn" title="Cambiar tema">
                    <i class="fa-solid fa-moon icon-moon"></i>
                    <i class="fa-solid fa-sun icon-sun"></i>
                </button>
            </div>

            <div class="divisor-linea my-4"></div>

            <h3 class="seccion-subtitulo text-[11px] font-bold uppercase tracking-wider mb-2">Repaso</h3>
            <div class="space-y-2">
                <a href="{{ route('srs.index') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-brain text-amber-500"></i> Repetición Espaciada (SRS)
                </a>
                <a href="/modo-examen"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-graduation-cap text-red-500"></i> Modo Examen
                </a>
            </div>
        </div>
    </aside>

    @include('partials.footer')

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
                    <p style="color:var(--modal-subtext);font-size:11px;margin:0;">Haz clic en el ícono de instalación en la barra de direcciones, o en los tres puntos <i class="fa-solid fa-ellipsis-vertical"></i> &gt; "Instalar PlayDF"</p>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Cargar gamificación del header
            @auth
            fetch('/ajax/gamification/stats')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.gamification) {
                        const g = data.gamification;
                        document.getElementById('header-streak').textContent = g.current_streak + ' Días';
                        document.getElementById('header-level').textContent = g.level;
                    }
                })
                .catch(() => {});

            // Cargar notificaciones pendientes
            fetch('/ajax/notifications/pending')
                .then(r => r.json())
                .then(data => {
                    if (data.notifications && data.notifications.length > 0 && !document.getElementById('srs-notifications-banner')) {
                        const banner = document.createElement('div');
                        banner.id = 'srs-notifications-banner';
                        banner.className = 'fixed bottom-4 right-4 z-50 max-w-sm';

                        function dismissBanner() {
                            banner.style.transition = 'opacity 0.3s, transform 0.3s';
                            banner.style.opacity = '0';
                            banner.style.transform = 'translateY(10px)';
                            setTimeout(() => banner.remove(), 300);
                        }

                        data.notifications.forEach((n, i) => {
                            const card = document.createElement('div');
                            card.className = 'mb-2 p-3 rounded-xl shadow-lg border flex items-center gap-3 cursor-pointer transition-all hover:scale-[1.02] relative';
                            card.style.backgroundColor = n.color + '15';
                            card.style.borderColor = n.color + '40';
                            card.innerHTML = `
                                <i class="fa-solid fa-${n.icon}" style="color:${n.color};font-size:1.2rem"></i>
                                <div class="flex-1">
                                    <div class="text-xs font-bold" style="color:${n.color}">${n.title}</div>
                                    <div class="text-[11px] text-slate-400">${n.message}</div>
                                </div>
                                <button class="absolute top-1.5 right-1.5 text-slate-500 hover:text-white transition-colors p-1 text-[10px]" title="Cerrar">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            `;
                            // Click en la card (excepto la X) navega a la URL
                            card.addEventListener('click', (e) => {
                                if (!e.target.closest('button')) {
                                    window.location.href = n.url;
                                }
                            });
                            // Click en la X cierra solo esa notificación
                            const closeBtn = card.querySelector('button');
                            closeBtn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                card.style.transition = 'opacity 0.2s, transform 0.2s';
                                card.style.opacity = '0';
                                card.style.transform = 'translateX(20px)';
                                setTimeout(() => {
                                    card.remove();
                                    if (banner.children.length === 0) dismissBanner();
                                }, 200);
                            });
                            banner.appendChild(card);
                        });
                        document.body.appendChild(banner);
                    }
                })
                .catch(() => {});
            @endauth

            const btnAbrir = document.getElementById('btn-abrir-menu-movil');
            const btnCerrar = document.getElementById('btn-cerrar-menu-movil');
            const menuMovil = document.getElementById('menu-movil-drawer');
            const fondoOscuro = document.getElementById('fondo-oscuro-menu');

            const btnTogglePdfs = document.getElementById('btn-toggle-pdfs-movil');
            const seccionPdfs = document.getElementById('seccion-pdfs-movil');
            const iconoFlecha = document.getElementById('icono-flecha-pdfs');

            function abrirMenu() {
                menuMovil.classList.add('activo');
                fondoOscuro.classList.add('activo');
            }

            function cerrarMenu() {
                menuMovil.classList.remove('activo');
                fondoOscuro.classList.remove('activo');
            }

            window.cerrarMenuMovilId = cerrarMenu;

            if (btnAbrir) btnAbrir.addEventListener('click', abrirMenu);
            if (btnCerrar) btnCerrar.addEventListener('click', cerrarMenu);
            if (fondoOscuro) fondoOscuro.addEventListener('click', cerrarMenu);

            if (btnTogglePdfs) {
                btnTogglePdfs.addEventListener('click', () => {
                    seccionPdfs.classList.toggle('hidden');
                    iconoFlecha.classList.toggle('rotate-180');
                });
            }
        });
        
    </script>
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
        var modal = document.getElementById('pwa-manual-modal');
        var modalClose = document.getElementById('pwa-modal-close');
        var modalOk = document.getElementById('pwa-modal-ok');
        var spinnerBtn = document.getElementById('user-spinner-btn');
        var dropdown = document.getElementById('user-dropdown');
        var arrow = document.getElementById('spinner-arrow');
        var btnInstallApp = document.getElementById('btn-install-app');

        function showModal() { modal.style.display = 'flex'; }
        function hideModal() { modal.style.display = 'none'; }

        function tryInstall() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choice) {
                    if (choice.outcome === 'accepted') console.log('PWA instalada');
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
        btnCancel.addEventListener('click', function() { banner.classList.add('hidden'); });
        btnClose.addEventListener('click', function() { banner.classList.add('hidden'); });
        modalClose.addEventListener('click', hideModal);
        modalOk.addEventListener('click', hideModal);
        modal.addEventListener('click', function(e) { if (e.target === modal) hideModal(); });

        if (spinnerBtn && dropdown) {
            spinnerBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = !dropdown.classList.contains('hidden');
                dropdown.classList.toggle('hidden');
                arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
            });
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target) && e.target !== spinnerBtn) {
                    dropdown.classList.add('hidden');
                    arrow.style.transform = '';
                }
            });
            if (btnInstallApp) {
                btnInstallApp.addEventListener('click', function() {
                    dropdown.classList.add('hidden');
                    arrow.style.transform = '';
                    tryInstall();
                });
            }
        }
    })();
    </script>
</body>

</html>
