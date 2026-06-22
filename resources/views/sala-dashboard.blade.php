<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sala en Vivo - PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="room-code" content="{{ $room->code }}">
    <meta name="room-status" content="{{ $room->status }}">

    @vite(['resources/css/app.css', 'resources/css/sala-dashboard.css', 'resources/js/sala-dashboard.js', 'resources/js/dark-toggle.js'])
</head>

<body class="bg-slate-950 font-sans min-h-screen text-white p-4 md:p-8 relative">

    <button id="btn-cancelar-sala" data-status="{{ $room->status }}"
        class="absolute top-6 left-6 text-slate-400 hover:text-red-500 transition-colors flex items-center gap-2 font-medium z-20">
        <i class="fa-solid fa-arrow-left"></i>
        <span id="btn-volver-texto">
            {{ $room->status === 'finalizado' ? 'Volver' : 'Cancelar Sala y Volver' }}
        </span>
    </button>

    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 mt-12">

        <div class="lg:col-span-1 space-y-6">

            <div
                class="bg-slate-900 border border-slate-800 rounded-3xl p-6 text-center shadow-2xl relative overflow-hidden">
                <div id="indicador-pulso" class="absolute top-0 right-0 w-full h-1 bg-blue-500"></div>

                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-2">Código de Sala</p>
                <h1 class="text-6xl font-black text-white tracking-widest mb-4">{{ $room->code }}</h1>

                <div id="status-container"
                    class="bg-blue-500/10 border border-blue-500/20 text-blue-400 rounded-xl p-3 text-sm font-medium flex items-center justify-center gap-2">
                    <i class="fa-solid fa-robot animate-pulse"></i>
                    <span id="status-text">Generando preguntas con IA...</span>
                </div>

                <button id="btn-reintentar-ia"
                    class="hidden mt-4 text-xs text-amber-500 hover:text-amber-400 underline font-medium w-full text-center cursor-pointer transition-colors">
                    <i class="fa-solid fa-rotate-right"></i> ¿Tarda mucho? Forzar reintento
                </button>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4">
                <h3 class="text-slate-400 text-xs font-bold uppercase tracking-widest">Controles de Sala</h3>

                <button id="btn-iniciar-examen" disabled
                    class="w-full bg-slate-800 text-slate-500 font-bold py-3 rounded-xl transition-colors flex items-center justify-center gap-2 cursor-not-allowed">
                    <i class="fa-solid fa-play"></i> Iniciar Examen
                </button>

                <button id="btn-finalizar-examen"
                    class="hidden w-full bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-xl transition-colors items-center justify-center gap-2">
                    <i class="fa-solid fa-stop"></i> Finalizar para Todos
                </button>

                <button id="btn-ver-reporte"
                    class="hidden w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition-colors items-center justify-center gap-2 shadow-lg shadow-blue-500/20">
                    <i class="fa-solid fa-eye"></i> Ver Reporte Detallado
                </button>

                <button id="btn-descargar-pdf"
                    class="hidden w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-xl transition-colors items-center justify-center gap-2 shadow-lg shadow-emerald-500/20">
                    <i class="fa-solid fa-file-pdf"></i> Descargar Reporte PDF
                </button>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
                <p class="text-xs text-slate-500 mb-1">Documento: <span
                        class="text-slate-300 font-medium">{{ $room->pdf_name }}</span></p>
                <p class="text-xs text-slate-500 mb-1">Preguntas: <span
                        class="text-slate-300 font-medium">{{ $room->num_questions }}</span></p>
                <p class="text-xs text-slate-500">Dificultad: <span
                        class="text-amber-400 font-medium capitalize">{{ $room->difficulty }}</span></p>
            </div>
        </div>

        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-3xl p-6 flex flex-col h-[80vh]">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-users text-red-500"></i> Participantes
                    <span id="contador-alumnos"
                        class="bg-slate-800 text-slate-300 text-xs py-1 px-2 rounded-lg ml-2">0</span>
                </h2>
                <div class="relative">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" id="filtro-alumnos" placeholder="Buscar alumno..."
                        class="bg-slate-950 border border-slate-800 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white outline-none focus:border-red-500 w-48">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto pr-2 rounded-xl border border-slate-800/50">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-950/50 text-slate-400 text-xs uppercase sticky top-0 backdrop-blur-sm z-10">
                        <tr>
                            <th class="px-4 py-3 font-semibold w-12">N°</th>
                            <th class="px-4 py-3 font-semibold">Nombre</th>
                            <th class="px-4 py-3 font-semibold text-center">Progreso</th>
                            <th class="px-4 py-3 font-semibold text-center w-24">Nota</th>
                            <th class="px-4 py-3 font-semibold text-center w-16"><i
                                    class="fa-solid fa-flag text-amber-500" title="Dudas/Revisiones"></i></th>
                            <th class="px-4 py-3 font-semibold text-center w-16"><i
                                    class="fa-solid fa-user-slash text-red-500" title="Expulsar"></i></th>
                        </tr>
                    </thead>
                    <tbody id="tabla-estudiantes-body" class="divide-y divide-slate-800/50">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500 italic">
                                Esperando a que se unan los participantes...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <button onclick="toggleTheme()" class="theme-toggle-floating" title="Cambiar tema">
        <i class="fa-solid fa-moon icon-moon"></i>
        <i class="fa-solid fa-sun icon-sun"></i>
    </button>
</body>

</html>
