<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyecto Web PDF</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

    <header class="flex justify-between items-center p-4 border-b border-gray-800">
        <div class="text-red-600 font-bold text-xl">WEB-PDF</div>
        
        <div>
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="text-gray-400 text-sm">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-gray-400 text-sm">Entrar</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="ml-4 text-red-600 border border-red-600 px-3 py-1 rounded text-sm">Registrarse</a>
                    @endif
                @endauth
            @endif
        </div>
    </header>

    <main class="layout-principal">
        
        <aside class="columna-lateral">
            <h3 class="text-gray-500 text-xs mb-4">FUENTES</h3>
            <button class="boton-opcion">+ Cargar Documento</button>
            <p class="text-gray-600 text-sm mt-5">Aun no hay archivos aqui.</p>
        </aside>

        <section class="seccion-central">
            <div class="caja-subida">
                <p class="text-xl">Arrastra tu PDF aqui</p>
                <p class="text-gray-500 text-sm">o selecciona un archivo de tu PC</p>
                <button class="boton-rojo">Seleccionar PDF</button>
            </div>

            <input type="text" class="barra-busqueda" placeholder="Escribe una pregunta sobre el texto...">
        </section>

        <aside class="columna-lateral columna-derecha">
            <h3 class="text-gray-500 text-xs mb-4">HERRAMIENTAS</h3>
            <button class="boton-opcion">Resumen Automatico</button>
            <button class="boton-opcion">Generar Mapa Mental</button>
            <button class="boton-opcion">Crear Cuestionario</button>
            <button class="boton-opcion">Tarjetas de Estudio</button>
            <button class="boton-opcion">Analisis de Video</button>
        </aside>

    </main>

    </body>
</html>