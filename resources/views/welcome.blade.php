<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Inteligencia Artificial en PDF</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex flex-col min-h-screen bg-black">

    <header class="flex justify-between items-center p-4 border-b border-gray-800 bg-black">
        <div class="text-red-600 font-bold text-2xl tracking-tighter">PlayDF</div>
        
        <div class="flex gap-4 items-center">
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="text-gray-400 text-sm hover:text-white">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-gray-400 text-sm hover:text-white">Entrar</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="text-red-600 border border-red-600 px-4 py-1 rounded-full text-sm hover:bg-red-600 hover:text-white transition">Registrarse</a>
                    @endif
                @endauth
            @endif
        </div>
    </header>

    <main class="layout-principal">
        
        <aside class="columna-lateral">
            <h3 class="text-gray-500 text-xs font-bold mb-4 uppercase tracking-widest">Fuentes</h3>
            <button class="boton-opcion">+ Cargar Documento</button>
            <div class="mt-8">
                <p class="text-gray-600 text-sm italic">No hay archivos cargados recientemente.</p>
            </div>
        </aside>

        <section class="seccion-central">
            <div class="caja-subida">
                <div class="text-4xl mb-2 text-gray-700">📄</div>
                <p class="text-xl font-semibold">Arrastra tu PDF aquí</p>
                <p class="text-gray-500 text-sm mb-4">o selecciona un archivo de tu PC</p>
                <button class="boton-rojo shadow-lg shadow-red-900/20">Seleccionar PDF</button>
            </div>

            <div class="w-full max-w-2xl px-4">
                <input type="text" class="barra-busqueda w-full focus:ring-1 focus:ring-red-600 outline-none" 
                       placeholder="Haz una pregunta sobre el contenido del PDF...">
            </div>
        </section>

        <aside class="columna-lateral columna-derecha">
            <h3 class="text-gray-500 text-xs font-bold mb-4 uppercase tracking-widest">Herramientas</h3>
            <div class="space-y-2">
                <button class="boton-opcion">Resumen Automático</button>
                <button class="boton-opcion">Generar Mapa Mental</button>
                <button class="boton-opcion">Crear Cuestionario</button>
                <button class="boton-opcion">Tarjetas de Estudio</button>
                <button class="boton-opcion">Análisis de Video</button>
            </div>
        </aside>
    
    </main>

    @include('partials.footer')

</body>
</html>