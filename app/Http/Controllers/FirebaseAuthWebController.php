<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FirebaseAuthWebController extends Controller
{
    /**
     * Manejar el login con Google desde la vista web de Laravel
     * Recibe el idToken del JS SDK, lo verifica y crea/inicia sesión
     */
    public function handleGoogleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        Log::info('Firebase Web: Intento de login con Google', ['email' => '']);

        // 1. Verificar el token de Firebase
        $firebaseUser = FirebaseService::verifyIdToken($request->id_token);

        if (!$firebaseUser) {
            return back()->withErrors([
                'google' => 'No se pudo verificar la identidad con Google. Intenta de nuevo.'
            ])->onlyInput('email');
        }

        Log::info('Firebase Web: Token verificado', ['email' => $firebaseUser['email']]);

        // 2. Buscar o crear usuario
        $user = User::where('firebase_uid', $firebaseUser['uid'])->first();

        if (!$user) {
            $user = User::where('email', $firebaseUser['email'])->first();

            if ($user) {
                // Vincular cuenta existente
                $user->update([
                    'firebase_uid' => $firebaseUser['uid'],
                    'provider' => 'google',
                ]);
            } else {
                // Crear nuevo usuario desde Google
                $user = User::create([
                    'name' => $firebaseUser['name'],
                    'email' => $firebaseUser['email'],
                    'firebase_uid' => $firebaseUser['uid'],
                    'provider' => 'google',
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
            }
        }

        // 3. Login en Laravel
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
