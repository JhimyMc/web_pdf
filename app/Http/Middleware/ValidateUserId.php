<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateUserId
{
    /**
     * Verificar que el user_id enviado en el request exista en la tabla users.
     * Si no existe, devuelve 404 antes de que el controlador haga cualquier cosa.
     *
     * NOTA: Solo intercepta requests que traen user_id como query param, body param,
     *       o route param. Requests sin user_id pasan sin validación.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user_id
            ?? $request->query('user_id')
            ?? $request->route('user_id');

        if ($userId !== null) {
            if (!is_numeric($userId) || !User::where('id', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado. Por favor inicia sesión nuevamente.',
                ], 404);
            }
        }

        return $next($request);
    }
}
