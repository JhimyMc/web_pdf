<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#000000">
    <meta name="description" content="Reporte de tu sala de estudio — PlayDF">
    <title>Reporte de Sala - PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="room-code" content="{{ $room->code }}">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    @vite(['resources/css/app.css', 'resources/css/sala-reporte.css', 'resources/js/sala-reporte.js', 'resources/js/dark-toggle.js'])
</head>

<body class="cuerpo-aplicacion font-sans min-h-screen text-white p-4 md:p-8">

    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <a href="{{ route('sala.dashboard', $room->code) }}"
                    class="text-slate-400 hover:text-red-500 transition-colors flex items-center gap-2 text-sm font-medium mb-3">
                    <i class="fa-solid fa-arrow-left"></i> Volver al Dashboard
                </a>
                <h1 class="text-2xl md:text-3xl font-black">
                    Reporte de Sala <span class="text-red-500">{{ $room->code }}</span>
                </h1>
                <p class="text-slate-400 text-sm mt-1" id="reporte-subtitulo">
                    {{ $room->pdf_name }} &middot; {{ count($preguntas) }} preguntas &middot;
                    {{ count($estudiantes) }} estudiantes
                </p>
                <p class="text-slate-500 text-xs">
                    Creada: {{ $room->created_at->format('d/m/Y H:i') }} &middot;
                    @if($room->finished_at)
                        Finalizada: {{ $room->finished_at->format('d/m/Y H:i') }}
                    @else
                        Estado: <span class="capitalize">{{ $room->status }}</span>
                    @endif
                </p>
            </div>
            <div class="flex gap-3">
                <button id="btn-imprimir"
                    class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-3 px-6 rounded-xl transition-all flex items-center gap-2 border border-slate-700">
                    <i class="fa-solid fa-file-pdf"></i> <span id="btn-pdf-texto">Descargar PDF</span>
                </button>
                <div id="overlay-carga-pdf" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex-col items-center justify-center">
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 flex flex-col items-center shadow-2xl">
                        <div class="relative w-16 h-16 mb-4">
                            <div class="absolute inset-0 rounded-full border-4 border-slate-700"></div>
                            <div class="absolute inset-0 rounded-full border-4 border-t-red-500 animate-spin"></div>
                            <i class="fa-solid fa-file-pdf text-xl absolute inset-0 flex items-center justify-center text-red-500"></i>
                        </div>
                        <p class="text-white font-bold text-sm">Generando PDF...</p>
                        <p class="text-slate-400 text-xs mt-1">Preparando el reporte de la sala</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen de estudiantes -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            @php
                $totalEstudiantes = count($estudiantes);
                $totalCorrectas = $estudiantes->sum('score');
                $totalPreguntas = $estudiantes->sum('total');
                $promedio = $totalPreguntas > 0 ? round(($totalCorrectas / $totalPreguntas) * 100, 1) : 0;
                $totalBanderas = $estudiantes->sum(function($e) { return $e['tieneBandera'] ? 1 : 0; });
            @endphp
            <div class="estadistica-tarjeta bg-slate-900 border border-slate-800 rounded-2xl p-5">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-500/10 p-3 rounded-xl">
                        <i class="fa-solid fa-users text-blue-500 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider">Estudiantes</p>
                        <p class="text-2xl font-bold text-white">{{ $totalEstudiantes }}</p>
                    </div>
                </div>
            </div>
            <div class="estadistica-tarjeta bg-slate-900 border border-slate-800 rounded-2xl p-5">
                <div class="flex items-center gap-3">
                    <div class="bg-emerald-500/10 p-3 rounded-xl">
                        <i class="fa-solid fa-chart-line text-emerald-500 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider">Promedio</p>
                        <p class="text-2xl font-bold text-emerald-400">{{ $promedio }}%</p>
                    </div>
                </div>
            </div>
            <div class="estadistica-tarjeta bg-slate-900 border border-slate-800 rounded-2xl p-5">
                <div class="flex items-center gap-3">
                    <div class="bg-purple-500/10 p-3 rounded-xl">
                        <i class="fa-solid fa-list-check text-purple-500 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider">Preguntas</p>
                        <p class="text-2xl font-bold text-white">{{ count($preguntas) }}</p>
                    </div>
                </div>
            </div>
            <div class="estadistica-tarjeta bg-slate-900 border border-slate-800 rounded-2xl p-5">
                <div class="flex items-center gap-3">
                    <div class="bg-amber-500/10 p-3 rounded-xl">
                        <i class="fa-solid fa-flag text-amber-500 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider">Banderas</p>
                        <p class="text-2xl font-bold text-amber-400">{{ $totalBanderas }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selector de estudiante -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-8">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-filter mr-1"></i> Filtrar por Estudiante
                    </label>
                    <select id="filtro-estudiante"
                        class="w-full md:w-72 bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 outline-none focus:border-red-500">
                        <option value="todos">Todos los estudiantes</option>
                        @foreach ($estudiantes as $est)
                            <option value="{{ $est['student_name'] }}">{{ $est['student_name'] }}
                                @if($est['tieneBandera']) 🚩 @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="chk-solo-banderas"
                            class="rounded bg-slate-800 border-slate-700 text-red-500 focus:ring-red-500">
                        <span class="text-slate-300">Solo con banderas 🚩</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="chk-solo-incorrectas"
                            class="rounded bg-slate-800 border-slate-700 text-red-500 focus:ring-red-500">
                        <span class="text-slate-300">Solo incorrectas ❌</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Preguntas y respuestas -->
        <div id="contenedor-reporte" class="space-y-6">
            @foreach ($preguntas as $idx => $pregunta)
                <div class="pregunta-bloque bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-lg"
                     data-pregunta-index="{{ $idx }}">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-red-500/10 text-red-500 font-bold text-sm">
                                {{ $idx + 1 }}
                            </span>
                            <div>
                                <h3 class="text-lg font-bold text-white">{{ $pregunta['pregunta'] ?? '—' }}</h3>
                                <p class="text-xs text-slate-500 mt-1">
                                    Opción correcta: <span class="text-emerald-400 font-medium">
                                        {{ isset($pregunta['opciones'][$pregunta['correcta']]) ? $pregunta['opciones'][$pregunta['correcta']] : '—' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="text-xs text-slate-500 bg-slate-950 px-3 py-1 rounded-lg">
                            @php
                                $respondieron = $estudiantes->filter(function($e) use ($idx) {
                                    return $e['detalle'][$idx]['respondio'] ?? false;
                                })->count();
                                $acertaron = $estudiantes->filter(function($e) use ($idx) {
                                    return $e['detalle'][$idx]['is_correct'] ?? false;
                                })->count();
                                $banderas = $estudiantes->filter(function($e) use ($idx) {
                                    return $e['detalle'][$idx]['is_flagged'] ?? false;
                                })->count();
                            @endphp
                            <span class="text-blue-400">{{ $respondieron }} respondieron</span> &middot;
                            <span class="text-emerald-400">{{ $acertaron }} correctas</span>
                            @if($banderas > 0)
                                &middot; <span class="text-amber-400">{{ $banderas }} 🚩</span>
                            @endif
                        </div>
                    </div>

                    <!-- Opciones -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-4">
                        @foreach ($pregunta['opciones'] as $oidx => $opcion)
                            @php
                                $esCorrecta = $oidx === $pregunta['correcta'];
                            @endphp
                            <div class="flex items-center gap-2 p-2.5 rounded-lg text-sm
                                {{ $esCorrecta ? 'bg-emerald-500/10 border border-emerald-500/30' : 'bg-slate-950 border border-slate-800' }}">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md text-xs font-bold flex-shrink-0
                                    {{ $esCorrecta ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-400' }}">
                                    {{ chr(65 + $oidx) }}
                                </span>
                                <span class="{{ $esCorrecta ? 'text-emerald-300 font-medium' : 'text-slate-400' }}">
                                    {{ $opcion }}
                                    @if($esCorrecta) <i class="fa-solid fa-check text-emerald-500 ml-1"></i> @endif
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <!-- Respuestas de estudiantes para esta pregunta -->
                    <div class="respuestas-estudiantes space-y-1.5">
                        @foreach ($estudiantes as $est)
                            @php $det = $est['detalle'][$idx] ?? null; @endphp
                            @if($det && $det['respondio'])
                                <div class="respuesta-item flex items-center justify-between p-2.5 rounded-lg text-xs
                                    {{ $det['is_correct'] ? 'bg-emerald-500/5 border border-emerald-500/10' : 'bg-red-500/5 border border-red-500/10' }}
                                    {{ $det['is_flagged'] ? 'border-amber-500/30' : '' }}"
                                    data-estudiante="{{ $est['student_name'] }}"
                                    data-correcta="{{ $det['is_correct'] ? 'true' : 'false' }}"
                                    data-bandera="{{ $det['is_flagged'] ? 'true' : 'false' }}">
                                    <div class="flex items-center gap-3">
                                        <i class="fa-solid fa-user text-slate-500"></i>
                                        <span class="font-medium text-slate-200">{{ $est['student_name'] }}</span>
                                        @if($det['selected_option'] !== null && isset($pregunta['opciones'][$det['selected_option']]))
                                            <span class="text-slate-400">
                                                Eligió: <span class="{{ $det['is_correct'] ? 'text-emerald-400' : 'text-red-400' }}">
                                                    {{ chr(65 + $det['selected_option']) }}. {{ $pregunta['opciones'][$det['selected_option']] }}
                                                </span>
                                            </span>
                                        @else
                                            <span class="text-slate-500 italic">Sin respuesta</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($det['is_correct'])
                                            <span class="text-emerald-500"><i class="fa-solid fa-check-circle"></i> Correcta</span>
                                        @else
                                            <span class="text-red-400"><i class="fa-solid fa-xmark-circle"></i> Incorrecta</span>
                                        @endif
                                        @if($det['is_flagged'])
                                            <span class="text-amber-500"><i class="fa-solid fa-flag"></i> Revisar</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        <!-- Si nadie respondió -->
                        @php
                            $algunRespondio = $estudiantes->contains(function($e) use ($idx) {
                                return $e['detalle'][$idx]['respondio'] ?? false;
                            });
                        @endphp
                        @if(!$algunRespondio)
                            <p class="text-slate-500 italic text-xs text-center py-3">Ningún estudiante respondió esta pregunta.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Tabla resumen por estudiante -->
        <div class="mt-10 bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-slate-800">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <i class="fa-solid fa-ranking-star text-amber-500"></i>
                    Resumen por Estudiante
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-950/50 text-slate-400 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-left">#</th>
                            <th class="px-4 py-3 font-semibold text-left">Nombre</th>
                            <th class="px-4 py-3 font-semibold text-center">Puntaje</th>
                            <th class="px-4 py-3 font-semibold text-center">%</th>
                            <th class="px-4 py-3 font-semibold text-center">Bandera</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @foreach ($estudiantes->sortByDesc('score') as $eidx => $est)
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="px-4 py-3 text-slate-400">{{ $eidx + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-slate-200">{{ $est['student_name'] }}</td>
                                <td class="px-4 py-3 text-center font-bold text-emerald-400">{{ $est['score'] }}/{{ $est['total'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    @php $porc = $est['total'] > 0 ? round(($est['score'] / $est['total']) * 100) : 0; @endphp
                                    <span class="px-2 py-1 rounded-lg text-xs font-bold
                                        {{ $porc >= 80 ? 'bg-emerald-500/10 text-emerald-400' : ($porc >= 50 ? 'bg-amber-500/10 text-amber-400' : 'bg-red-500/10 text-red-400') }}">
                                        {{ $porc }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($est['tieneBandera'])
                                        <i class="fa-solid fa-flag text-amber-500"></i>
                                    @else
                                        <i class="fa-solid fa-check text-slate-600"></i>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer info -->
        <div class="mt-8 text-center text-xs text-slate-600 border-t border-slate-800 pt-6">
            <p>Reporte generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }} &middot; PlayDF - Inteligencia Artificial en PDF</p>
            <p>Código de Sala: {{ $room->code }} &middot; {{ $room->pdf_name }}</p>
        </div>
    </div>

    <button onclick="toggleTheme()" class="theme-toggle-floating" title="Cambiar tema">
        <i class="fa-solid fa-moon icon-moon"></i>
        <i class="fa-solid fa-sun icon-sun"></i>
    </button>
</body>

</html>
