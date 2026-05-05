<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Inteligencia Artificial en PDF</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

    <header class="header-principal flex-wrap gap-4">
        <div class="contenedor-logo-gamificacion flex-wrap">
            {{--Mejora del Logo--}}
            <div class="logo-contenedor">
                <span class="logo-letra-p">P</span>
                <div class="logo-bloque-derecho">
                    <span class="logo-texto-lay">lay</span>
                    <span class="logo-texto-df">DF</span>
                </div>
            </div>
            
            <div class="seccion-gamificacion">
                <div class="indicador-racha">Racha: 5 Dias</div>
                <div class="indicador-nivel">Nivel: 12</div>
            </div>
        </div>
        
            <div class="nav-auth">
                @if (Route::has('login'))
                    @auth
                        <span class="link-auth" style="margin-right: 15px; color: var(--color-gris-claro);">
                            {{ Auth::user()->name }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                            @csrf
                            <a href="{{ route('logout') }}" class="link-auth" 
                            onclick="event.preventDefault(); this.closest('form').submit();">
                                Salir
                            </a>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="link-auth">Entrar</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="boton-registro">Registrarse</a>
                        @endif
                    @endauth
                @endif
            </div>
    </header>

    <main class="layout-principal">
        
        <aside class="columna-lateral">
            <h3 class="titulo-seccion">Fuentes</h3>
            <button class="boton-opcion">+ Cargar Documento</button>
            <button class="boton-opcion boton-conexion">Conexion de Conceptos</button>
            
            <div class="contenedor-recientes" style="margin-top: 2rem;">
                <h4 class="titulo-seccion subtitulo-recientes">Archivos Recientes</h4>
                <div class="texto-vacio">
                    No hay archivos cargados recientemente.
                </div>
            </div>
        </aside>

        <section class="seccion-central">
            <div class="caja-subida w-full">
                <div class="icono-reemplazo">PDF</div>
                <p class="texto-subida-principal text-center">Arrastra tus archivos aqui</p>
                <p class="texto-subida-secundario text-center">Puedes subir multiples PDFs para conectarlos</p>
                <button class="boton-rojo mt-2">Seleccionar Archivos</button>
            </div>

            <div class="contenedor-busqueda w-full">
                <input type="text" class="barra-busqueda" placeholder="Haz una pregunta sobre el contenido de tus PDFs...">
            </div>
        </section>

        <aside class="columna-lateral columna-derecha">
            <h3 class="titulo-seccion">Sostenibilidad y Repaso</h3>
            
            <button class="boton-opcion flex-items-center">
                @include('partials.iconos', ['name' => 'book', 'size' => 18, 'class' => 'mr-2'])
                <span>Repeticion Espaciada (SRS)</span>
            </button>
            
            <button class="boton-opcion flex-items-center">
                @include('partials.iconos', ['name' => 'file-description', 'size' => 18, 'class' => 'mr-2'])
                <a href="/modo-examen" class="boton-opcion">Modo Examen</a>
            </button>

            <div class="separador-herramientas"></div>

            <h3 class="titulo-seccion">Herramientas IA</h3>
            
            <button class="boton-opcion flex-items-center">
                @include('partials.iconos', ['name' => 'file-filled', 'size' => 18, 'class' => 'mr-2'])
                <span>Resumen Automatico</span>
            </button>
            
            <button class="boton-opcion flex-items-center">
                @include('partials.iconos', ['name' => 'sitemap', 'size' => 18, 'class' => 'mr-2'])
                <span>Generar Mapa Mental</span>
            </button>
            
            <button class="boton-opcion flex-items-center">
                @include('partials.iconos', ['name' => 'list-details', 'size' => 18, 'class' => 'mr-2'])
                <span>Crear Cuestionario</span>
            </button>
            
            <button class="boton-opcion flex-items-center">
                @include('partials.iconos', ['name' => 'cards', 'size' => 18, 'class' => 'mr-2'])
                <span>Tarjetas de Estudio</span>
            </button>
        </aside>
    
    </main>

    @include('partials.footer')

    <button class="boton-ayuda-fijo" aria-label="Ayuda">?</button>

</body>
</html>