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
    <meta name="description" content="Configura tu sala de estudio — PlayDF">
    <title>Configurar Sala - PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/sala-configurar.css', 'resources/js/sala-configurar.js', 'resources/js/dark-toggle.js'])
</head>

<body class="cuerpo-aplicacion font-sans min-h-screen flex items-center justify-center p-4 relative">

    <a href="/modo-examen"
        class="absolute top-6 left-6 text-slate-400 hover:text-red-500 transition-colors flex items-center gap-2 font-medium z-20">
        <i class="fa-solid fa-arrow-left"></i> Volver al Menú
    </a>

    <div id="pantalla-carga"
        class="fixed inset-0 bg-slate-950/90 z-50 flex-col items-center justify-center hidden backdrop-blur-sm">
        <i class="fa-solid fa-circle-notch animate-spin text-red-500 text-4xl mb-4"></i>
        <h2 class="text-white font-bold text-lg">Procesando PDF...</h2>
        <p class="text-slate-400 text-sm">Extrayendo texto para la IA</p>
    </div>

    {{-- Modal de sala activa --}}
    @if($salaActiva)
    <div id="modal-sala-activa"
        class="fixed inset-0 bg-slate-950/80 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 max-w-md w-full shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-amber-500/10 rounded-full blur-3xl"></div>

            <div class="text-center mb-6 relative z-10">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-500/10 mb-4">
                    <i class="fa-solid fa-triangle-exclamation text-amber-400 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-white mb-2">Ya tienes una sala activa</h2>
                <p class="text-slate-400 text-sm">Tienes una sala en estado <strong class="text-amber-400">{{ $salaActiva->status }}</strong> que aún no ha finalizado.</p>
            </div>

            <div class="bg-slate-950 rounded-xl p-4 mb-6 border border-slate-800 relative z-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-2xl font-black tracking-widest text-white">{{ $salaActiva->code }}</span>
                    <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/30">
                        {{ $salaActiva->status === 'en_vivo' ? 'En Vivo' : ($salaActiva->status === 'espera' ? 'En Espera' : ($salaActiva->status === 'generando' ? 'Generando' : 'Configurando')) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500">
                    <i class="fa-solid fa-file-pdf text-red-500 mr-1"></i> {{ $salaActiva->pdf_name }}
                    &middot; Creada {{ $salaActiva->created_at->diffForHumans() }}
                </p>
            </div>

            <div class="flex flex-col gap-3 relative z-10">
                <a href="{{ route('sala.dashboard', $salaActiva->code) }}"
                    class="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-3 rounded-xl transition-colors flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20">
                    <i class="fa-solid fa-arrow-right"></i> Ir a la sala existente
                </a>
                <form action="{{ route('sala.crear') }}" method="POST" id="form-crear-nueva" class="w-full">
                    @csrf
                    <input type="hidden" name="document_id" id="modal-document-id" value="">
                    <input type="hidden" name="num_questions" id="modal-num-questions" value="10">
                    <input type="hidden" name="difficulty" id="modal-difficulty" value="intermedio">
                    <button type="submit" id="btn-crear-nueva"
                        class="w-full bg-slate-800 hover:bg-red-600 text-slate-400 hover:text-white font-bold py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plus"></i> Crear nueva sala (cerrar la anterior)
                    </button>
                </form>
            </div>

            <button id="btn-cerrar-modal"
                class="absolute top-4 right-4 text-slate-500 hover:text-white transition-colors z-20">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
    </div>
    @endif

    <div
        class="tarjeta-configuracion max-w-xl w-full p-8 md:p-10 rounded-3xl border border-slate-800 relative overflow-hidden z-10">
        <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-red-600/10 rounded-full blur-3xl"></div>

        <div class="text-center mb-8 relative z-10">
            <div class="inline-block bg-red-500/10 p-4 rounded-full mb-4">
                <i class="fa-solid fa-sliders text-red-500 text-3xl"></i>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-white mb-2">Configurar <span class="text-red-500">Sala
                    Live</span></h1>
            <p class="text-slate-400 text-sm">Define los parámetros del cuestionario antes de invitar a los
                participantes.</p>
        </div>

        <form action="{{ route('sala.crear') }}" method="POST" class="space-y-6 relative z-10">
            @csrf

            <div class="grupo-formulario">
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-file-pdf text-red-500 mr-1"></i> Documento Base
                </label>
                <div class="flex gap-2">
                    <select name="document_id" id="select-documento" required
                        class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 outline-none hover:border-slate-700 transition-colors cursor-pointer appearance-none custom-select">
                        <option value="" disabled selected>Selecciona un PDF de tu biblioteca...</option>
                        @foreach ($documentos as $doc)
                            <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>

                    <input type="file" id="input-subir-pdf" accept=".pdf" class="hidden">
                    <button type="button" id="btn-subir-pdf"
                        class="bg-slate-900 border border-slate-800 hover:border-red-500 hover:text-red-400 text-slate-400 px-4 rounded-xl transition-colors"
                        title="Subir nuevo PDF">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="grupo-formulario">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-list-ol text-blue-500 mr-1"></i> Cantidad
                    </label>
                    <select name="num_questions" required
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 outline-none custom-select">
                        <option value="5">5 Preguntas</option>
                        <option value="10" selected>10 Preguntas</option>
                        <option value="15">15 Preguntas</option>
                        <option value="20">20 Preguntas</option>
                    </select>
                </div>

                <div class="grupo-formulario">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-fire text-amber-500 mr-1"></i> Dificultad
                    </label>
                    <select name="difficulty" required
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 outline-none custom-select">
                        <option value="basico">Básico</option>
                        <option value="intermedio" selected>Intermedio</option>
                        <option value="avanzado">Avanzado</option>
                    </select>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-red-600 hover:bg-red-500 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-red-500/20 mt-4 flex items-center justify-center gap-2">
                Crear Sala y Generar Código <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
    </div>
    <button onclick="toggleTheme()" class="theme-toggle-floating" title="Cambiar tema">
        <i class="fa-solid fa-moon icon-moon"></i>
        <i class="fa-solid fa-sun icon-sun"></i>
    </button>
</body>

</html>
