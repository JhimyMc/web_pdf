{{-- C:\laragon\www\web-pdf\resources\views\mapa-mental.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4A90E2">
    <meta name="description" content="Genera mapas mentales con IA — PlayDF">
    <title>Mapa Mental — PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://d3js.org/d3.v7.min.js"></script>

    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/css/mapa-mental.css', 'resources/js/app.js', 'resources/js/mapa-mental.js', 'resources/js/dark-toggle.js'])

    <script>
        window.isLoggedIn = @json(Auth::check());
        window.loginRoute = "{{ route('login') }}";

        {{-- Pasar el mapa actual al JS (null si no existe) --}}
        @if ($mapaActual)
            window.mmMapaInicial = {
                id: {{ $mapaActual->id }},
                map_data: @json($mapaActual->map_data),
                titulo: @json($mapaActual->title),
                prompt_original: @json($mapaActual->prompt_original),
            };
        @else
            window.mmMapaInicial = null;
        @endif
    </script>
</head>

<body class="cuerpo-aplicacion mm-cuerpo font-sans min-h-screen flex flex-col">

    {{-- ══════════════════════════════════════════════════════
         CABECERA — exactamente igual a welcome.blade.php
    ══════════════════════════════════════════════════════ --}}
    <header
        class="cabecera-principal px-4 md:px-6 py-4 flex flex-row items-center justify-between shadow-md sticky top-0 z-40">
        <div class="flex items-center gap-3">
            {{-- Botón menú móvil --}}
            <button id="btn-abrir-menu-movil" class="boton-menu-movil md:hidden text-xl p-1 mr-1" title="Abrir menú">
                <i class="fa-solid fa-bars"></i>
            </button>

            {{-- Logo --}}
            @include('partials.logo')

            {{-- Racha / Nivel --}}
            <div
                class="hidden sm:flex items-center gap-3 ml-2 md:ml-6 racha-nivel-contenedor px-3 py-1 rounded-full text-xs">
                <span class="text-amber-400">🔥 Racha: 5 Días</span>
                <span class="text-blue-400">⭐ Nivel 12</span>
            </div>
        </div>

        {{-- Auth / Usuario --}}
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
                <i class="fa-solid fa-sitemap text-purple-400 mr-1"></i>Mapa Mental
            </span>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
    ══════════════════════════════════════════════════════ --}}
    <main class="mm-main flex-1">

        {{-- ── Estado: VACÍO (sin mapa generado) ─────────────── --}}
        <div id="mm-estado-vacio" class="mm-estado-vacio">
            <div class="mm-icono-vacio">
                <i class="fa-solid fa-sitemap"></i>
            </div>
            <div>
                <h2 class="mm-titulo-vacio">Mapa Mental con IA</h2>
                <p class="mm-subtitulo-vacio">
                    Escribe cualquier tema y la IA generará un mapa mental visual,
                    estructurado y editable en segundos.
                </p>
            </div>
            @auth
                <button id="mm-btn-nuevo-vacio" class="mm-btn-generar">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Generar Diagrama
                </button>
            @else
                <a href="{{ route('login') }}" class="mm-btn-generar" style="text-decoration:none">
                    <i class="fa-solid fa-lock"></i>
                    Inicia sesión para generar
                </a>
            @endauth
        </div>

        {{-- ── Estado: CARGANDO ────────────────────────────────── --}}
        <div id="mm-estado-cargando" class="mm-cargando oculto">
            <div class="mm-spinner">
                <div class="mm-spinner-ring"></div>
                <div class="mm-spinner-arc"></div>
                <div class="mm-spinner-arc-2"></div>
            </div>
            <p class="mm-cargando-titulo">Generando tu mapa mental...</p>
            <p class="mm-cargando-sub">La IA está estructurando los conceptos, un momento.</p>
        </div>

        {{-- ── Estado: MAPA ACTIVO ─────────────────────────────── --}}
        <div id="mm-estado-mapa" class="w-full max-w-5xl mx-auto oculto" style="animation: mm-fade-in 0.5s ease both;">

            {{-- Título del mapa --}}
            <h2 id="mm-titulo-mapa" class="mm-titulo-mapa">Mapa Mental</h2>
            <p class="mm-meta-mapa">
                Haz clic en <strong>Editar</strong> para modificar cualquier nodo del diagrama.
            </p>

            {{-- Barra de acciones --}}
            <div class="mm-acciones-bar">
                @auth
                    {{-- ACCIONES PARA EL NODO SELECCIONADO --}}
                    <button id="mm-btn-editar" class="mm-btn-secundario" onclick="window.editarNodoActivo()">
                        <i class="fa-solid fa-pen-to-square"></i> Editar Nodo
                    </button>

                    <button id="mm-btn-agregar-rama" class="mm-btn-secundario" onclick="window.agregarRamaActiva()">
                        <i class="fa-solid fa-code-branch"></i> Agregar Rama
                    </button>

                    <button id="mm-btn-eliminar-rama" class="mm-btn-secundario" onclick="window.eliminarNodoActivo()">
                        <i class="fa-solid fa-eraser"></i> Eliminar Rama
                    </button>

                    {{-- ACCIONES GENERALES DEL MAPA --}}
                    <button id="mm-btn-nuevo" class="mm-btn-secundario">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Regenerar Mapa
                    </button>

                    <button id="mm-btn-eliminar" class="mm-btn-secundario peligro">
                        <i class="fa-solid fa-trash-can"></i> Eliminar Mapa
                    </button>
                @endauth
            </div>

            {{-- Canvas SVG del mapa --}}
                <div id="mm-canvas-svg-container"
                    class="mm-canvas-wrapper"
                    style="min-height: 520px;">
                {{-- Controles de zoom --}}
                <div class="mm-zoom-controls">
                    <button id="btn-zoom-in" class="mm-zoom-btn" title="Acercar">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                    <button id="btn-zoom-out" class="mm-zoom-btn" title="Alejar">
                        <i class="fa-solid fa-minus"></i>
                    </button>
                    <button id="btn-zoom-fit" class="mm-zoom-btn" title="Encuadrar">
                        <i class="fa-solid fa-expand"></i>
                    </button>
                </div>

                {{-- SVG generado por JS --}}
                <svg id="mm-canvas-svg" viewBox="0 0 1000 600" xmlns="http://www.w3.org/2000/svg"
                    style="width:100%; min-height:520px; display:block; background: transparent;">
                </svg>
                </div>

    {{-- ══════════════════════════════════════════════════════
         MODAL — Escribir tema para generar
    ══════════════════════════════════════════════════════ --}}
    {{-- MODAL INTEGRADO: SELECCIONAR O SUBIR DOCUMENTO --}}
    <div id="mm-modal-overlay"
        class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300">
        <div
            class="bg-[#111111] border border-zinc-800 rounded-3xl w-full max-w-lg p-6 md:p-8 shadow-2xl transform scale-95 transition-all duration-300 mx-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                    <i class="fa-solid fa-diagram-project text-red-500"></i> Generar Mapa Mental
                </h2>
                <button onclick="cerrarModal()" class="text-zinc-400 hover:text-white transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <p class="text-xs text-zinc-400 mb-6 leading-relaxed">Selecciona uno de tus archivos en la nube o sube uno
                nuevo.</p>
            <form id="form-generar-mapa" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-2">Seleccionar
                        PDF Existente</label>
                    <select id="mm-select-documento"
                        class="w-full bg-[#161616] border border-zinc-800 rounded-xl px-4 py-3 text-xs text-zinc-200 focus:outline-none focus:border-red-500">
                        <option value="">-- Elige un documento de tu lista --</option>
                        @foreach ($documentos as $doc)
                            <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative flex py-2 items-center">
                    <div class="flex-grow border-t border-zinc-800/80"></div>
                    <span class="flex-shrink mx-4 text-zinc-500 text-[10px] uppercase font-bold tracking-widest">O Sube
                        Uno Nuevo</span>
                    <div class="flex-grow border-t border-zinc-800/80"></div>
                </div>
                <div id="mm-zona-subida-rapida"
                    class="border-2 border-dashed border-zinc-800 hover:border-red-500/40 rounded-xl p-5 text-center cursor-pointer transition-all bg-[#161616]/30">
                    <input type="file" id="mm-file-rapido" accept=".pdf" class="hidden">
                    <div
                        class="w-10 h-10 bg-zinc-900 mx-auto mb-3 rounded-xl flex items-center justify-center text-zinc-400 border border-zinc-800">
                        <i class="fa-solid fa-cloud-arrow-up text-lg"></i>
                    </div>
                    <span id="text-upload-modal" class="text-xs text-zinc-300 font-medium block">Arrastra o haz clic
                        para subir un PDF</span>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-800/50">
                    <button type="button" onclick="cerrarModal()"
                        class="px-4 py-2.5 rounded-xl text-xs font-semibold text-zinc-400 hover:bg-zinc-900">Cancelar</button>
                    <button type="submit"
                        class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl text-xs font-semibold"><i
                            class="fa-solid fa-wand-magic-sparkles"></i> Generar Diagrama</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         PANEL DE EDICIÓN FLOTANTE
    ══════════════════════════════════════════════════════ --}}
    <div id="mm-edit-panel" class="mm-edit-panel oculto">
        <i class="fa-solid fa-pen" style="color: var(--color-gris-oscuro); font-size: 0.8rem; flex-shrink:0;"></i>
        <input id="mm-edit-input" class="mm-edit-input" type="text" placeholder="Editar texto del nodo...">
        <button id="mm-btn-edit-guardar" class="mm-btn-generar"
            style="padding:0.45rem 1rem; font-size:0.78rem; white-space:nowrap; flex-shrink:0;">
            <i class="fa-solid fa-check"></i> Guardar
        </button>
        <button id="mm-btn-edit-eliminar" class="mm-btn-secundario peligro" style="flex-shrink:0;">
            <i class="fa-solid fa-trash-can"></i>
        </button>
        <button id="mm-btn-edit-cancelar" class="mm-btn-cancelar" style="flex-shrink:0;">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════
         TOAST DE NOTIFICACIÓN
    ══════════════════════════════════════════════════════ --}}
    <div id="mm-toast" class="mm-toast oculto">
        <i class="fa-solid fa-circle-check" style="color: #22c55e; flex-shrink:0;"></i>
        <span id="mm-toast-msg">Cambios guardados</span>
    </div>

    </main>

    {{-- ══════════════════════════════════════════════════════
         MENÚ MÓVIL (drawer — igual que welcome.blade.php)
    ══════════════════════════════════════════════════════ --}}
    <div id="fondo-oscuro-menu" class="overlay-menu-movil hidden"></div>
    <aside id="menu-movil-drawer" class="menu-movil-contenedor p-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-6">
                <span class="text-lg font-bold text-white">Menú <span class="primario-resaltado">PlayDF</span></span>
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
                <a href="/mapa-mental"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-sitemap text-purple-400"></i> Mapa Mental
                </a>
                <a href="/docente/crear-sala"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-list-check text-emerald-400"></i> Crear Cuestionario
                </a><a href="{{ route('tarjetas-estudio.index') }}"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                        <i class="fa-solid fa-layer-group text-pink-400"></i> Tarjetas de Estudio
                    </a>
            </div>

            <div class="divisor-linea my-4"></div>

            <h3 class="seccion-subtitulo text-[11px] font-bold uppercase tracking-wider mb-2">Repaso</h3>
            <div class="space-y-2">
                <a href="/modo-examen"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-graduation-cap text-red-500"></i> Modo Examen
                </a>
            </div>
        </div>
    </aside>

    {{-- Footer --}}
    @include('partials.footer')

    {{-- Script inline para el menú móvil (igual que welcome) --}}
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
