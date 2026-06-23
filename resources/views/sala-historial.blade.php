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
    <meta name="description" content="Historial de tus salas de estudio — PlayDF">
    <title>Historial de Salas - PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/sala-historial.css', 'resources/js/sala-historial.js', 'resources/js/dark-toggle.js'])
</head>

<body class="cuerpo-aplicacion font-sans min-h-screen flex flex-col">

    @include('partials.header-unified')

    <div class="px-4 md:px-8 pt-4 pb-1">
        <div class="flex items-center gap-2 text-xs" style="color: var(--color-gris-oscuro)">
            <a href="/" class="hover:text-white transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-house text-[10px]"></i> PlayDF
            </a>
            <i class="fa-solid fa-chevron-right text-[9px]"></i>
            <span style="color: var(--color-gris-claro)">
                <i class="fa-solid fa-clock-rotate-left text-amber-500 mr-1"></i>Historial de Salas
            </span>
        </div>
    </div>

    <main class="contenedor-principal flex-grow w-full max-w-7xl mx-auto p-4 md:p-8">
        <div class="mb-8">
            <h1 class="text-2xl md:text-3xl font-black text-white mb-2">
                Historial de <span class="text-red-500">Examenes</span>
            </h1>
            <p class="text-slate-400 text-sm">Revisa métricas, descarga reportes y gestiona tus exámenes anteriores.</p>
            <div class="flex gap-3 mt-4">
                <a href="{{ route('sala.configurar') }}"
                    class="bg-red-600 hover:bg-red-500 text-white text-xs font-bold py-2 px-4 rounded-xl transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Nueva Sala Live
                </a>
                <a href="{{ route('solo-exam.configurar') }}"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold py-2 px-4 rounded-xl transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square"></i> Crear Cuestionario Individual
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 md:p-5 mb-6">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                        <input type="text" id="buscador-historial" placeholder="Buscar por código, PDF o fecha..."
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-4 py-3 text-sm text-white outline-none focus:border-red-500 transition-colors">
                    </div>
                </div>
                <div class="flex gap-3">
                    <select id="filtro-estado"
                        class="bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-300 outline-none focus:border-red-500">
                        <option value="todos">Todos los estados</option>
                        <option value="finalizado">Finalizados</option>
                        <option value="en_vivo">En vivo</option>
                        <option value="espera">En espera</option>
                        <option value="configurando">Configurando</option>
                        <option value="generando">Generando</option>
                    </select>
                </div>
            </div>
        </div>

        @if(count($salas) === 0)
            <div class="text-center py-20">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-900 border border-slate-800 mb-6">
                    <i class="fa-solid fa-clock-rotate-left text-3xl text-slate-600"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">No hay salas aún</h3>
                <p class="text-slate-400 text-sm mb-6">Crea tu primera sala desde el modo examen o un cuestionario individual.</p>
                <a href="{{ route('sala.configurar') }}"
                    class="inline-block bg-red-600 hover:bg-red-500 text-white font-bold py-3 px-8 rounded-xl transition-colors shadow-lg shadow-red-500/20">
                    <i class="fa-solid fa-plus mr-2"></i> Crear Sala
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5" id="contenedor-salas">
                @foreach ($salas as $sala)
                    <div class="sala-tarjeta bg-slate-900 border border-slate-800 rounded-2xl p-5 transition-all hover:-translate-y-1 hover:border-red-500/30 shadow-lg"
                         data-codigo="{{ $sala->code }}"
                         data-pdf="{{ $sala->pdf_name }}"
                         data-estado="{{ $sala->status }}">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="text-3xl font-black tracking-widest text-white">{{ $sala->code }}</span>
                            </div>
                            @php
                                $estadoColores = [
                                    'finalizado'    => 'bg-slate-700 text-slate-300',
                                    'en_vivo'       => 'bg-red-500/10 text-red-400 border border-red-500/30',
                                    'espera'        => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
                                    'configurando'  => 'bg-blue-500/10 text-blue-400 border border-blue-500/30',
                                    'generando'     => 'bg-purple-500/10 text-purple-400 border border-purple-500/30',
                                ];
                                $estadoClase = $estadoColores[$sala->status] ?? 'bg-slate-700 text-slate-300';
                                $estadoTexto = [
                                    'finalizado'   => 'Finalizado',
                                    'en_vivo'      => 'En Vivo',
                                    'espera'       => 'En Espera',
                                    'configurando' => 'Configurando',
                                    'generando'    => 'Generando',
                                ];
                            @endphp
                            <span class="text-[10px] font-bold uppercase px-2.5 py-1 rounded-lg border {{ $estadoClase }}">
                                {{ $estadoTexto[$sala->status] ?? $sala->status }}
                            </span>
                        </div>

                        <div class="space-y-2 mb-4 text-xs">
                            <p class="text-slate-400">
                                <i class="fa-solid fa-file-pdf text-red-500 mr-1.5"></i>
                                <span class="text-slate-300">{{ $sala->pdf_name }}</span>
                            </p>
                            <p class="text-slate-400">
                                <i class="fa-solid fa-calendar mr-1.5"></i>
                                {{ $sala->created_at->format('d/m/Y H:i') }}
                            </p>
                            <p class="text-slate-400">
                                <i class="fa-solid fa-list-ol text-blue-500 mr-1.5"></i>
                                {{ $sala->num_questions }} preguntas &middot;
                                <span class="capitalize">{{ $sala->difficulty }}</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-3 text-xs border-t border-slate-800 pt-4">
                            @if($sala->is_individual)
                            <div class="flex items-center gap-1.5 text-emerald-400">
                                <i class="fa-solid fa-user"></i>
                                <span>Individual</span>
                            </div>
                            @else
                            <div class="flex items-center gap-1.5 text-slate-400">
                                <i class="fa-solid fa-users"></i>
                                <span>{{ $sala->total_estudiantes }} estudiantes</span>
                            </div>
                            @endif
                            @if($sala->promedio > 0)
                                <div class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-chart-line text-emerald-400"></i>
                                    <span class="text-emerald-400 font-bold">{{ $sala->promedio }}%</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex gap-2 mt-4">
                            @if($sala->is_individual)
                            <a href="{{ route('solo-exam.reporte', $sala->code) }}"
                                class="flex-1 bg-slate-800 hover:bg-emerald-600 text-slate-300 hover:text-white font-medium py-2.5 rounded-xl transition-all text-xs flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-eye"></i> Ver Reporte
                            </a>
                            @else
                            <a href="{{ route('sala.reporte', $sala->code) }}"
                                class="flex-1 bg-slate-800 hover:bg-blue-600 text-slate-300 hover:text-white font-medium py-2.5 rounded-xl transition-all text-xs flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-eye"></i> Ver Reporte
                            </a>
                            <a href="{{ route('sala.dashboard', $sala->code) }}"
                                class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white font-medium py-2.5 rounded-xl transition-all text-xs flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-gauge"></i> Dashboard
                            </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>

    @include('partials.drawer-unified')
    @include('partials.footer')
    @include('partials.scripts-unified')

</body>

</html>
