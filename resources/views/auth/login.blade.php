<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mt-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-playdf-rojo z-20">
                    @include('partials.iconos', ['name' => 'mail', 'size' => 20])
                </div>
                
                <input id="email" class="block w-full pl-10 pr-3 py-3 bg-transparent border border-gray-600 text-white rounded-md focus:border-playdf-rojo focus:ring-1 focus:ring-playdf-rojo outline-none appearance-none peer transition-colors" 
                       type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder=" " />
                
                <label for="email" class="absolute text-sm text-gray-400 bg-playdf-card px-1 duration-300 transform -translate-y-1/2 scale-75 top-0 z-10 origin-[0] left-10 peer-focus:text-playdf-rojo peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-0 peer-focus:scale-75 peer-focus:-translate-y-1/2 pointer-events-none">
                    Correo Electrónico
                </label>
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-playdf-rojo" />
        </div>

        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-playdf-rojo z-20">
                    @include('partials.iconos', ['name' => 'lock', 'size' => 20])
                </div>
                
                <input id="password" class="block w-full pl-10 pr-10 py-3 bg-transparent border border-gray-600 text-white rounded-md focus:border-playdf-rojo focus:ring-1 focus:ring-playdf-rojo outline-none appearance-none peer transition-colors" 
                       type="password" name="password" required autocomplete="current-password" placeholder=" " />
                
                <label for="password" class="absolute text-sm text-gray-400 bg-playdf-card px-1 duration-300 transform -translate-y-1/2 scale-75 top-0 z-10 origin-[0] left-10 peer-focus:text-playdf-rojo peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-0 peer-focus:scale-75 peer-focus:-translate-y-1/2 pointer-events-none">
                    Contraseña
                </label>

                <button type="button" onclick="togglePassword('password', 'eye-login', 'eye-off-login')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-playdf-rojo hover:text-red-400 focus:outline-none transition-colors z-20">
                    <span id="eye-login">@include('partials.iconos', ['name' => 'eye', 'size' => 20])</span>
                    <span id="eye-off-login" class="hidden">@include('partials.iconos', ['name' => 'eye-off', 'size' => 20])</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-playdf-rojo" />
        </div>

        <div class="mt-8">
            <button type="submit" class="w-full flex justify-center items-center h-[55px] border border-transparent rounded-xl shadow-sm text-base font-bold text-white bg-playdf-rojo hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-playdf-rojo focus:ring-offset-playdf-dark transition-colors">
                INGRESAR
            </button>
        </div>

        <div class="flex items-center justify-center mt-6">
            <span class="text-gray-400 text-sm">¿No tienes cuenta?</span>
            <a href="{{ route('register') }}" class="ml-1 text-sm text-playdf-rojo font-bold hover:text-red-400 transition-colors">
                Regístrate aquí
            </a>
        </div>
    </form>

    <script>
        function togglePassword(inputId, eyeId, eyeOffId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(eyeId);
            const eyeOff = document.getElementById(eyeOffId);
            
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.add('hidden');
                eyeOff.classList.remove('hidden');
            } else {
                input.type = 'password';
                eye.classList.remove('hidden');
                eyeOff.classList.add('hidden');
            }
        }
    </script>
</x-guest-layout>