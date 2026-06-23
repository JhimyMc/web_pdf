<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#000000">
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

    @include('partials.header-unified')

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

    @include('partials.drawer-unified')

    @include('partials.footer')

    @include('partials.scripts-unified')

</body>

</html>
