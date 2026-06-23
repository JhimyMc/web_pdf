<header class="cabecera-principal sticky top-0 z-40 shadow-md">
    <div class="px-3 md:px-6 py-3 flex flex-row items-center justify-between">
        <div class="flex items-center gap-2 min-w-0">
            <button id="btn-abrir-menu-movil" class="boton-menu-movil md:hidden text-xl p-1 flex-shrink-0" title="Abrir menú">
                <i class="fa-solid fa-bars"></i>
            </button>

            <a href="{{ route('dashboard') }}" class="flex-shrink-0 flex items-center">
                <x-application-logo class="block h-8 md:h-10 w-auto fill-current text-gray-800" />
            </a>

            <div class="hidden sm:flex items-center gap-2 ml-2 md:ml-6 racha-nivel-contenedor px-3 py-1 rounded-full text-xs flex-shrink-0">
                <span class="text-amber-400"><i class="fa-solid fa-fire"></i> Racha: <span id="header-streak">-</span></span>
                <span class="text-blue-400"><i class="fa-solid fa-star"></i> Nivel <span id="header-level">-</span></span>
            </div>
        </div>

        <div class="flex items-center gap-2 md:gap-4 flex-shrink-0">
            <button onclick="toggleTheme()" class="theme-toggle-btn hidden md:flex" title="Cambiar tema">
                <i class="fa-solid fa-moon icon-moon"></i>
                <i class="fa-solid fa-sun icon-sun"></i>
            </button>
            @auth
                <div class="relative" id="user-spinner">
                    <button id="user-spinner-btn" class="flex items-center gap-2 text-xs md:text-sm usuario-identificado px-2 md:px-3 py-1.5 rounded-xl hover:bg-white/10 transition-colors">
                        <i class="fa-solid fa-user"></i>
                        <span class="max-w-[80px] md:max-w-none truncate hidden sm:inline">{{ Auth::user()->name }}</span>
                        <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" id="spinner-arrow"></i>
                    </button>
                    <div id="user-dropdown" class="hidden absolute right-0 top-full mt-2 w-52 rounded-xl shadow-2xl overflow-hidden z-50" style="background: var(--modal-bg); border: 1px solid var(--modal-border);">
                        <div class="px-4 py-3" style="border-bottom: 1px solid var(--modal-border);">
                            <p class="text-xs" style="color: var(--modal-subtext);">Conectado como</p>
                            <p class="text-sm font-semibold truncate" style="color: var(--modal-text);">{{ Auth::user()->name }}</p>
                        </div>
                        <button id="btn-install-app" class="w-full text-left px-4 py-2.5 text-xs transition-colors flex items-center gap-2.5" style="color: #3b82f6;">
                            <i class="fa-solid fa-download"></i> Instalar PlayDF
                        </button>
                        <div style="border-top: 1px solid var(--modal-border);"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-xs text-red-400 transition-colors flex items-center gap-2.5">
                                <i class="fa-solid fa-right-from-bracket"></i> Salir
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="text-xs md:text-sm enlace-autenticacion hidden sm:inline">Entrar</a>
                <a href="{{ route('register') }}"
                    class="boton-registrarse text-white text-[11px] md:text-xs font-bold px-2.5 md:px-3 py-2 rounded-lg transition-colors">Registrarse</a>
            @endauth
        </div>
    </div>
</header>
