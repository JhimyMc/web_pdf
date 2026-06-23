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
    <meta name="description" content="Tarjetas de estudio espaciadas con IA — PlayDF">
    <title>Tarjetas de Estudio — PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/css/tarjetas-estudio.css', 'resources/js/app.js', 'resources/js/tarjetas-estudio.js', 'resources/js/dark-toggle.js'])

    <script>
        window.isLoggedIn = @json(Auth::check());
        window.loginRoute = "{{ route('login') }}";

        // Pasar los sets de tarjetas al JS (formateados desde el controlador)
        window.teSetsIniciales = @json($setsData);
    </script>
</head>

<body class="te-cuerpo font-sans min-h-screen flex flex-col">

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
                <i class="fa-solid fa-layer-group text-pink-400 mr-1"></i>Tarjetas de Estudio
            </span>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
    ══════════════════════════════════════════════════════ --}}
    <main class="te-main flex-1">

        {{-- ── Estado: VACÍO (sin tarjetas) ─────────────────────── --}}
        <div id="te-estado-vacio" class="te-estado-vacio">
            <div class="te-icono-vacio">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <h2 class="te-titulo-vacio">Tarjetas de Estudio</h2>
                <p class="te-subtitulo-vacio">
                    Genera tarjetas de estudio con preguntas y respuestas a partir de tus PDFs.
                    Ideal para repasar conceptos clave de forma rápida y efectiva.
                </p>
            </div>
            @auth
                <button id="te-btn-nuevo-vacio" class="te-btn-generar">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Generar Tarjetas
                </button>
            @else
                <a href="{{ route('login') }}" class="te-btn-generar" style="text-decoration:none">
                    <i class="fa-solid fa-lock"></i>
                    Inicia sesión para generar
                </a>
            @endauth
        </div>

        {{-- ── Estado: CARGANDO ────────────────────────────────── --}}
        <div id="te-estado-cargando" class="te-cargando oculto">
            <div class="te-spinner">
                <div class="te-spinner-ring"></div>
                <div class="te-spinner-arc"></div>
                <div class="te-spinner-arc-2"></div>
            </div>
            <p class="te-cargando-titulo">Generando tus tarjetas de estudio...</p>
            <p class="te-cargando-sub">La IA está extrayendo conceptos clave del documento.</p>
        </div>

        {{-- ── Estado: LISTA DE SETS ───────────────────────────── --}}
        <div id="te-estado-lista" class="te-contenedor oculto">
            <div class="te-encabezado">
                <div>
                    <div class="te-encabezado-titulo">Tus Tarjetas de Estudio</div>
                    <div class="te-encabezado-sub">Selecciona un set para comenzar a repasar</div>
                </div>
                <button onclick="window.abrirModal()" class="te-btn-generar" style="font-size:0.82rem; padding:0.6rem 1.2rem">
                    <i class="fa-solid fa-plus"></i> Nuevo Set
                </button>
            </div>
            <div id="te-lista-sets" class="te-lista-sets"></div>
        </div>

        {{-- ── Estado: TARJETAS INDIVIDUALES ───────────────────── --}}
        <div id="te-estado-cards" class="te-contenedor oculto">
            <div class="te-cards-header">
                <div class="flex items-center gap-3">
                    <button id="te-btn-volver" class="te-btn-volver">
                        <i class="fa-solid fa-arrow-left"></i> Volver
                    </button>
                    <h2 id="te-cards-titulo" class="te-cards-titulo">Tarjetas</h2>
                </div>
                <div class="flex items-center gap-2">
                    <button id="te-btn-shuffle" class="te-nav-btn" title="Barajar tarjetas">
                        <i class="fa-solid fa-shuffle"></i>
                    </button>
                    <span id="te-cards-counter" class="te-cards-counter">0 / 0</span>
                </div>
            </div>

            {{-- ── Progreso de repaso ─────────────────────────── --}}
            <div id="te-progress-container" class="te-progress-container">
                <div class="te-progress-info">
                    <span class="te-progress-label"><i class="fa-regular fa-circle-check"></i> Repasadas</span>
                    <span id="te-progress-text" class="te-progress-text">0 / 0</span>
                </div>
                <div class="te-progress-bar">
                    <div id="te-progress-fill" class="te-progress-fill" style="width: 0%;"></div>
                </div>
            </div>

            <div id="te-cards-container"></div>

            <div class="te-nav">
                <button id="te-btn-prev" class="te-nav-btn" disabled>
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button id="te-btn-next" class="te-nav-btn">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>

    </main>

    {{-- ══════════════════════════════════════════════════════
         MODAL — Seleccionar PDF para generar tarjetas
    ══════════════════════════════════════════════════════ --}}
    <div id="te-modal-overlay" class="te-modal-overlay oculto">
        <div class="te-modal">
            <div class="flex items-center justify-between mb-2">
                <h2 class="te-modal-titulo flex items-center gap-2.5">
                    <i class="fa-solid fa-layer-group text-pink-500"></i> Generar Tarjetas
                </h2>
                <button onclick="window.cerrarModal()" class="text-zinc-400 hover:text-white transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <p class="te-modal-desc">Selecciona un PDF de tu lista o sube uno nuevo para generar las tarjetas.</p>

            <form id="te-form-generar" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-2">
                        Seleccionar PDF Existente
                    </label>
                    <select id="te-select-documento" class="te-modal-select">
                        <option value="">-- Elige un documento --</option>
                        @foreach ($documentos as $doc)
                            <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="te-modal-divisor">
                    <div class="te-modal-divisor-linea"></div>
                    <span class="te-modal-divisor-texto">O Sube Uno Nuevo</span>
                    <div class="te-modal-divisor-linea"></div>
                </div>

                <div id="te-zona-subida" class="te-zona-subida">
                    <input type="file" id="te-file-rapido" accept=".pdf" class="hidden">
                    <div class="te-zona-subida-icono">
                        <i class="fa-solid fa-cloud-arrow-up text-lg"></i>
                    </div>
                    <span id="te-text-upload" class="te-zona-subida-texto">Arrastra o haz clic para subir un PDF</span>
                </div>

                <div class="te-modal-acciones">
                    <button type="button" onclick="window.cerrarModal()" class="te-btn-cancelar">Cancelar</button>
                    <button type="submit" class="te-btn-generar" style="padding:0.6rem 1.2rem; font-size:0.82rem">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generar Tarjetas
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         TOAST DE NOTIFICACIÓN
    ══════════════════════════════════════════════════════ --}}
    <div id="te-toast" class="te-toast oculto">
        <i class="fa-solid fa-circle-check" style="color: #22c55e; flex-shrink:0;"></i>
        <span id="te-toast-msg">Cambios guardados</span>
    </div>

    @include('partials.drawer-unified')

    {{-- Footer --}}
    @include('partials.footer')

    @include('partials.scripts-unified')

</body>

</html>
