<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Inteligencia Artificial en PDF</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

    <header class="header-principal">
        <div class="logo-marca">PlayDF</div>
        
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
            <div style="margin-top: 2rem;">
                <p style="color: #4b5563; font-size: 0.875rem; font-style: italic;">No hay archivos cargados recientemente.</p>
            </div>
        </aside>

        <section class="seccion-central">
            <div class="caja-subida">
                <div class="icono-pdf">📄</div>
                <p class="texto-subida-principal">Arrastra tu PDF aquí</p>
                <p class="texto-subida-secundario">o selecciona un archivo de tu PC</p>
                <button class="boton-rojo">Seleccionar PDF</button>
            </div>

            <div class="contenedor-busqueda">
                <input type="text" class="barra-busqueda" placeholder="Haz una pregunta sobre el contenido del PDF...">
            </div>
        </section>

        <aside class="columna-lateral columna-derecha">
            <h3 class="titulo-seccion">Herramientas</h3>
            <button class="boton-opcion">Resumen Automático</button>
            <button class="boton-opcion">Generar Mapa Mental</button>
            <button class="boton-opcion">Crear Cuestionario</button>
            <button class="boton-opcion">Tarjetas de Estudio</button>
            
        </aside>
    
    </main>

    @include('partials.footer')

</body>
</html>