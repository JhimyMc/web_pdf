<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirebaseAuthController extends Controller
{
    /**
     * Verificar token de Firebase y responder con datos del usuario.
     * Usado exclusivamente por la app Android (sin sesiones Laravel).
     * La app gestiona su propia sesión con SessionManager local.
     */
    public function loginWithGoogle(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        Log::info('Firebase API: Intento de login con Google', ['id_token_start' => substr($request->id_token, 0, 20) . '...']);

        // 1. Verificar el token de Firebase (con tolerancia de reloj)
        $firebaseUser = FirebaseService::verifyIdToken($request->id_token);

        if (!$firebaseUser) {
            Log::error('Firebase API: Token inválido');
            return response()->json([
                'exito' => false,
                'mensaje' => 'Token de Firebase inválido o expirado'
            ], 401);
        }

        Log::info('Firebase API: Token verificado', ['email' => $firebaseUser['email']]);

        // 2. Buscar usuario por firebase_uid o por email
        $user = User::where('firebase_uid', $firebaseUser['uid'])->first();

        if (!$user) {
            $user = User::where('email', $firebaseUser['email'])->first();

            if ($user) {
                $user->update([
                    'firebase_uid' => $firebaseUser['uid'],
                    'provider' => 'google',
                ]);
            } else {
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

        Log::info('Firebase API: Login exitoso', ['user_id' => $user->id, 'email' => $user->email]);

        // 3. Responder con datos del usuario (sin sesiones Laravel)
        return response()->json([
            'exito' => true,
            'mensaje' => 'Inicio de sesión con Google exitoso',
            'usuario' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }
}
