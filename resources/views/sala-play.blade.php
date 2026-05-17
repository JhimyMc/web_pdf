<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Sala en Vivo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="room-code" content="{{ $room->code }}">
    <meta name="user-name" content="{{ $nombre }}">

    @vite(['resources/css/app.css', 'resources/js/sala-play.js'])
</head>

<body class="bg-slate-950 font-sans min-h-screen text-white flex flex-col relative overflow-hidden">

    <div
        class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none">
    </div>

    <div id="pantalla-espera" class="flex-1 flex flex-col items-center justify-center p-6 z-10">
        <a href="/modo-examen"
            class="absolute top-6 left-6 text-slate-400 hover:text-red-500 transition-colors flex items-center gap-2 font-medium">
            <i class="fa-solid fa-arrow-left"></i> Salir de la sala
        </a>

        <div class="text-center space-y-6">
            <div
                class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-slate-800 border-4 border-blue-500 mb-4 animate-bounce shadow-[0_0_30px_rgba(59,130,246,0.3)]">
                <i class="fa-solid fa-user-astronaut text-4xl text-blue-400"></i>
            </div>
            <h2 class="text-3xl font-bold">¡Hola, <span class="text-blue-400">{{ $nombre }}</span>!</h2>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl inline-block max-w-sm w-full">
                <p class="text-slate-400 text-sm mb-2">Estás en la sala:</p>
                <h3 class="text-5xl font-black tracking-widest text-white mb-6">{{ $room->code }}</h3>
                <div class="flex items-center justify-center gap-3 text-blue-400">
                    <i class="fa-solid fa-circle-notch animate-spin"></i>
                    <span class="font-medium text-sm">Esperando que el creador inicie...</span>
                </div>
            </div>
        </div>
    </div>

    <div id="pantalla-quiz" class="hidden flex-1 flex flex-col max-w-4xl w-full mx-auto p-4 md:p-6 z-10">
        <div
            class="flex justify-between items-center bg-slate-900 border border-slate-800 p-4 rounded-2xl mb-6 shadow-md">
            <div class="text-slate-400 font-bold">
                Pregunta <span id="contador-pregunta" class="text-white text-lg ml-1">1</span><span
                    class="text-slate-500 text-sm">/<span id="total-preguntas"></span></span>
            </div>
            <button id="btn-bandera"
                class="flex items-center gap-2 bg-slate-800 hover:bg-amber-500/20 hover:text-amber-400 text-slate-400 px-4 py-2 rounded-xl transition-colors border border-slate-700">
                <i class="fa-solid fa-flag"></i> <span class="hidden sm:inline text-sm font-medium">Tengo dudas</span>
            </button>
        </div>

        <div class="flex-1 flex flex-col justify-center">
            <h2 id="texto-pregunta" class="text-2xl md:text-3xl font-bold text-center mb-10 leading-tight"></h2>
            <div id="contenedor-opciones" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            </div>
        </div>

        <button id="btn-enviar-respuesta"
            class="w-full md:w-auto self-end bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 px-10 rounded-xl transition-all shadow-lg shadow-blue-500/20 active:scale-95 mt-8 text-lg flex items-center justify-center gap-3">
            Siguiente Pregunta <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>

    <div id="pantalla-resultado" class="hidden flex-1 flex flex-col items-center justify-center p-6 z-10 text-center">
        <i class="fa-solid fa-trophy text-6xl text-emerald-400 mb-6 drop-shadow-[0_0_20px_rgba(52,211,153,0.4)]"></i>
        <h2 class="text-4xl font-black mb-2">¡Completado!</h2>
        <p class="text-slate-400 mb-8">Tus respuestas han sido enviadas al creador de la sala.</p>

        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 max-w-md w-full shadow-2xl mb-8">
            <p class="text-slate-400 font-bold uppercase tracking-widest text-xs mb-2">Tu Puntuación</p>
            <div class="text-7xl font-black text-emerald-400 mb-2"><span id="nota-valor">0</span><span
                    class="text-3xl text-slate-500"> pts</span></div>
        </div>

        <a href="/modo-examen"
            class="text-blue-400 hover:text-blue-300 font-medium transition-colors border border-blue-500/30 px-6 py-2 rounded-lg">Volver
            al Menú</a>
    </div>

</body>

</html>
