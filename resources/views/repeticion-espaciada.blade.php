<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4A90E2">
    <meta name="description" content="Sistema de repetición espaciada con IA — PlayDF">
    <title>Repetición Espaciada — PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/css/repeticion-espaciada.css', 'resources/js/app.js', 'resources/js/repeticion-espaciada.js', 'resources/js/dark-toggle.js'])

    <script>
        window.isLoggedIn = @json(Auth::check());
        window.loginRoute = "{{ route('login') }}";
        window.srsSetsIniciales = @json($setsData);
    </script>
</head>

<body class="srs-cuerpo font-sans min-h-screen flex flex-col">

    {{-- ══════════════════════════════════════════════════════
         CABECERA — exactamente igual a welcome.blade.php
    ══════════════════════════════════════════════════════ --}}
    <header
        class="cabecera-principal px-4 md:px-6 py-4 flex flex-row items-center justify-between shadow-md sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <button id="btn-abrir-menu-movil" class="boton-menu-movil md:hidden text-xl p-1 mr-1" title="Abrir menú">
                <i class="fa-solid fa-bars"></i>
            </button>

            @include('partials.logo')

            <div
                class="hidden sm:flex items-center gap-3 ml-2 md:ml-6 racha-nivel-contenedor px-3 py-1 rounded-full text-xs">
                <span class="text-amber-400">🔥 Racha: 5 Días</span>
                <span class="text-blue-400">⭐ Nivel 12</span>
            </div>
        </div>

        <div class="flex items-center gap-3 md:gap-4">
            <button onclick="toggleTheme()" class="theme-toggle-btn" title="Cambiar tema">
                <i class="fa-solid fa-moon icon-moon"></i>
                <i class="fa-solid fa-sun icon-sun"></i>
            </button>
            @auth
                <span class="text-xs md:text-sm usuario-identificado max-w-[120px] md:max-w-none truncate">
                    <i class="fa-solid fa-user mr-1 md:mr-2"></i>{{ Auth::user()->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="boton-salir text-xs hover:underline">Salir</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-xs md:text-sm enlace-autenticacion">Entrar</a>
                <a href="{{ route('register') }}"
                    class="boton-registrarse text-white text-[11px] md:text-xs font-bold px-2.5 md:px-3 py-2 rounded-lg transition-colors">Registrarse</a>
            @endauth
        </div>
    </header>

    {{-- ══════════════════════════════════════════════════════
         BREADCRUMB / NAVEGACIÓN
    ══════════════════════════════════════════════════════ --}}
    <div class="px-4 md:px-8 pt-4 pb-1">
        <div class="flex items-center gap-2 text-xs" style="color: var(--color-gris-oscuro)">
            <a href="/" class="hover:text-white transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-house text-[10px]"></i> PlayDF
            </a>
            <i class="fa-solid fa-chevron-right text-[9px]"></i>
            <span style="color: var(--color-gris-claro)">
                <i class="fa-solid fa-brain text-amber-400 mr-1"></i>Repetición Espaciada
            </span>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
    ══════════════════════════════════════════════════════ --}}
    <main class="srs-main flex-1">

        {{-- ── Estado: VACÍO (sin sets) ─────────────────────── --}}
        <div id="srs-estado-vacio" class="srs-estado-vacio">
            <div class="srs-icono-vacio">
                <i class="fa-solid fa-brain"></i>
            </div>
            <div>
                <h2 class="srs-titulo-vacio">Repetición Espaciada</h2>
                <p class="srs-subtitulo-vacio">
                    Sincroniza tus tarjetas de estudio con el algoritmo SM-2 para optimizar tu repaso.
                    Las tarjetas se programan automáticamente según tu dificultad percibida.
                </p>
            </div>
            @auth
                @if(count($setsData) > 0)
                    <button id="srs-btn-sincronizar-vacio" class="srs-btn-primario">
                        <i class="fa-solid fa-rotate"></i>
                        Sincronizar Tarjetas
                    </button>
                @endif
            @else
                <a href="{{ route('login') }}" class="srs-btn-primario" style="text-decoration:none">
                    <i class="fa-solid fa-lock"></i>
                    Inicia sesión para comenzar
                </a>
            @endauth
        </div>

        {{-- ── Estado: CARGANDO ────────────────────────────────── --}}
        <div id="srs-estado-cargando" class="srs-cargando oculto">
            <div class="srs-spinner">
                <div class="srs-spinner-ring"></div>
                <div class="srs-spinner-arc"></div>
                <div class="srs-spinner-arc-2"></div>
            </div>
            <p class="srs-cargando-titulo">Preparando tu repaso...</p>
            <p class="srs-cargando-sub">Cargando tarjetas programadas para hoy.</p>
        </div>

        {{-- ── Estado: LISTA DE SETS ───────────────────────────── --}}
        <div id="srs-estado-lista" class="srs-contenedor oculto">

            {{-- Stats globales --}}
            <div id="srs-stats-bar" class="srs-stats-bar">
                <div class="srs-stat-item">
                    <div class="srs-stat-num" id="srs-stat-due">0</div>
                    <div class="srs-stat-label">Para hoy</div>
                </div>
                <div class="srs-stat-divider"></div>
                <div class="srs-stat-item">
                    <div class="srs-stat-num" id="srs-stat-mastered">0</div>
                    <div class="srs-stat-label">Dominadas</div>
                </div>
                <div class="srs-stat-divider"></div>
                <div class="srs-stat-item">
                    <div class="srs-stat-num" id="srs-stat-total">0</div>
                    <div class="srs-stat-label">Total</div>
                </div>
            </div>

            <div class="srs-encabezado">
                <div>
                    <div class="srs-encabezado-titulo">Tus Sets — Repetición Espaciada</div>
                    <div class="srs-encabezado-sub">Selecciona un set para comenzar a repasar</div>
                </div>
                <button onclick="srsSincronizarTodos()" class="srs-btn-primario" style="font-size:0.82rem; padding:0.6rem 1.2rem">
                    <i class="fa-solid fa-rotate"></i> Sincronizar
                </button>
            </div>

            <div id="srs-lista-sets" class="srs-lista-sets"></div>
        </div>

        {{-- ── Estado: REVISIÓN DE TARJETAS ───────────────────── --}}
        <div id="srs-estado-revision" class="srs-contenedor oculto">
            <div class="srs-revision-header">
                <div class="flex items-center gap-3">
                    <button id="srs-btn-volver" class="srs-btn-volver">
                        <i class="fa-solid fa-arrow-left"></i> Volver
                    </button>
                    <div>
                        <h2 id="srs-revision-titulo" class="srs-revision-titulo">Repasando</h2>
                        <div id="srs-revision-sub" class="srs-revision-sub"></div>
                    </div>
                </div>
                <div class="srs-revision-counter" id="srs-revision-counter">0 / 0</div>
            </div>

            {{-- Barra de progreso --}}
            <div id="srs-progress-container" class="srs-progress-container">
                <div class="srs-progress-bar">
                    <div id="srs-progress-fill" class="srs-progress-fill" style="width: 0%;"></div>
                </div>
            </div>

            {{-- Tarjeta actual --}}
            <div id="srs-card-container"></div>

            {{-- Botones de calidad SM-2 --}}
            <div id="srs-quality-buttons" class="srs-quality-buttons oculto">
                <button class="srs-q-btn srs-q-again" data-quality="0" title="No recuerdo (Reiniciar)">
                    <i class="fa-solid fa-xmark"></i>
                    <span>Otra vez</span>
                    <small>1 día</small>
                </button>
                <button class="srs-q-btn srs-q-hard" data-quality="1" title="Difícil">
                    <i class="fa-solid fa-doubledown"></i>
                    <span>Difícil</span>
                    <small id="srs-q-hard-interval">1 día</small>
                </button>
                <button class="srs-q-btn srs-q-good" data-quality="2" title="Bien">
                    <i class="fa-solid fa-check"></i>
                    <span>Bien</span>
                    <small id="srs-q-good-interval">3 días</small>
                </button>
                <button class="srs-q-btn srs-q-easy" data-quality="3" title="Fácil">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Fácil</span>
                    <small id="srs-q-easy-interval">7 días</small>
                </button>
            </div>

            {{-- Completado --}}
            <div id="srs-completado" class="srs-completado oculto">
                <div class="srs-completado-icono">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3 class="srs-completado-titulo">¡Repaso completado!</h3>
                <p class="srs-completado-texto">Has revisado todas las tarjetas pendientes para hoy.</p>
                <button onclick="srsVolverALista()" class="srs-btn-primario">
                    <i class="fa-solid fa-arrow-left"></i> Volver a la lista
                </button>
            </div>
        </div>

    </main>

    {{-- ══════════════════════════════════════════════════════
         TOAST DE NOTIFICACIÓN
    ══════════════════════════════════════════════════════ --}}
    <div id="srs-toast" class="srs-toast oculto">
        <i class="fa-solid fa-circle-check" style="color: #22c55e; flex-shrink:0;"></i>
        <span id="srs-toast-msg">Operación exitosa</span>
    </div>

    {{-- ══════════════════════════════════════════════════════
         MENÚ MÓVIL (drawer)
    ══════════════════════════════════════════════════════ --}}
    <div id="fondo-oscuro-menu" class="overlay-menu-movil hidden"></div>
    <aside id="menu-movil-drawer" class="menu-movil-contenedor p-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-6">
                <span class="text-lg font-bold text-white">Menú <span
                        class="primario-resaltado">PlayDF</span></span>
                <button id="btn-cerrar-menu-movil" class="text-slate-400 hover:text-white p-1 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="divisor-linea my-4"></div>

            <h3 class="seccion-subtitulo text-[11px] font-bold uppercase tracking-wider mb-2">Herramientas IA</h3>
            <div class="space-y-2">
                <a href="/"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-file-lines text-blue-400"></i> Chat con PDF
                </a>
                <a href="{{ route('mapa-mental.index') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-sitemap text-purple-400"></i> Mapa Mental
                </a>
                <a href="/docente/crear-sala"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-list-check text-emerald-400"></i> Crear Cuestionario
                </a>
                <a href="{{ route('tarjetas-estudio.index') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-layer-group text-pink-400"></i> Tarjetas de Estudio
                </a>
            </div>

            <div class="divisor-linea my-4"></div>

            <h3 class="seccion-subtitulo text-[11px] font-bold uppercase tracking-wider mb-2">Repaso</h3>
            <div class="space-y-2">
                <a href="{{ route('srs.index') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-brain text-amber-400"></i> Repetición Espaciada (SRS)
                </a>
                <a href="/modo-examen"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-graduation-cap text-red-500"></i> Modo Examen
                </a>
            </div>
        </div>
    </aside>

    @include('partials.footer')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btnAbrir = document.getElementById('btn-abrir-menu-movil');
            const btnCerrar = document.getElementById('btn-cerrar-menu-movil');
            const menuMovil = document.getElementById('menu-movil-drawer');
            const fondoOscuro = document.getElementById('fondo-oscuro-menu');

            function abrirMenu() {
                menuMovil.classList.add('activo');
                fondoOscuro.classList.add('activo');
            }

            function cerrarMenu() {
                menuMovil.classList.remove('activo');
                fondoOscuro.classList.remove('activo');
            }

            if (btnAbrir) btnAbrir.addEventListener('click', abrirMenu);
            if (btnCerrar) btnCerrar.addEventListener('click', cerrarMenu);
            if (fondoOscuro) fondoOscuro.addEventListener('click', cerrarMenu);
        });
    </script>

</body>

</html>
