<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HangmanGame extends Model
{
    protected $fillable = [
        'user_id', 'study_card_id', 'max_attempts', 'wrong_guesses',
        'secret_phrase', 'guessed_letters', 'won', 'completed', 'xp_earned',
    ];

    protected $casts = [
        'max_attempts'    => 'integer',
        'wrong_guesses'   => 'integer',
        'guessed_letters' => 'array',
        'won'             => 'boolean',
        'completed'       => 'boolean',
        'xp_earned'       => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyCard(): BelongsTo
    {
        return $this->belongsTo(StudyCard::class);
    }

    /**
     * Obtener las letras de la frase secretas (sin adivinar).
     */
    public function getMaskedPhraseAttribute(): string
    {
        $guessed = array_map('strtolower', $this->guessed_letters ?? []);
        $result = '';
        foreach (mb_str_split(mb_strtolower($this->secret_phrase)) as $char) {
            if (ctype_alpha($char)) {
                $result .= in_array($char, $guessed) ? mb_strtoupper($char) : '_';
            } else {
                $result .= $char; // Espacios, números, puntuación se muestran
            }
            $result .= ' ';
        }
        return trim($result);
    }

    /**
     * Verificar si una letra ya fue adivinada.
     */
    public function isLetterGuessed(string $letter): bool
    {
        return in_array(mb_strtolower($letter), array_map('mb_strtolower', $this->guessed_letters ?? []));
    }

    /**
     * Intentar adivinar una letra.
     * Retorna ['correct' => bool, 'game_over' => bool, 'won' => bool]
     */
    public function guessLetter(string $letter): array
    {
        $letter = mb_strtolower($letter);

        // Ya adivinada
        if ($this->isLetterGuessed($letter)) {
            return ['correct' => false, 'game_over' => false, 'won' => false, 'already_guessed' => true];
        }

        $guessed = $this->guessed_letters ?? [];
        $guessed[] = $letter;
        $this->guessed_letters = $guessed;

        $phraseChars = array_map('mb_strtolower', mb_str_split($this->secret_phrase));
        $correct = in_array($letter, $phraseChars);

        if (!$correct) {
            $this->wrong_guesses += 1;
        }

        // Verificar si ganó (todas las letras alpha adivinadas)
        $alphaChars = array_unique(array_filter($phraseChars, fn($c) => ctype_alpha($c)));
        $allGuessed = true;
        foreach ($alphaChars as $char) {
            if (!in_array($char, array_map('mb_strtolower', $guessed))) {
                $allGuessed = false;
                break;
            }
        }

        if ($allGuessed) {
            $this->won = true;
            $this->completed = true;
        } elseif ($this->wrong_guesses >= $this->max_attempts) {
            $this->completed = true;
            $this->won = false;
        }

        $this->save();

        return [
            'correct'        => $correct,
            'game_over'      => $this->completed,
            'won'            => $this->won,
            'already_guessed'=> false,
        ];
    }

    /**
     * Calcular XP ganado al completar el juego.
     */
    public function calculateXp(): int
    {
        if (!$this->completed) return 0;

        if ($this->won) {
            // Más puntos por menos intentos fallidos
            $base = 50;
            $bonus = ($this->max_attempts - $this->wrong_guesses) * 10;
            return $base + $bonus;
        }
        return 0;
    }
}
