<?php
// C:\laragon\www\web-pdf\app\Http\Controllers\Api\AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Registro de nuevos usuarios
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'exito' => false,
                'errores' => $validator->errors()
            ], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'exito' => true,
            'mensaje' => 'Usuario registrado exitosamente',
            'usuario' => $user
        ], 201);
    }

    /**
     * Inicio de sesión
     */
    public function login(Request $request)
    {
        // 1. Validamos que envíen correo y contraseña
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // 2. Intentamos iniciar sesión
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();

            return response()->json([
                'exito' => true,
                'mensaje' => 'Login exitoso',
                'usuario' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
        }

        // Si falla la contraseña o correo:
        return response()->json([
            'exito' => false,
            'mensaje' => 'Credenciales incorrectas'
        ], 401);
    }

    /**
     * Actualizar perfil
     */
    /**
     * Actualizar perfil
     */
    // Asegúrate de tener esta importación arriba del archivo

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // EXPLICACIÓN: Buscamos al usuario directamente en la base de datos por su ID.
        // Esto le asegura a Laravel y a tu editor que '$user' es un modelo Eloquent real.
        $user = User::find($request->user()->id);

        if (!$user) {
            return response()->json([
                'exito' => false,
                'mensaje' => 'Usuario no encontrado'
            ], 404);
        }

        // Actualizamos el nombre y guardamos
        $user->name = $request->name;
        $user->save(); // <--- Aquí ya no dará ningún error

        return response()->json([
            'exito' => true,
            'mensaje' => 'Nombre actualizado correctamente',
            'id' => $user->id
        ]);
    }
}
