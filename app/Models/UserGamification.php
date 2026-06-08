<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGamification extends Model
{
    protected $table = 'user_gamification';

    protected $fillable = [
        'user_id', 'xp', 'level', 'total_reviews', 'total_mastered',
        'hangman_wins', 'current_streak', 'longest_streak', 'last_active_date', 'achievements',
    ];

    protected $casts = [
        'xp'                => 'integer',
        'level'             => 'integer',
        'total_reviews'     => 'integer',
        'total_mastered'    => 'integer',
        'hangman_wins'      => 'integer',
        'current_streak'    => 'integer',
        'longest_streak'    => 'integer',
        'last_active_date'  => 'date',
        'achievements'      => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * XP necesario para subir de nivel.
     * Fórmula: level * 100 + (level^2 * 10)
     */
    public function xpForNextLevel(): int
    {
        $lvl = $this->level;
        return $lvl * 100 + ($lvl * $lvl * 10);
    }

    /**
     * Porcentaje de progreso hacia el siguiente nivel (0-100).
     */
    public function levelProgress(): int
    {
        $needed = $this->xpForNextLevel();
        if ($needed <= 0) return 100;
        return min(100, (int)(($this->xp * 100) / $needed));
    }

    /**
     * Agregar XP y subir de nivel si es necesario.
     * Retorna true si subió de nivel.
     */
    public function addXp(int $amount): bool
    {
        $this->xp += $amount;
        $leveledUp = false;

        // Subir de nivel mientras haya suficiente XP
        while ($this->xp >= $this->xpForNextLevel()) {
            $this->xp -= $this->xpForNextLevel();
            $this->level += 1;
            $leveledUp = true;
        }

        $this->save();
        return $leveledUp;
    }

    /**
     * Actualizar racha de días consecutivos.
     */
    public function updateStreak(): void
    {
        $today = now()->toDateString();
        $lastActive = $this->last_active_date?->toDateString();

        if ($lastActive === $today) {
            // Ya tuvo actividad hoy, no hacer nada
            return;
        }

        $yesterday = now()->subDay()->toDateString();

        if ($lastActive === $yesterday) {
            // Día consecutivo
            $this->current_streak += 1;
        } elseif ($lastActive === null) {
            // Primera vez
            $this->current_streak = 1;
        } else {
            // Racha rota
            $this->current_streak = 1;
        }

        if ($this->current_streak > $this->longest_streak) {
            $this->longest_streak = $this->current_streak;
        }

        $this->last_active_date = $today;
        $this->save();
    }

    /**
     * Verificar y desbloquear logros.
     * Retorna array de logros nuevos desbloqueados.
     */
    public function checkAchievements(): array
    {
        $current = $this->achievements ?? [];
        $newOnes = [];

        $allAchievements = [
            'first_review'    => ['name' => 'Primer Repaso',       'description' => 'Completaste tu primera revisión',        'icon' => 'player-play',  'xp' => 50,  'condition' => fn($g) => $g->total_reviews >= 1],
            'ten_reviews'     => ['name' => 'Estudiante Dedicado', 'description' => 'Completaste 10 revisiones',              'icon' => 'star',         'xp' => 100, 'condition' => fn($g) => $g->total_reviews >= 10],
            'fifty_reviews'   => ['name' => 'Maratonista Mental',  'description' => 'Completaste 50 revisiones',              'icon' => 'flame',        'xp' => 250, 'condition' => fn($g) => $g->total_reviews >= 50],
            'hundred_reviews' => ['name' => 'Leyenda del Estudio',  'description' => 'Completaste 100 revisiones',             'icon' => 'trophy',       'xp' => 500, 'condition' => fn($g) => $g->total_reviews >= 100],
            'first_mastered'  => ['name' => 'Primera Dominada',     'description' => 'Dominaste tu primera tarjeta',           'icon' => 'circle-check','xp' => 75,  'condition' => fn($g) => $g->total_mastered >= 1],
            'ten_mastered'    => ['name' => 'Maestro Cartesiano',   'description' => 'Dominaste 10 tarjetas',                  'icon' => 'stack-2',     'xp' => 200, 'condition' => fn($g) => $g->total_mastered >= 10],
            'fifty_mastered'  => ['name' => 'Sabio Absoluto',       'description' => 'Dominaste 50 tarjetas',                  'icon' => 'crown',       'xp' => 500, 'condition' => fn($g) => $g->total_mastered >= 50],
            'streak_3'        => ['name' => 'Constancia de 3',      'description' => 'Racha de 3 días consecutivos',           'icon' => 'calendar',    'xp' => 75,  'condition' => fn($g) => $g->current_streak >= 3],
            'streak_7'        => ['name' => 'Racha de Fuego',       'description' => '7 días seguidos estudiando',             'icon' => 'flame',       'xp' => 200, 'condition' => fn($g) => $g->current_streak >= 7],
            'streak_30'       => ['name' => 'Disciplina Total',     'description' => '30 días consecutivos de estudio',        'icon' => 'award',       'xp' => 1000,'condition' => fn($g) => $g->current_streak >= 30],
            'hangman_first'   => ['name' => 'Ahorcado Principiante','description' => 'Ganaste tu primera partida de ahorcado', 'icon' => 'puzzle',      'xp' => 30,  'condition' => fn($g) => ($g->hangman_wins ?? 0) >= 1],
            'hangman_10'      => ['name' => 'Ahorcado Experto',     'description' => 'Ganaste 10 partidas de ahorcado',        'icon' => 'puzzle',      'xp' => 150, 'condition' => fn($g) => ($g->hangman_wins ?? 0) >= 10],
        ];

        foreach ($allAchievements as $key => $achievement) {
            if (!in_array($key, $current) && $achievement['condition']($this)) {
                $current[] = $key;
                $newOnes[] = [
                    'key'         => $key,
                    'name'        => $achievement['name'],
                    'description' => $achievement['description'],
                    'icon'        => $achievement['icon'],
                    'xp'          => $achievement['xp'],
                ];
            }
        }

        if (count($newOnes) > 0) {
            $this->achievements = $current;
            $this->save();
        }

        return $newOnes;
    }

    /**
     * Obtener datos de gamificación como array.
     */
    public function toArray(): array
    {
        return [
            'xp'              => $this->xp,
            'level'           => $this->level,
            'xp_for_next'     => $this->xpForNextLevel(),
            'level_progress'  => $this->levelProgress(),
            'total_reviews'   => $this->total_reviews,
            'total_mastered'  => $this->total_mastered,
            'current_streak'  => $this->current_streak,
            'longest_streak'  => $this->longest_streak,
            'achievements'    => $this->achievements ?? [],
        ];
    }
}
