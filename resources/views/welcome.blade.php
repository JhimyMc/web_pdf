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
            <div class="logo-marca">PlayDF</div>
            
            <div class="seccion-gamificacion">
                <div class="indicador-racha">Racha: 5 Dias</div>
                <div class="indicador-nivel">Nivel: 12</div>
            </div>
        </div>
        
        <div class="nav-auth">
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="link-auth">Dashboard</a>
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
            <button class="boton-opcion">Repeticion Espaciada (SRS)</button>
            <button class="boton-opcion">Modo Examen</button>

            <div class="separador-herramientas"></div>

            <h3 class="titulo-seccion">Herramientas IA</h3>
            <button class="boton-opcion">Resumen Automatico</button>
            <button class="boton-opcion">Generar Mapa Mental</button>
            <button class="boton-opcion">Crear Cuestionario</button>
            <button class="boton-opcion">Tarjetas de Estudio</button>
        </aside>
    
    </main>

    @include('partials.footer')

    <button class="boton-ayuda-fijo" aria-label="Ayuda">?</button>

</body>
</html>