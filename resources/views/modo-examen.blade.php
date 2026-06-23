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
    <meta name="description" content="Menú de exámenes con IA — PlayDF">
    <title>PlayDF - Menú de Exámenes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/modo-examen.css', 'resources/js/modo-examen.js', 'resources/js/dark-toggle.js'])

    <script>
        window.isLoggedIn = @json(Auth::check());
        window.loginRoute = "{{ route('login') }}";
    </script>
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
                <i class="fa-solid fa-graduation-cap text-red-500 mr-1"></i>Modo Examen
            </span>
        </div>
    </div>

    <main class="flex-1 flex flex-col items-center justify-center p-4">

    <div class="max-w-4xl w-full">
        <div class="text-center mb-10">
            <h1 class="text-3xl md:text-4xl font-black text-white mb-3">Modo <span class="text-red-500">Examen</span>
            </h1>
            <p class="text-slate-400 text-sm md:text-base">Selecciona cómo deseas poner a prueba tus conocimientos hoy.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative">

            <div
                class="tarjeta-menu p-8 rounded-3xl border border-slate-800 flex flex-col items-center text-center transition-all hover:-translate-y-2">
                <div class="bg-blue-500/10 p-5 rounded-full mb-5">
                    <i class="fa-solid fa-right-to-bracket text-blue-500 text-4xl"></i>
                </div>
                <h2 class="text-xl font-bold text-white mb-2">Unirse a Sala</h2>
                <p class="text-slate-400 text-xs mb-6 flex-1">Ingresa con un código de sala provisto por un docente o
                    creador. No requiere registro.</p>

                <div class="w-full space-y-3 mb-4">
                    <input type="text" id="input-codigo-sala" placeholder="Código de 5 dígitos"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-center text-white uppercase font-bold tracking-widest focus:border-blue-500 outline-none"
                        maxlength="5">
                    <input type="text" id="input-nombre-estudiante" placeholder="Tu Nombre o Apodo"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-center text-white focus:border-blue-500 outline-none">
                </div>
                <button id="btn-unirse-sala"
                    class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition-colors shadow-lg shadow-blue-500/20">
                    Ingresar
                </button>
            </div>

            <div
                class="tarjeta-menu relative overflow-hidden p-8 rounded-3xl border border-slate-800 flex flex-col items-center text-center transition-all hover:-translate-y-2">
                @if (!Auth::check())
                    <div
                        class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm z-10 flex flex-col items-center justify-center p-6 rounded-3xl border border-slate-800">
                        <i class="fa-solid fa-lock text-red-500 text-3xl mb-3"></i>
                        <p class="text-white font-bold mb-1">Acceso Restringido</p>
                        <p class="text-slate-400 text-xs mb-4">Inicia sesión para crear salas.</p>
                        <a href="{{ route('login') }}"
                            class="bg-red-600 hover:bg-red-500 text-white font-bold py-2 px-6 rounded-xl transition-colors text-sm">Iniciar
                            Sesión</a>
                    </div>
                @endif
                <div class="bg-red-500/10 p-5 rounded-full mb-5 flex items-center justify-center">
                @include('partials.iconos', ['name' => 'adjustments-horizontal', 'class' => 'text-red-500', 'size' => 36])
            </div>
            <h2 class="text-xl font-bold text-white mb-2">Crear Sala Live</h2>
            <p class="text-slate-400 text-xs mb-6 flex-1">Configura un cuestionario en vivo, invita a participantes
                y evalúa resultados en tiempo real.</p>
            <button id="btn-crear-sala"
                class="w-full bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-xl transition-colors shadow-lg shadow-red-500/20">
                Configurar Nueva Sala
            </button>
            </div>

            <a href="{{ route('solo-exam.configurar') }}"
                class="tarjeta-menu p-6 rounded-3xl border border-slate-800 flex flex-col items-center text-center transition-all hover:border-emerald-500/50 cursor-pointer">
                <i class="fa-solid fa-pen-to-square text-emerald-500 text-3xl mb-3"></i>
                <h2 class="text-lg font-bold text-white mb-1">Crear Cuestionario</h2>
                <p class="text-slate-400 text-xs">Genera tu propio examen individual con IA.</p>
            </a>

            <div id="btn-historial"
                class="tarjeta-menu p-6 rounded-3xl border border-slate-800 flex flex-col items-center text-center transition-all hover:border-amber-500/50 cursor-pointer relative overflow-hidden">
                @if (!Auth::check())
                    <div
                        class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm z-10 flex flex-col items-center justify-center rounded-3xl">
                        <i class="fa-solid fa-lock text-amber-500"></i>
                    </div>
                @else
                    <div class="absolute inset-0 z-10" id="btn-historial-overlay"></div>
                @endif
                <i class="fa-solid fa-clock-rotate-left text-amber-500 text-3xl mb-3"></i>
                <h2 class="text-lg font-bold text-white mb-1">Historial de Salas</h2>
                <p class="text-slate-400 text-xs">Revisa métricas y descargas de PDF.</p>
            </div>

        </div>
    </div>
    </main>

    @include('partials.footer')
    @include('partials.drawer-unified')
    @include('partials.scripts-unified')
</body>

</html>
