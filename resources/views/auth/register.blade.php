<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mt-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-playdf-rojo z-20">
                    @include('partials.iconos', ['name' => 'user', 'size' => 20])
                </div>
                <input id="name" class="block w-full pl-10 pr-3 py-3 bg-transparent border border-gray-600 text-white rounded-md focus:border-playdf-rojo focus:ring-1 focus:ring-playdf-rojo outline-none appearance-none peer transition-colors" 
                       type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder=" " />
                <label for="name" class="absolute text-sm text-gray-400 bg-playdf-card px-1 duration-300 transform -translate-y-1/2 scale-75 top-0 z-10 origin-[0] left-10 peer-focus:text-playdf-rojo peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-0 peer-focus:scale-75 peer-focus:-translate-y-1/2 pointer-events-none">
                    Nombre Completo
                </label>
            </div>
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-playdf-rojo" />
        </div>

        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-playdf-rojo z-20">
                    @include('partials.iconos', ['name' => 'mail', 'size' => 20])
                </div>
                <input id="email" class="block w-full pl-10 pr-3 py-3 bg-transparent border border-gray-600 text-white rounded-md focus:border-playdf-rojo focus:ring-1 focus:ring-playdf-rojo outline-none appearance-none peer transition-colors" 
                       type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder=" " />
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
                       type="password" name="password" required autocomplete="new-password" placeholder=" " />
                <label for="password" class="absolute text-sm text-gray-400 bg-playdf-card px-1 duration-300 transform -translate-y-1/2 scale-75 top-0 z-10 origin-[0] left-10 peer-focus:text-playdf-rojo peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-0 peer-focus:scale-75 peer-focus:-translate-y-1/2 pointer-events-none">
                    Contraseña
                </label>
                <button type="button" onclick="togglePassword('password', 'eye-reg', 'eye-off-reg')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-playdf-rojo hover:text-red-400 focus:outline-none transition-colors z-20">
                    <span id="eye-reg">@include('partials.iconos', ['name' => 'eye', 'size' => 20])</span>
                    <span id="eye-off-reg" class="hidden">@include('partials.iconos', ['name' => 'eye-off', 'size' => 20])</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-playdf-rojo" />
        </div>

        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-playdf-rojo z-20">
                    @include('partials.iconos', ['name' => 'lock', 'size' => 20])
                </div>
                <input id="password_confirmation" class="block w-full pl-10 pr-10 py-3 bg-transparent border border-gray-600 text-white rounded-md focus:border-playdf-rojo focus:ring-1 focus:ring-playdf-rojo outline-none appearance-none peer transition-colors" 
                       type="password" name="password_confirmation" required autocomplete="new-password" placeholder=" " />
                <label for="password_confirmation" class="absolute text-sm text-gray-400 bg-playdf-card px-1 duration-300 transform -translate-y-1/2 scale-75 top-0 z-10 origin-[0] left-10 peer-focus:text-playdf-rojo peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-0 peer-focus:scale-75 peer-focus:-translate-y-1/2 pointer-events-none">
                    Confirmar Contraseña
                </label>
                <button type="button" onclick="togglePassword('password_confirmation', 'eye-conf', 'eye-off-conf')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-playdf-rojo hover:text-red-400 focus:outline-none transition-colors z-20">
                    <span id="eye-conf">@include('partials.iconos', ['name' => 'eye', 'size' => 20])</span>
                    <span id="eye-off-conf" class="hidden">@include('partials.iconos', ['name' => 'eye-off', 'size' => 20])</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-playdf-rojo" />
        </div>

        <div class="mt-8">
            <button type="submit" class="w-full flex justify-center items-center h-[55px] border border-transparent rounded-xl shadow-sm text-base font-bold text-white bg-playdf-rojo hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-playdf-rojo focus:ring-offset-playdf-dark transition-colors">
                REGISTRARSE
            </button>
        </div>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- SEPARADOR visual --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="relative mt-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-600"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="bg-playdf-card px-3 text-gray-400">o regístrate con</span>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- BOTÓN GOOGLE SIGN-IN (Firebase JS SDK) --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="mt-6">
            <button type="button" id="btn-google-register"
                    class="w-full flex justify-center items-center h-[55px] border border-red-800/50 rounded-xl shadow-sm text-base font-medium text-white bg-white/5 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-playdf-rojo focus:ring-offset-playdf-dark transition-all duration-200">
                <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                <span id="google-btn-text">Continuar con Google</span>
                <svg id="google-btn-spinner" class="hidden animate-spin ml-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </button>
            @error('google')
                <p class="mt-2 text-sm text-playdf-rojo">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-center mt-6">
            <span class="text-gray-400 text-sm">¿Ya tienes cuenta?</span>
            <a href="{{ route('login') }}" class="ml-1 text-sm text-playdf-rojo font-bold hover:text-red-400 transition-colors">
                Inicia sesión
            </a>
        </div>
    </form>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- FIREBASE JS SDK + Google Sign-In --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-app.js";
        import { getAuth, signInWithPopup, GoogleAuthProvider } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";

        const firebaseConfig = {
            apiKey:            @json(env('FIREBASE_API_KEY')),
            authDomain:        @json(env('FIREBASE_AUTH_DOMAIN')),
            projectId:         @json(env('FIREBASE_PROJECT_ID')),
            storageBucket:     @json(env('FIREBASE_STORAGE_BUCKET')),
            messagingSenderId: @json(env('FIREBASE_MESSAGING_SENDER_ID')),
            appId:             @json(env('FIREBASE_APP_ID')),
            measurementId:     @json(env('FIREBASE_MEASUREMENT_ID'))
        };

        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        const provider = new GoogleAuthProvider();

        const btnGoogle = document.getElementById('btn-google-register');
        const btnText   = document.getElementById('google-btn-text');
        const btnSpinner = document.getElementById('google-btn-spinner');

        btnGoogle.addEventListener('click', async () => {
            btnGoogle.disabled = true;
            btnText.textContent = 'Conectando...';
            btnSpinner.classList.remove('hidden');

            try {
                const result = await signInWithPopup(auth, provider);
                const idToken = await result.user.getIdToken();

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("auth.google") }}';

                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';
                form.appendChild(csrfInput);

                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'id_token';
                tokenInput.value = idToken;
                form.appendChild(tokenInput);

                document.body.appendChild(form);
                form.submit();

            } catch (error) {
                console.error('Error en Google Sign-In:', error);
                btnGoogle.disabled = false;
                btnText.textContent = 'Continuar con Google';
                btnSpinner.classList.add('hidden');

                let msg = 'Error al conectar con Google';
                if (error.code === 'auth/popup-closed-by-user') {
                    msg = 'Inicio de sesión cancelado';
                } else if (error.code === 'auth/network-request-failed') {
                    msg = 'Error de conexión. Revisa tu internet.';
                }
                alert(msg);
            }
        });
    </script>

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