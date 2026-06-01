<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Inteligencia Artificial en PDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/js/app.js', 'resources/js/welcome.js'])

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
                <span class="text-amber-400">🔥 Racha: 5 Días</span>
                <span class="text-blue-400">⭐ Nivel 12</span>
            </div>
        </div>

        <div class="flex items-center gap-3 md:gap-4">
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

                <button class="boton-deshabilitado w-full text-xs py-2 px-4 rounded-xl cursor-not-allowed mb-6">
                    Conexión de Conceptos
                </button>

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

        <section class="col-span-1 md:col-span-2 flex flex-col justify-between h-[calc(100vh-140px)] md:h-[80vh] gap-4">

            <div class="flex-grow relative flex flex-col">
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

                <div id="contenedor-chat"
                    class="chat-contenedor-ia absolute inset-0 rounded-2xl p-4 hidden flex-col justify-between overflow-hidden z-0">
                    <div id="historial-chat" class="w-full flex-grow overflow-y-auto space-y-3 pr-1 max-h-[52vh]">
                    </div>
                </div>
            </div>

            <div id="wrapper-busqueda"
                class="barra-busqueda w-full relative flex items-center rounded-2xl px-4 py-3 transition-all">
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
                    <button
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-brain text-amber-500"></i> Repetición Espaciada (SRS)
                    </button>
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
                    <button
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-file-lines text-blue-400"></i> Resumen Automático
                    </button>
                    <a href="{{ route('mapa-mental.index') }}"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-sitemap text-purple-400"></i> Generar Mapa Mental
                    </a>
                    <a href="/docente/crear-sala"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-list-check text-emerald-400"></i> Crear Cuestionario
                    </a>
                    <a href="{{ route('tarjetas-estudio.index') }}"
                        class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                        <i class="fa-solid fa-layer-group text-pink-400"></i> Tarjetas de Estudio
                    </a>
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
                <button
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-file-lines text-blue-400"></i> Resumen Automático
                </button>
                <a href="{{ route('mapa-mental.index') }}"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                    <i class="fa-solid fa-sitemap text-purple-400"></i> Generar Mapa Mental
                </a>
                <a href="/docente/crear-sala"
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-list-check text-emerald-400"></i> Crear Cuestionario
                </a>
                <button
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-layer-group text-pink-400"></i> Tarjetas de Estudio
                </button>
            </div>

            <div class="divisor-linea my-4"></div>

            <h3 class="seccion-subtitulo text-[11px] font-bold uppercase tracking-wider mb-2">Repaso</h3>
            <div class="space-y-2">
                <button
                    class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                    <i class="fa-solid fa-brain text-amber-500"></i> Repetición Espaciada (SRS)
                </button>
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
</body>

</html>
