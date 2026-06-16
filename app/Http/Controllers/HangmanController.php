<?php

namespace App\Http\Controllers;

use App\Models\HangmanGame;
use App\Models\StudyCard;
use App\Models\StudyCardDifficult;
use App\Models\StudyCardSet;
use App\Models\UserGamification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HangmanController extends Controller
{
    /**
     * Página principal del ahorcado (web).
     */
    public function index()
    {
        $userId = Auth::id();
        $difficultCards = collect();

        if ($userId) {
            // Obtener todas las tarjetas difíciles del usuario con sus datos
            $difficultCards = StudyCardDifficult::where('user_id', $userId)
                ->with(['studyCardSet' => fn($q) => $q->with('cards')])
                ->get()
                ->map(function ($diff) {
                    $set = $diff->studyCardSet;
                    $card = $set?->cards?->values()?->get($diff->card_index);
                    return $card ? [
                        'card_id'     => $card->id,
                        'set_id'      => $set->id,
                        'set_title'   => $set->title,
                        'front'       => $card->front,
                        'back'        => $card->back,
                        'card_index'  => $diff->card_index,
                    ] : null;
                })
                ->filter()
                ->values();

            // Juegos recientes
            $recentGames = HangmanGame::where('user_id', $userId)
                ->with('studyCard')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn($g) => [
                    'id'         => $g->id,
                    'phrase'     => mb_substr($g->secret_phrase, 0, 50),
                    'won'        => $g->won,
                    'wrong'      => $g->wrong_guesses,
                    'max'        => $g->max_attempts,
                    'xp_earned'  => $g->xp_earned,
                    'created_at' => $g->created_at->diffForHumans(),
                ]);
        } else {
            $recentGames = collect();
        }

        return view('ahorcado', compact('difficultCards', 'recentGames'));
    }

    /**
     * Iniciar una nueva partida de ahorcado (web AJAX).
     */
    public function startGame(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $request->validate([
            'card_id'     => 'required|integer',
            'set_id'      => 'required|integer',
            'card_index'  => 'required|integer|min:0',
        ]);

        // Verificar que la tarjeta está marcada como difícil
        $difficult = StudyCardDifficult::where([
            'user_id'           => $userId,
            'study_card_set_id' => $request->set_id,
            'card_index'        => $request->card_index,
        ])->first();

        if (!$difficult) {
            return response()->json([
                'success' => false,
                'message' => 'Esta tarjeta no está marcada como difícil',
            ], 422);
        }

        // Obtener la tarjeta
        $set = StudyCardSet::where('id', $request->set_id)->with('cards')->firstOrFail();
        $card = $set->cards->values()->get($request->card_index);

        if (!$card) {
            return response()->json(['success' => false, 'message' => 'Tarjeta no encontrada'], 404);
        }

        // Verificar si ya hay un juego activo para esta tarjeta
        $existingGame = HangmanGame::where('user_id', $userId)
            ->where('study_card_id', $card->id)
            ->where('completed', false)
            ->first();

        if ($existingGame) {
            return response()->json([
                'success'       => true,
                'game_id'       => $existingGame->id,
                'masked_phrase' => $existingGame->masked_phrase,
                'max_attempts'  => $existingGame->max_attempts,
                'wrong_guesses' => $existingGame->wrong_guesses,
                'front'         => $card->front,
                'set_title'     => $set->title,
            ]);
        }

        // Usar la respuesta (back) como frase secreta
        $secretPhrase = trim($card->back);

        $game = HangmanGame::create([
            'user_id'        => $userId,
            'study_card_id'  => $card->id,
            'max_attempts'   => 5,
            'wrong_guesses'  => 0,
            'secret_phrase'  => $secretPhrase,
            'guessed_letters'=> [],
            'won'            => false,
            'completed'      => false,
            'xp_earned'      => 0,
        ]);

        return response()->json([
            'success'       => true,
            'game_id'       => $game->id,
            'masked_phrase' => $game->masked_phrase,
            'max_attempts'  => $game->max_attempts,
            'wrong_guesses' => 0,
            'front'         => $card->front,
            'set_title'     => $set->title,
        ]);
    }

    /**
     * Adivinar una letra (web AJAX).
     */
    public function guess(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $request->validate([
            'game_id' => 'required|integer',
            'letter'  => 'required|string|max:1',
        ]);

        $game = HangmanGame::where('id', $request->game_id)
            ->where('user_id', $userId)
            ->where('completed', false)
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Juego no encontrado o ya finalizado',
            ], 404);
        }

        $result = $game->guessLetter($request->letter);

        // Si el juego terminó, ganar XP
        $xpEarned = 0;
        $leveledUp = false;
        $newAchievements = [];

        if ($result['game_over'] && $result['won']) {
            $xpEarned = $game->calculateXp();
            $game->xp_earned = $xpEarned;
            $game->save();

            // Actualizar gamificación
            $gamification = UserGamification::firstOrCreate(
                ['user_id' => $userId],
                ['xp' => 0, 'level' => 1]
            );
            $gamification->total_reviews += 1;
            $leveledUp = $gamification->addXp($xpEarned);
            $gamification->updateStreak();

            // Contar victorias de ahorcado
            $hangmanWins = HangmanGame::where('user_id', $userId)
                ->where('won', true)
                ->count();
            $gamification->hangman_wins = $hangmanWins;
            $gamification->save();

            // Verificar logros
            $newAchievements = $gamification->checkAchievements();
        }

        $response = [
            'success'          => true,
            'correct'          => $result['correct'],
            'game_over'        => $result['game_over'],
            'won'              => $result['won'],
            'already_guessed'  => $result['already_guessed'] ?? false,
            'masked_phrase'    => $game->masked_phrase,
            'wrong_guesses'    => $game->wrong_guesses,
            'max_attempts'     => $game->max_attempts,
            'guessed_letters'  => $game->guessed_letters ?? [],
            'xp_earned'        => $xpEarned,
            'leveled_up'       => $leveledUp,
            'new_achievements' => $newAchievements,
        ];

        // Revelar la respuesta correcta cuando el juego termina
        if ($result['game_over']) {
            $response['secret_phrase'] = $game->secret_phrase;
        }

        return response()->json($response);
    }

    // ══════════════════════════════════════════════════════════════
    // API ENDPOINTS (para Android)
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtener tarjetas difíciles del usuario (API - Android).
     */
    public function apiGetDifficultCards(Request $request)
    {
        $request->validate(['user_id' => 'required|integer']);

        $difficultCards = StudyCardDifficult::where('user_id', $request->user_id)
            ->with(['studyCardSet' => fn($q) => $q->with('cards')])
            ->get()
            ->map(function ($diff) {
                $set = $diff->studyCardSet;
                $card = $set?->cards?->values()?->get($diff->card_index);
                return $card ? [
                    'card_id'     => $card->id,
                    'set_id'      => $set->id,
                    'set_title'   => $set->title,
                    'front'       => $card->front,
                    'back'        => $card->back,
                    'card_index'  => $diff->card_index,
                ] : null;
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'cards'   => $difficultCards,
        ]);
    }

    /**
     * Iniciar partida (API - Android).
     */
    public function apiStartGame(Request $request)
    {
        $request->validate([
            'user_id'    => 'required|integer',
            'card_id'    => 'required|integer',
            'set_id'     => 'required|integer',
            'card_index' => 'required|integer|min:0',
        ]);

        $difficult = StudyCardDifficult::where([
            'user_id'           => $request->user_id,
            'study_card_set_id' => $request->set_id,
            'card_index'        => $request->card_index,
        ])->first();

        if (!$difficult) {
            return response()->json([
                'success' => false,
                'message' => 'Esta tarjeta no está marcada como difícil',
            ], 422);
        }

        $set = StudyCardSet::where('id', $request->set_id)->with('cards')->firstOrFail();
        $card = $set->cards->values()->get($request->card_index);

        if (!$card) {
            return response()->json(['success' => false, 'message' => 'Tarjeta no encontrada'], 404);
        }

        // Verificar si ya hay un juego activo para esta tarjeta
        $existingGame = HangmanGame::where('user_id', $request->user_id)
            ->where('study_card_id', $card->id)
            ->where('completed', false)
            ->first();

        if ($existingGame) {
            return response()->json([
                'success'       => true,
                'game_id'       => $existingGame->id,
                'masked_phrase' => $existingGame->masked_phrase,
                'max_attempts'  => $existingGame->max_attempts,
                'wrong_guesses' => $existingGame->wrong_guesses,
                'front'         => $card->front,
                'set_title'     => $set->title,
            ]);
        }

        $secretPhrase = trim($card->back);

        $game = HangmanGame::create([
            'user_id'        => $request->user_id,
            'study_card_id'  => $card->id,
            'max_attempts'   => 5,
            'wrong_guesses'  => 0,
            'secret_phrase'  => $secretPhrase,
            'guessed_letters'=> [],
            'won'            => false,
            'completed'      => false,
            'xp_earned'      => 0,
        ]);

        return response()->json([
            'success'       => true,
            'game_id'       => $game->id,
            'masked_phrase' => $game->masked_phrase,
            'max_attempts'  => $game->max_attempts,
            'wrong_guesses' => 0,
            'front'         => $card->front,
            'set_title'     => $set->title,
        ]);
    }

    /**
     * Adivinar letra (API - Android).
     */
    public function apiGuess(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'game_id' => 'required|integer',
            'letter'  => 'required|string|max:1',
        ]);

        $game = HangmanGame::where('id', $request->game_id)
            ->where('user_id', $request->user_id)
            ->where('completed', false)
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Juego no encontrado o ya finalizado',
            ], 404);
        }

        $result = $game->guessLetter($request->letter);

        $xpEarned = 0;
        $leveledUp = false;
        $newAchievements = [];

        if ($result['game_over'] && $result['won']) {
            $xpEarned = $game->calculateXp();
            $game->xp_earned = $xpEarned;
            $game->save();

            $gamification = UserGamification::firstOrCreate(
                ['user_id' => $request->user_id],
                ['xp' => 0, 'level' => 1]
            );
            $gamification->total_reviews += 1;
            $leveledUp = $gamification->addXp($xpEarned);
            $gamification->updateStreak();

            $hangmanWins = HangmanGame::where('user_id', $request->user_id)
                ->where('won', true)
                ->count();
            $gamification->hangman_wins = $hangmanWins;
            $gamification->save();

            $newAchievements = $gamification->checkAchievements();
        }

        $response = [
            'success'          => true,
            'correct'          => $result['correct'],
            'game_over'        => $result['game_over'],
            'won'              => $result['won'],
            'already_guessed'  => $result['already_guessed'] ?? false,
            'masked_phrase'    => $game->masked_phrase,
            'wrong_guesses'    => $game->wrong_guesses,
            'max_attempts'     => $game->max_attempts,
            'guessed_letters'  => $game->guessed_letters ?? [],
            'xp_earned'        => $xpEarned,
            'leveled_up'       => $leveledUp,
            'new_achievements' => $newAchievements,
        ];

        if ($result['game_over']) {
            $response['secret_phrase'] = $game->secret_phrase;
        }

        return response()->json($response);
    }

    /**
     * Historial de partidas (API - Android).
     */
    public function apiHistory(Request $request)
    {
        $request->validate(['user_id' => 'required|integer']);

        $games = HangmanGame::where('user_id', $request->user_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($g) => [
                'id'         => $g->id,
                'phrase'     => mb_substr($g->secret_phrase, 0, 50),
                'won'        => $g->won,
                'wrong'      => $g->wrong_guesses,
                'max'        => $g->max_attempts,
                'xp_earned'  => $g->xp_earned,
                'created_at' => $g->created_at->toISOString(),
            ]);

        return response()->json(['success' => true, 'games' => $games]);
    }
}
