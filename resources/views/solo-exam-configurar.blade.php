<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4A90E2">
    <meta name="description" content="Crea cuestionarios con IA — PlayDF">
    <title>PlayDF - Crear Cuestionario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/solo-exam.css', 'resources/js/app.js', 'resources/js/dark-toggle.js'])

    <script>
        window.isLoggedIn = @json(Auth::check());
        window.loginRoute = "{{ route('login') }}";
    </script>
</head>

<body class="cuerpo-aplicacion font-sans min-h-screen flex flex-col">

    <header class="cabecera-principal px-4 md:px-6 py-4 flex items-center justify-between shadow-md sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <button id="btn-abrir-menu-movil" class="boton-menu-movil md:hidden text-xl p-1 mr-1" title="Abrir menú">
                <i class="fa-solid fa-bars"></i>
            </button>

            @include('partials.logo')

            <div class="hidden sm:flex items-center gap-3 ml-2 md:ml-6 racha-nivel-contenedor px-3 py-1 rounded-full text-xs">
                <span class="text-amber-400"><i class="fa-solid fa-fire"></i> Racha: <span id="header-streak">-</span></span>
                <span class="text-blue-400"><i class="fa-solid fa-star"></i> Nivel <span id="header-level">-</span></span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="toggleTheme()" class="theme-toggle-btn" title="Cambiar tema">
                <i class="fa-solid fa-moon icon-moon"></i>
                <i class="fa-solid fa-sun icon-sun"></i>
            </button>
            @auth
                <span class="text-xs md:text-sm usuario-identificado max-w-[120px] md:max-w-none truncate">
                    <i class="fa-solid fa-user mr-1 md:mr-2"></i>{{ Auth::user()->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="boton-salir text-xs hover:underline">Salir</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-xs md:text-sm enlace-autenticacion">Entrar</a>
                <a href="{{ route('register') }}" class="boton-registrarse text-white text-[11px] md:text-xs font-bold px-2.5 md:px-3 py-2 rounded-lg transition-colors">Registrarse</a>
            @endauth
        </div>
    </header>

    <div class="px-4 md:px-8 pt-4 pb-1">
        <div class="flex items-center gap-2 text-xs" style="color: var(--color-gris-oscuro)">
            <a href="/" class="hover:text-white transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-house text-[10px]"></i> PlayDF
            </a>
            <i class="fa-solid fa-chevron-right text-[9px]"></i>
            <a href="/modo-examen" class="hover:text-white transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-graduation-cap text-red-500 text-[10px]"></i> Modo Examen
            </a>
            <i class="fa-solid fa-chevron-right text-[9px]"></i>
            <span style="color: var(--color-gris-claro)">
                <i class="fa-solid fa-pen-to-square text-emerald-400 mr-1"></i>Crear Cuestionario
            </span>
        </div>
    </div>

    <main class="contenedor-principal flex-grow flex items-center justify-center p-4 md:p-8">
        <div class="tarjeta-configuracion max-w-xl w-full p-6 md:p-10 rounded-3xl border border-slate-800 relative overflow-hidden">
            <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-red-600/10 rounded-full blur-3xl"></div>

            <div class="text-center mb-8 relative z-10">
                <div class="inline-block bg-red-500/10 p-4 rounded-full mb-4">
                    <i class="fa-solid fa-pen-to-square text-red-500 text-3xl"></i>
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-white mb-2">Crear <span class="text-red-500">Cuestionario</span></h1>
                <p class="text-slate-400 text-sm">Genera un examen individual para ponerte a prueba tú mismo.</p>
            </div>

            <form action="{{ route('solo-exam.crear') }}" method="POST" class="space-y-6 relative z-10">
                @csrf

                <div class="grupo-formulario">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-file-pdf text-red-500 mr-1"></i> Documento Base
                    </label>
                    <select name="document_id" id="select-documento" required
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 outline-none hover:border-slate-700 transition-colors cursor-pointer appearance-none custom-select">
                        <option value="" disabled selected>Selecciona un PDF de tu biblioteca...</option>
                        @foreach ($documentos as $doc)
                            <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>
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
                            <option value="30">30 Preguntas</option>
                            <option value="50">50 Preguntas</option>
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
                    class="w-full bg-red-600 hover:bg-red-500 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-red-500/20 flex items-center justify-center gap-2 active:scale-[0.98]">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Generar y Empezar Examen <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </main>

    @include('partials.footer')
</body>

</html>
