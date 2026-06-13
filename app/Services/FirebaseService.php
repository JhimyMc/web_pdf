<?php

namespace App\Services;

use Kreait\Firebase\Auth;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Auth\InvalidToken;
class FirebaseService
{
    private static ?Auth $auth = null;

    public static function auth(): Auth
    {
        if (self::$auth === null) {
            $credentialsPath = env('FIREBASE_CREDENTIALS', 'storage/app/firebase/service-account.json');

            // Si la ruta ya contiene 'storage/' al inicio, usar base_path() para no duplicar
            if (str_starts_with($credentialsPath, 'storage/')) {
                $fullPath = base_path($credentialsPath);
            } else {
                // Si es ruta absoluta (Windows: C:\ o Unix: /), usarla directo
                $isAbsolute = str_starts_with($credentialsPath, '/') || preg_match('~^[A-Z]:\\~i', $credentialsPath);
                $fullPath = $isAbsolute ? $credentialsPath : base_path($credentialsPath);
            }

            if (!file_exists($fullPath)) {
                Log::error('Firebase: Service account file not found', ['path' => $fullPath]);
                throw new \RuntimeException('Firebase service account file not found: ' . $fullPath);
            }

            $factory = (new Factory)->withServiceAccount($fullPath);
            self::$auth = $factory->createAuth();
        }
        return self::$auth;
    }

    /**
     * Verificar un ID token de Firebase y devolver los claims
     */
    public static function verifyIdToken(string $idToken): ?array
    {
        try {
            $verifiedToken = self::auth()->verifyIdToken(
                $idToken,
                checkIfRevoked: false,
                leewayInSeconds: 300
            );
            $claims = $verifiedToken->claims();

            return [
                'uid'   => $claims->get('sub'),
                'email' => $claims->get('email'),
                'name'  => $claims->get('name', $claims->get('email')),
                'photo' => $claims->get('picture'),
            ];
        } catch (InvalidToken $e) {
            Log::error('Firebase: Token inválido', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Firebase: Error al verificar token', [
                'error'   => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
}
