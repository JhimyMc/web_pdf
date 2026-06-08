<?php

namespace App\Http\Controllers;

use App\Models\UserGamification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GamificationController extends Controller
{
    /**
     * Obtener stats de gamificación del usuario actual (web).
     */
    public function stats()
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['success' => false], 401);
        }

        $gamification = UserGamification::firstOrCreate(
            ['user_id' => $userId],
            ['xp' => 0, 'level' => 1, 'current_streak' => 0, 'longest_streak' => 0]
        );

        return response()->json([
            'success' => true,
            'gamification' => $gamification->toArray(),
        ]);
    }

    /**
     * Obtener stats de gamificación por user_id (API - Android).
     */
    public function apiStats(Request $request)
    {
        $request->validate(['user_id' => 'required|integer']);

        $gamification = UserGamification::firstOrCreate(
            ['user_id' => $request->user_id],
            ['xp' => 0, 'level' => 1, 'current_streak' => 0, 'longest_streak' => 0]
        );

        return response()->json([
            'success' => true,
            'gamification' => $gamification->toArray(),
        ]);
    }

    /**
     * Tabla de clasificación entre docentes (web).
     */
    public function leaderboard()
    {
        $top = UserGamification::with('user:id,name,email')
            ->orderByDesc('xp')
            ->orderByDesc('level')
            ->limit(20)
            ->get()
            ->map(fn($g, $i) => [
                'position'    => $i + 1,
                'name'        => $g->user->name ?? 'Anónimo',
                'level'       => $g->level,
                'xp'          => $g->xp,
                'total_reviews' => $g->total_reviews,
                'current_streak' => $g->current_streak,
            ]);

        return response()->json(['success' => true, 'leaderboard' => $top]);
    }

    /**
     * Tabla de clasificación (API - Android).
     */
    public function apiLeaderboard(Request $request)
    {
        $top = UserGamification::with('user:id,name,email')
            ->orderByDesc('xp')
            ->orderByDesc('level')
            ->limit(20)
            ->get()
            ->map(fn($g, $i) => [
                'position'    => $i + 1,
                'name'        => $g->user->name ?? 'Anónimo',
                'level'       => $g->level,
                'xp'          => $g->xp,
                'total_reviews' => $g->total_reviews,
                'current_streak' => $g->current_streak,
            ]);

        return response()->json(['success' => true, 'leaderboard' => $top]);
    }

    /**
     * Notificaciones pendientes para el usuario (web y API).
     * Retorna si tiene repeticiones espaciadas pendientes hoy.
     */
    public function pendingNotifications(Request $request)
    {
        $userId = $request->user_id ?? Auth::id();
        if (!$userId) {
            return response()->json(['notifications' => []]);
        }

        $notifications = [];
        $gamification = UserGamification::where('user_id', $userId)->first();

        // Verificar si tiene tarjetas SRS pendientes hoy
        $dueCount = DB::table('srs_cards')
            ->where('user_id', $userId)
            ->where('next_review_at', '<=', now())
            ->count();

        if ($dueCount > 0) {
            $notifications[] = [
                'type'    => 'srs_review',
                'title'   => 'Repeticiones Pendientes',
                'message' => "Tienes $dueCount tarjetas para repasar hoy",
                'icon'    => 'calendar-event',
                'color'   => '#F59E0B',
                'url'     => '/repeticion-espaciada',
            ];
        }

        // Verificar si tiene tarjetas difíciles sin practicar
        $difficultCount = DB::table('study_card_difficult')
            ->where('user_id', $userId)
            ->count();

        if ($difficultCount > 0) {
            $notifications[] = [
                'type'    => 'hangman',
                'title'   => 'Ahorcado Disponible',
                'message' => "Tienes $difficultCount tarjetas difíciles para practicar con el ahorcado",
                'icon'    => 'puzzle',
                'color'   => '#8B5CF6',
                'url'     => '/ahorcado',
            ];
        }

        // Logros recientes sin leer
        if ($gamification && !empty($gamification->achievements)) {
            $recentCount = count($gamification->achievements);
            if ($recentCount > 0) {
                $notifications[] = [
                    'type'    => 'achievement',
                    'title'   => 'Logros Desbloqueados',
                    'message' => "Has desbloqueado $recentCount logros",
                    'icon'    => 'award',
                    'color'   => '#10B981',
                    'url'     => '/profile',
                ];
            }
        }

        return response()->json(['notifications' => $notifications]);
    }
}
