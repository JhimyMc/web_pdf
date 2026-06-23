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
    <meta name="description" content="Reporte de examen individual — PlayDF">
    <title>Reporte Examen Individual - PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="room-code" content="{{ $room->code }}">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    @vite(['resources/css/app.css', 'resources/css/sala-reporte.css', 'resources/js/dark-toggle.js'])
</head>

<body class="cuerpo-aplicacion font-sans min-h-screen text-white p-4 md:p-8">

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <a href="{{ route('sala.historial') }}"
                    class="text-slate-400 hover:text-red-500 transition-colors flex items-center gap-2 text-sm font-medium mb-3">
                    <i class="fa-solid fa-arrow-left"></i> Volver al Historial
                </a>
                <h1 class="text-2xl md:text-3xl font-black">
                    Reporte Examen <span class="text-red-500">Individual</span>
                </h1>
                <p class="text-slate-400 text-sm mt-1">
                    {{ $room->pdf_name }} &middot; {{ count($preguntas) }} preguntas &middot;
                    Dificultad: <span class="capitalize">{{ $room->difficulty }}</span>
                </p>
                <p class="text-slate-500 text-xs">
                    Creado: {{ $room->created_at->format('d/m/Y H:i') }} &middot;
                    @if($room->finished_at)
                        Finalizado: {{ $room->finished_at->format('d/m/Y H:i') }}
                    @else
                        Estado: <span class="capitalize">{{ $room->status }}</span>
                    @endif
                </p>
            </div>
            <div class="flex gap-3">
                <button id="btn-imprimir"
                    class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-3 px-6 rounded-xl transition-all flex items-center gap-2 border border-slate-700">
                    <i class="fa-solid fa-file-pdf"></i> Descargar PDF
                </button>
            </div>
        </div>

        <!-- Resumen -->
        @php
            $est = $estudiantes->first();
            $score = $est['score'];
            $total = $est['total'];
            $porcentaje = $total > 0 ? round(($score / $total) * 100, 1) : 0;
            $banderas = $est['tieneBandera'];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-500/10 mb-3">
                    <i class="fa-solid fa-chart-line text-emerald-500 text-xl"></i>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Puntuación</p>
                <p class="text-3xl font-black text-emerald-400">{{ $score }}<span class="text-lg text-slate-500">/{{ $total }}</span></p>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-500/10 mb-3">
                    <i class="fa-solid fa-percent text-blue-500 text-xl"></i>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Porcentaje</p>
                <p class="text-3xl font-black {{ $porcentaje >= 70 ? 'text-emerald-400' : ($porcentaje >= 40 ? 'text-amber-400' : 'text-red-400') }}">{{ $porcentaje }}%</p>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-500/10 mb-3">
                    <i class="fa-solid fa-bookmark text-amber-500 text-xl"></i>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Marcadas Difícil</p>
                <p class="text-3xl font-black text-amber-400">{{ $banderas ? 'Sí' : 'No' }}</p>
            </div>
        </div>

        <!-- Detalle por pregunta -->
        <div id="contenedor-reporte" class="space-y-4">
            @foreach ($preguntas as $idx => $pregunta)
                @php $det = $est['detalle'][$idx] ?? null; @endphp
                <div class="pregunta-bloque bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg"
                     data-pregunta-index="{{ $idx }}">
                    <div class="flex items-start gap-3 mb-4">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl font-bold text-sm flex-shrink-0
                            {{ ($det && $det['is_correct']) ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-red-500/10 text-red-400 border border-red-500/30' }}">
                            {{ $idx + 1 }}
                        </span>
                        <div class="flex-1">
                            <h3 class="text-base md:text-lg font-bold text-white">{{ $pregunta['pregunta'] ?? '—' }}</h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Correcta: <span class="text-emerald-400 font-medium">
                                    {{ isset($pregunta['opciones'][$pregunta['correcta']]) ? chr(65 + $pregunta['correcta']) . '. ' . $pregunta['opciones'][$pregunta['correcta']] : '—' }}
                                </span>
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            @if($det && $det['is_correct'])
                                <span class="inline-flex items-center gap-1 bg-emerald-500/10 text-emerald-400 px-2.5 py-1 rounded-lg text-xs font-bold border border-emerald-500/20">
                                    <i class="fa-solid fa-check"></i> Correcta
                                </span>
                            @elseif($det && $det['respondio'])
                                <span class="inline-flex items-center gap-1 bg-red-500/10 text-red-400 px-2.5 py-1 rounded-lg text-xs font-bold border border-red-500/20">
                                    <i class="fa-solid fa-xmark"></i> Incorrecta
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 bg-slate-700 text-slate-400 px-2.5 py-1 rounded-lg text-xs font-bold">
                                    Sin respuesta
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Opciones -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach ($pregunta['opciones'] as $oidx => $opcion)
                            @php
                                $esCorrecta = $oidx === $pregunta['correcta'];
                                $seleccionada = $det && $det['selected_option'] === $oidx;
                            @endphp
                            <div class="flex items-center gap-2 p-2.5 rounded-lg text-sm
                                {{ $esCorrecta ? 'bg-emerald-500/10 border border-emerald-500/30' : ($seleccionada ? 'bg-red-500/10 border border-red-500/30' : 'bg-slate-950 border border-slate-800') }}">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md text-xs font-bold flex-shrink-0
                                    {{ $esCorrecta ? 'bg-emerald-500/20 text-emerald-400' : ($seleccionada ? 'bg-red-500/20 text-red-400' : 'bg-slate-800 text-slate-400') }}">
                                    {{ chr(65 + $oidx) }}
                                </span>
                                <span class="{{ $esCorrecta ? 'text-emerald-300 font-medium' : ($seleccionada ? 'text-red-300' : 'text-slate-400') }}">
                                    {{ $opcion }}
                                    @if($esCorrecta) <i class="fa-solid fa-check text-emerald-500 ml-1"></i> @endif
                                    @if($seleccionada && !$esCorrecta) <i class="fa-solid fa-xmark text-red-400 ml-1"></i> @endif
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @if($det && $det['is_flagged'])
                        <div class="mt-3 flex items-center gap-2 text-amber-400 text-xs">
                            <i class="fa-solid fa-bookmark"></i> Marcada como difícil
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-xs text-slate-600 border-t border-slate-800 pt-6">
            <p>Reporte generado el {{ now()->format('d/m/Y \\a \\l\\a\\s H:i') }} &middot; PlayDF - Inteligencia Artificial en PDF</p>
            <p>Examen Individual &middot; {{ $room->pdf_name }}</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btnImprimir = document.getElementById('btn-imprimir');
            if (btnImprimir) {
                btnImprimir.addEventListener('click', () => {
                    const elemento = document.getElementById('contenedor-reporte');
                    const opt = {
                        margin: 10,
                        filename: 'reporte-examen-{{ $room->code }}.pdf',
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2 },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    };
                    html2pdf().set(opt).from(elemento).save();
                });
            }
        });
    </script>
    <button onclick="toggleTheme()" class="theme-toggle-floating" title="Cambiar tema">
        <i class="fa-solid fa-moon icon-moon"></i>
        <i class="fa-solid fa-sun icon-sun"></i>
    </button>
</body>

</html>
