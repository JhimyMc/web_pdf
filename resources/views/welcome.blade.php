<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PlayDF">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="description" content="PlayDF — Herramientas de estudio interactivas con Inteligencia Artificial">
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

    @include('partials.header-unified')

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

        <section class="col-span-1 md:col-span-2 flex flex-col md:h-[80vh] md:relative">

            <!-- Selector de PDFs (solo mobile) -->
            <div class="md:hidden mb-2">
                <input type="file" id="pdf-file-input-movil" accept=".pdf" class="hidden">
                <button id="btn-toggle-pdf-movil"
                    class="boton-accion-principal w-full text-xs font-bold py-2 px-4 rounded-xl flex items-center justify-between mb-1.5">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-folder-open text-red-500"></i> Mis PDFs</span>
                    <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-300" id="icono-flecha-pdf-movil"></i>
                </button>
                <div id="seccion-pdf-movil" class="hidden space-y-1.5 max-h-[120px] overflow-y-auto mb-2 px-1">
                    @if (count($documentos) == 0)
                        <p class="text-[11px] text-center py-2" style="color: var(--color-gris-medio);">No hay archivos.</p>
                    @else
                        @foreach ($documentos as $doc)
                            <div id="doc-item-movil-{{ $doc->id }}"
                                class="item-documento flex items-center justify-between p-2 rounded-xl transition-colors">
                                <button onclick="seleccionarDocumento({{ $doc->id }}, '{{ $doc->name }}')"
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
                <button id="btn-cargar-pdf-movil-home"
                    class="boton-cargar-movil-rojo w-full text-[11px] font-bold py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 shadow-md">
                    <i class="fa-solid fa-cloud-arrow-up text-xs"></i> Subir nuevo PDF
                </button>
            </div>

            <!-- Drop zone (solo se ve cuando NO hay chat activo) -->
            <div id="zona-drop"
                class="zona-arrastre border-2 border-dashed rounded-2xl p-6 flex flex-col items-center justify-center cursor-pointer mb-3">
                <div class="insignia-pdf font-black text-xl px-4 py-2 rounded-xl mb-3">PDF</div>
                <p class="text-sm font-semibold texto-blanco text-center">Selecciona tu PDF aquí</p>
                <p class="text-xs texto-atenuado mt-1 text-center">Toca el botón para abrir un documento</p>
                <button id="btn-seleccionar-centro"
                    class="nav-boton-subir mt-4 boton-subir-archivos text-white font-bold text-xs py-2 px-5 rounded-xl shadow-lg transition-colors">
                    Seleccionar Archivos
                </button>
            </div>

            <!-- Pantalla de carga (overlay) -->
            <div id="pantalla-carga"
                class="pantalla-carga-ia fixed inset-0 p-8 hidden flex-col items-center justify-center text-center z-50 backdrop-blur-sm">
                <div class="relative w-20 h-20 mb-4">
                    <div class="anillo-espera-base absolute inset-0 rounded-full border-4"></div>
                    <div class="anillo-espera-activo absolute inset-0 rounded-full border-4 animate-spin"></div>
                    <i class="fa-solid fa-brain text-2xl absolute inset-0 flex items-center justify-center"></i>
                </div>
                <p class="text-sm font-bold texto-blanco animate-pulse">Analizando y guardando tu PDF...</p>
                <p class="text-xs texto-atenuado mt-1 max-w-xs mx-auto">Extrayendo texto para que la IA responda sin demoras</p>
            </div>

            <!-- Chat (solo se ve cuando HAY chat activo, reemplaza la drop zone) -->
            <div id="contenedor-chat"
                class="chat-contenedor-ia rounded-2xl p-4 hidden flex-col h-[50vh] md:h-auto md:flex-1 md:min-h-0">
                <div id="historial-chat" class="w-full flex-1 min-h-0 overflow-y-auto space-y-3 pr-1 pb-2">
                </div>
            </div>

            <!-- Barra de búsqueda -->
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

            <!-- Botones móviles (solo visible en mobile, debajo del chat) -->
            <div class="md:hidden mt-4 space-y-3">
                <a href="/modo-examen"
                    class="flex items-center justify-center gap-2 w-full py-3.5 px-4 rounded-2xl text-white font-extrabold text-sm transition-all"
                    style="background: var(--color-primario); box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <div class="text-left">
                        <div class="text-sm font-extrabold leading-tight">LANZAR MODO EXAMEN</div>
                        <div class="text-[10px] font-medium opacity-80">Evalua a tus estudiantes</div>
                    </div>
                </a>

                <a href="{{ route('solo-exam.configurar') }}"
                    class="flex items-center justify-center gap-2 w-full py-3 px-4 rounded-2xl text-white font-bold text-sm transition-all"
                    style="background: #059669; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <div class="text-left">
                        <div class="text-sm font-bold leading-tight">CREAR CUESTIONARIO</div>
                        <div class="text-[10px] font-medium opacity-80">Genera quiz con IA</div>
                    </div>
                </a>

                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('mapa-mental.index') }}"
                        class="flex items-center gap-2.5 p-3 rounded-2xl transition-all"
                        style="background: var(--color-fondo); border: 1px solid rgba(139, 92, 246, 0.35);">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                            style="background: rgba(139, 92, 246, 0.12);">
                            <i class="fa-solid fa-sitemap text-purple-400 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold" style="color: var(--color-texto);">Mapa Mental</p>
                            <p class="text-[9px]" style="color: var(--color-gris-medio);">Diagramas IA</p>
                        </div>
                    </a>

                    <a href="{{ route('tarjetas-estudio.index') }}"
                        class="flex items-center gap-2.5 p-3 rounded-2xl transition-all"
                        style="background: var(--color-fondo); border: 1px solid rgba(236, 72, 153, 0.35);">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                            style="background: rgba(236, 72, 153, 0.12);">
                            <i class="fa-solid fa-layer-group text-pink-400 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold" style="color: var(--color-texto);">Tarjetas</p>
                            <p class="text-[9px]" style="color: var(--color-gris-medio);">Flashcards</p>
                        </div>
                    </a>

                    <a href="{{ route('srs.index') }}"
                        class="flex items-center gap-2.5 p-3 rounded-2xl transition-all"
                        style="background: var(--color-fondo); border: 1px solid rgba(245, 158, 11, 0.35);">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                            style="background: rgba(245, 158, 11, 0.12);">
                            <i class="fa-solid fa-brain text-amber-500 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold" style="color: var(--color-texto);">Repeticion</p>
                            <p class="text-[9px]" style="color: var(--color-gris-medio);">Sistema SM-2</p>
                        </div>
                    </a>

                    @auth
                    <a href="{{ route('ahorcado.index') }}"
                        class="flex items-center gap-2.5 p-3 rounded-2xl transition-all"
                        style="background: var(--color-fondo); border: 1px solid rgba(139, 92, 246, 0.35);">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                            style="background: rgba(139, 92, 246, 0.12);">
                            <i class="fa-solid fa-puzzle-piece text-violet-400 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold" style="color: var(--color-texto);">Ahorcado</p>
                            <p class="text-[9px]" style="color: var(--color-gris-medio);">Vocabulario</p>
                        </div>
                    </a>
                    @endauth
                </div>
            </div>
        </section>

        <aside class="hidden md:flex panel-lateral rounded-2xl p-3 flex-col h-[80vh] overflow-y-auto">
            <div class="space-y-2">
                <h3 class="seccion-subtitulo text-[10px] font-bold uppercase tracking-wider mb-1">Herramientas IA</h3>

                <a href="{{ route('mapa-mental.index') }}"
                    class="tool-card flex items-center gap-2.5 p-2.5 rounded-xl transition-all"
                    style="background: var(--color-fondo); border: 1px solid rgba(139, 92, 246, 0.35);">
                    <div class="tool-card-icon-box flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: rgba(139, 92, 246, 0.12);">
                        <i class="fa-solid fa-sitemap text-purple-400 text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-bold" style="color: var(--color-texto);">Mapa Mental</p>
                        <p class="text-[9px]" style="color: var(--color-gris-medio);">Diagramas con IA</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[9px] flex-shrink-0" style="color: var(--color-gris-oscuro);"></i>
                </a>

                <a href="{{ route('tarjetas-estudio.index') }}"
                    class="tool-card flex items-center gap-2.5 p-2.5 rounded-xl transition-all"
                    style="background: var(--color-fondo); border: 1px solid rgba(236, 72, 153, 0.35);">
                    <div class="tool-card-icon-box flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: rgba(236, 72, 153, 0.12);">
                        <i class="fa-solid fa-layer-group text-pink-400 text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-bold" style="color: var(--color-texto);">Tarjetas de Estudio</p>
                        <p class="text-[9px]" style="color: var(--color-gris-medio);">Flashcards interactivas</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[9px] flex-shrink-0" style="color: var(--color-gris-oscuro);"></i>
                </a>

                <a href="{{ route('srs.index') }}"
                    class="tool-card flex items-center gap-2.5 p-2.5 rounded-xl transition-all"
                    style="background: var(--color-fondo); border: 1px solid rgba(245, 158, 11, 0.35);">
                    <div class="tool-card-icon-box flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: rgba(245, 158, 11, 0.12);">
                        <i class="fa-solid fa-brain text-amber-500 text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-bold" style="color: var(--color-texto);">Repeticion Espaciada</p>
                        <p class="text-[9px]" style="color: var(--color-gris-medio);">Sistema SM-2</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[9px] flex-shrink-0" style="color: var(--color-gris-oscuro);"></i>
                </a>

                @auth
                <a href="{{ route('ahorcado.index') }}"
                    class="tool-card flex items-center gap-2.5 p-2.5 rounded-xl transition-all"
                    style="background: var(--color-fondo); border: 1px solid rgba(139, 92, 246, 0.35);">
                    <div class="tool-card-icon-box flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: rgba(139, 92, 246, 0.12);">
                        <i class="fa-solid fa-puzzle-piece text-violet-400 text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-bold" style="color: var(--color-texto);">Ahorcado</p>
                        <p class="text-[9px]" style="color: var(--color-gris-medio);">Vocabulario</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[9px] flex-shrink-0" style="color: var(--color-gris-oscuro);"></i>
                </a>
                @endauth

                <a href="{{ route('sala.historial') }}"
                    class="tool-card flex items-center gap-2.5 p-2.5 rounded-xl transition-all"
                    style="background: var(--color-fondo); border: 1px solid rgba(245, 158, 11, 0.35);">
                    <div class="tool-card-icon-box flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: rgba(245, 158, 11, 0.12);">
                        <i class="fa-solid fa-clock-rotate-left text-amber-500 text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-bold" style="color: var(--color-texto);">Historial de Examenes</p>
                        <p class="text-[9px]" style="color: var(--color-gris-medio);">Sesiones pasadas</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[9px] flex-shrink-0" style="color: var(--color-gris-oscuro);"></i>
                </a>
            </div>

            <div class="divisor-linea my-2.5"></div>

            <div class="space-y-2">
                <a href="/modo-examen"
                    class="tool-card-examen flex items-center justify-center gap-2 w-full py-3 px-3 rounded-xl text-white font-extrabold text-xs transition-all"
                    style="background: var(--color-primario); box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <div class="text-left">
                        <div class="text-xs font-extrabold leading-tight">LANZAR MODO EXAMEN</div>
                        <div class="text-[9px] font-medium opacity-80">Evalua a tus estudiantes</div>
                    </div>
                </a>

                <a href="{{ route('solo-exam.configurar') }}"
                    class="tool-card-cuestionario flex items-center justify-center gap-2 w-full py-2.5 px-3 rounded-xl text-white font-bold text-xs transition-all"
                    style="background: #059669; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <div class="text-left">
                        <div class="text-xs font-bold leading-tight">CREAR CUESTIONARIO</div>
                        <div class="text-[9px] font-medium opacity-80">Genera quiz con IA</div>
                    </div>
                </a>
            </div>

        </aside>

    </main>

    @include('partials.drawer-unified')
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

    @include('partials.scripts-unified')
    <script>
    (function() {
        var deferredPrompt = null;
        var banner = document.getElementById('pwa-install-banner');
        var btnInstall = document.getElementById('pwa-btn-install');
        var btnCancel = document.getElementById('pwa-btn-cancel');
        var btnClose = document.getElementById('pwa-btn-close');
        var modal = document.getElementById('pwa-manual-modal');
        var modalClose = document.getElementById('pwa-modal-close');
        var modalOk = document.getElementById('pwa-modal-ok');
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
        });

        window.addEventListener('appinstalled', function() {
            deferredPrompt = null;
        });

        btnInstall.addEventListener('click', tryInstall);
        btnCancel.addEventListener('click', function() { banner.classList.add('hidden'); });
        btnClose.addEventListener('click', function() { banner.classList.add('hidden'); });
        modalClose.addEventListener('click', hideModal);
        modalOk.addEventListener('click', hideModal);
        modal.addEventListener('click', function(e) { if (e.target === modal) hideModal(); });

        if (btnInstallApp) {
            btnInstallApp.addEventListener('click', function() {
                document.getElementById('user-dropdown').classList.add('hidden');
                var arrow = document.getElementById('spinner-arrow');
                if (arrow) arrow.style.transform = '';
                tryInstall();
            });
        }
    })();
    </script>
    <script>
    (function() {
        var btnToggle = document.getElementById('btn-toggle-pdf-movil');
        var seccion = document.getElementById('seccion-pdf-movil');
        var flecha = document.getElementById('icono-flecha-pdf-movil');
        var btnCargar = document.getElementById('btn-cargar-pdf-movil-home');
        var fileInput = document.getElementById('pdf-file-input-movil');

        if (btnToggle) {
            btnToggle.addEventListener('click', function() {
                seccion.classList.toggle('hidden');
                flecha.classList.toggle('rotate-180');
            });
        }
        if (btnCargar && fileInput) {
            btnCargar.addEventListener('click', function() {
                fileInput.click();
            });
        }
    })();
    </script>
</body>

</html>
