<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Menú de Exámenes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/modo-examen.css', 'resources/js/modo-examen.js'])

    <script>
        window.isLoggedIn = @json(Auth::check());
        window.loginRoute = "{{ route('login') }}";
    </script>
</head>

<body class="cuerpo-aplicacion font-sans min-h-screen flex flex-col items-center justify-center p-4">

    <a href="/"
        class="absolute top-6 left-6 text-slate-400 hover:text-red-500 transition-colors flex items-center gap-2 font-medium">
        <i class="fa-solid fa-arrow-left"></i> Volver a Inicio
    </a>

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

            <div
                class="tarjeta-menu p-6 rounded-3xl border border-slate-800 flex flex-col items-center text-center transition-all hover:border-emerald-500/50 cursor-pointer">
                <i class="fa-solid fa-user-astronaut text-emerald-500 text-3xl mb-3"></i>
                <h2 class="text-lg font-bold text-white mb-1">Modo Solitario</h2>
                <p class="text-slate-400 text-xs">Ponte a prueba tú mismo sin competir.</p>
            </div>

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
</body>

</html>
