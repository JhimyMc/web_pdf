<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Panel de Control del Docente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    @vite(['resources/css/crear-sala.css', 'resources/js/crear-sala.js'])
</head>

<body class="bg-slate-900 text-slate-100 font-sans min-h-screen flex flex-col justify-between">

    <header
        class="w-full bg-slate-800 border-b border-slate-700 px-6 py-4 flex flex-row items-center justify-between shadow-md">
        <div class="flex items-center gap-3">
            <div class="bg-blue-600 text-white font-black text-2xl px-4 py-1 rounded-lg shadow-inner">P</div>
            <span class="text-xl font-bold tracking-tight hidden sm:inline">Play<span class="text-blue-500">DF</span>
                Docente</span>
        </div>
        <div class="flex items-center gap-4">
            @auth
                <span class="text-sm bg-slate-700 px-3 py-1.5 rounded-full border border-slate-600 text-slate-300">
                    <i class="fa-solid fa-user-tie mr-2"></i>{{ Auth::user()->name }}
                </span>
            @endauth
            <a href="/" class="text-sm text-slate-400 hover:text-white transition-colors">
                <i class="fa-solid fa-arrow-left-long mr-2"></i>Volver
            </a>
        </div>
    </header>

    <main class="flex-grow w-full max-w-4xl mx-auto px-4 py-8 flex flex-col items-center justify-center">
        <div class="w-full bg-slate-800 border border-slate-700 rounded-2xl p-6 sm:p-10 shadow-2xl">

            <div class="text-center mb-8">
                <h1 class="text-3xl font-extrabold text-white tracking-tight sm:text-4xl">Panel de Evaluación Activa
                </h1>
                <p class="text-slate-400 mt-2 text-sm sm:text-base">Inicia una sala interactiva para tus estudiantes.
                </p>
            </div>

            <div
                class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5 mb-8 flex flex-col md:flex-row gap-4 justify-between items-center">
                <div class="flex items-center gap-4 w-full md:w-auto">
                    <div
                        class="bg-red-500/10 text-red-400 p-3.5 rounded-xl border border-red-500/20 text-xl hidden sm:block">
                        <i class="fa-solid fa-file-pdf"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-semibold text-slate-200">Examen_Historia_Prueba.pdf</p>
                        <p class="text-xs text-slate-500">Documento procesado correctamente</p>
                    </div>
                </div>
                <div class="w-full md:w-auto text-right">
                    <span
                        class="inline-flex items-center gap-1.5 bg-emerald-500/10 text-emerald-400 text-xs font-medium px-2.5 py-1 rounded-full border border-emerald-500/20 pulse-emerald">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Listo para Vincular
                    </span>
                </div>
            </div>

            <div class="w-full flex justify-center">
                <button id="btnCrearSala"
                    class="w-full sm:w-2/3 bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 px-6 rounded-xl shadow-lg transition-all duration-200 flex items-center justify-center gap-3 text-lg">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span id="btnText">Generar Nueva Sala Activa</span>
                </button>
            </div>

            <div id="resultado" class="mt-10 pt-8 border-t border-slate-700/60 hidden animate-fade-in text-center">
                <p class="text-slate-400 font-medium text-sm uppercase tracking-wider">Código Único de Acceso</p>

                <div class="flex items-center justify-center gap-4 my-4">
                    <p id="codigoSala"
                        class="text-5xl sm:text-6xl font-black text-emerald-400 tracking-widest font-mono bg-slate-900 px-6 py-2 rounded-2xl border border-slate-700">
                        -----</p>
                </div>

                <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">
                    Los alumnos deben ingresar a <span class="text-blue-400 font-semibold underline">Modo Examen</span>
                    e introducir este código.
                </p>

                <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-700 max-w-md mx-auto">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-sm font-medium text-slate-300">Pregunta Actual del Grupo:</span>
                        <span class="bg-blue-600/20 text-blue-400 px-3 py-1 rounded-md font-bold text-sm"
                            id="indexPregunta">En Espera</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="w-full bg-slate-950 py-4 text-center border-t border-slate-800 text-xs text-slate-500">
        &copy; 2026 PlayDF.
    </footer>
</body>

</html>
