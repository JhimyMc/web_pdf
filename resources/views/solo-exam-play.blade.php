<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4A90E2">
    <meta name="description" content="Examen individual con IA — PlayDF">
    <title>PlayDF - Examen Individual</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="room-code" content="{{ $room->code }}">
    <meta name="user-name" content="{{ $nombre }}">
    <meta name="is-solo" content="true">

    @vite(['resources/css/app.css', 'resources/css/solo-exam.css', 'resources/js/solo-exam-play.js', 'resources/js/dark-toggle.js'])
</head>

<body class="bg-black font-sans min-h-screen text-white flex flex-col relative overflow-hidden">

    <!-- Fondo decorativo -->
    <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-red-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-96 h-96 bg-blue-600/5 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Pantalla de carga/generación -->
    <div id="pantalla-espera" class="flex-1 flex flex-col items-center justify-center p-6 z-10">
        <a href="/solo-exam/configurar"
            class="absolute top-6 left-6 text-slate-400 hover:text-red-500 transition-colors flex items-center gap-2 font-medium">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>

        <div class="text-center space-y-6">
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-slate-900 border-4 border-red-500/50 mb-4">
                <div class="relative w-16 h-16">
                    <div class="absolute inset-0 rounded-full border-4 border-slate-700"></div>
                    <div class="absolute inset-0 rounded-full border-4 border-t-red-500 animate-spin"></div>
                    <i class="fa-solid fa-brain text-xl absolute inset-0 flex items-center justify-center text-red-400"></i>
                </div>
            </div>
            <h2 class="text-3xl font-bold text-white">Generando tu <span class="text-red-500">examen</span>...</h2>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl inline-block max-w-sm w-full">
                <p class="text-slate-400 text-sm mb-3">{{ $room->pdf_name }}</p>
                <div id="status-generating" class="flex items-center justify-center gap-3 text-red-400">
                    <i class="fa-solid fa-circle-notch animate-spin"></i>
                    <span class="font-medium text-sm">La IA está creando las preguntas...</span>
                </div>
                <div id="status-ready" class="hidden flex items-center justify-center gap-3 text-emerald-400">
                    <i class="fa-solid fa-check-circle"></i>
                    <span class="font-medium text-sm">¡Listo! Iniciando examen...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Pantalla del examen -->
    <div id="pantalla-quiz" class="hidden flex-1 flex flex-col max-w-4xl w-full mx-auto p-4 md:p-6 z-10">
        <!-- Barra superior -->
        <div class="flex justify-between items-center bg-slate-900 border border-slate-800 p-4 rounded-2xl mb-4 shadow-md">
            <div class="flex items-center gap-3">
                <a href="/solo-exam/configurar"
                    class="text-slate-400 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </a>
                <div class="text-slate-400 font-bold">
                    Pregunta <span id="contador-pregunta" class="text-white text-lg ml-1">1</span><span
                        class="text-slate-500 text-sm">/<span id="total-preguntas"></span></span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div id="score-display" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-3 py-1.5 rounded-lg text-xs font-bold">
                    <i class="fa-solid fa-star mr-1"></i><span id="score-actual">0</span> pts
                </div>
                <button id="btn-dificil"
                    class="flex items-center gap-2 bg-slate-800 hover:bg-amber-500/20 hover:text-amber-400 text-slate-400 px-4 py-2 rounded-xl transition-colors border border-slate-700">
                    <i class="fa-solid fa-bookmark"></i> <span class="hidden sm:inline text-sm font-medium">Difícil</span>
                </button>
            </div>
        </div>

        <!-- Barra de progreso -->
        <div class="w-full bg-slate-800 rounded-full h-1.5 mb-6">
            <div id="barra-progreso" class="bg-red-500 h-1.5 rounded-full transition-all duration-500" style="width: 0%"></div>
        </div>

        <!-- Contenido de la pregunta -->
        <div class="flex-1 flex flex-col justify-center">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 md:p-8 mb-8 shadow-lg">
                <h2 id="texto-pregunta" class="text-xl md:text-2xl lg:text-3xl font-bold text-center leading-tight"></h2>
            </div>
            <div id="contenedor-opciones" class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
            </div>
        </div>

        <!-- Botón siguiente -->
        <button id="btn-enviar-respuesta"
            class="w-full md:w-auto self-end bg-red-600 hover:bg-red-500 text-white font-bold py-4 px-10 rounded-xl transition-all shadow-lg shadow-red-500/20 active:scale-95 mt-6 text-lg flex items-center justify-center gap-3 disabled:opacity-40 disabled:cursor-not-allowed"
            disabled>
            Siguiente <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>

    <!-- Pantalla de resultado -->
    <div id="pantalla-resultado" class="hidden flex-1 flex flex-col items-center justify-center p-6 z-10 text-center">
        <i class="fa-solid fa-trophy text-6xl text-amber-400 mb-6 drop-shadow-[0_0_20px_rgba(245,158,11,0.4)]"></i>
        <h2 class="text-3xl md:text-4xl font-black mb-2">¡Examen Completado!</h2>
        <p class="text-slate-400 mb-8">Tus respuestas han sido guardadas.</p>

        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 max-w-md w-full shadow-2xl mb-4">
            <p class="text-slate-400 font-bold uppercase tracking-widest text-xs mb-2">Tu Puntuación</p>
            <div class="text-6xl md:text-7xl font-black mb-2">
                <span id="nota-valor" class="text-emerald-400">0</span>
                <span class="text-2xl text-slate-500">/ <span id="nota-total">0</span></span>
            </div>
            <div class="mt-3">
                <span id="nota-porcentaje" class="text-lg font-bold text-amber-400">0%</span>
                <span class="text-slate-500 text-sm ml-1">de aciertos</span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 mt-6 w-full max-w-md">
            <a id="link-reporte"
                href="#"
                class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-6 rounded-xl transition-colors flex items-center justify-center gap-2 shadow-lg shadow-blue-500/20">
                <i class="fa-solid fa-chart-bar"></i> Ver Reporte
            </a>
            <a href="/solo-exam/configurar"
                class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-3 px-6 rounded-xl transition-colors flex items-center justify-center gap-2 border border-slate-700">
                <i class="fa-solid fa-plus"></i> Nuevo Examen
            </a>
        </div>

        <a href="/"
            class="text-slate-400 hover:text-red-500 font-medium transition-colors mt-6 text-sm">
            <i class="fa-solid fa-home mr-1"></i> Volver al Inicio
        </a>
    </div>

    <button onclick="toggleTheme()" class="theme-toggle-floating" title="Cambiar tema">
        <i class="fa-solid fa-moon icon-moon"></i>
        <i class="fa-solid fa-sun icon-sun"></i>
    </button>
</body>

</html>
