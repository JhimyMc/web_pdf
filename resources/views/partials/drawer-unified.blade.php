<div id="fondo-oscuro-menu" class="overlay-menu-movil"></div>
<aside id="menu-movil-drawer" class="menu-movil-contenedor flex flex-col justify-between">
    <div>
        <div class="drawer-header-banner p-5 pb-4" style="background: var(--color-primario);">
            <div class="flex items-center justify-between mb-3">
                <span class="text-lg font-bold text-white">PlayDF</span>
                <button id="btn-cerrar-menu-movil" class="text-white/70 hover:text-white p-1 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            @auth
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm"
                    style="background: rgba(255,255,255,0.2);">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                    <p class="text-[11px] text-white/70 truncate">{{ Auth::user()->email }}</p>
                </div>
            </div>
            @else
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white"
                    style="background: rgba(255,255,255,0.2);">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">Invitado</p>
                    <p class="text-[11px] text-white/70">Inicia sesion para mas funciones</p>
                </div>
            </div>
            @endauth
        </div>

        <div class="p-5 pt-4">

        @if(isset($documentos))
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
                            onclick="if(typeof seleccionarDocumento === 'function') { seleccionarDocumento({{ $doc->id }}, '{{ $doc->name }}'); } window.cerrarMenuMovilId();"
                            class="enlace-documento flex items-center gap-2 text-xs font-medium overflow-hidden text-ellipsis whitespace-nowrap text-left flex-1 mr-1">
                            <i class="fa-solid fa-file-pdf text-xs flex-shrink-0"></i>
                            <span class="truncate">{{ $doc->name }}</span>
                        </button>
                        <button onclick="if(typeof eliminarDocumento === 'function') eliminarDocumento(event, {{ $doc->id }})"
                            class="boton-eliminar p-1 px-2 rounded-lg">
                            <i class="fa-solid fa-trash-can text-[10px]"></i>
                        </button>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="divisor-linea my-4"></div>
        @endif

        <h3 class="seccion-subtitulo text-[11px] font-bold uppercase tracking-wider mb-2">Navegacion</h3>
        <div class="space-y-2">
            <a href="{{ route('dashboard') }}"
                class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5 transition-colors">
                <i class="fa-solid fa-house text-blue-400"></i> Home
            </a>
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
                <i class="fa-solid fa-brain text-amber-500"></i> Repeticion Espaciada (SRS)
            </a>
            <a href="/modo-examen"
                class="boton-herramienta-ia text-left text-xs font-medium p-3 rounded-xl flex items-center gap-2.5">
                <i class="fa-solid fa-graduation-cap text-red-500"></i> Modo Examen
            </a>
        </div>

        </div>
    </div>
</aside>
