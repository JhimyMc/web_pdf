<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SrsCard;
use App\Models\StudyCardSet;
use App\Models\StudyCard;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ApiSrsController extends Controller
{
    /**
     * Obtener la cola de revisión de hoy (Android).
     */
    public function apiGetReviewQueue(Request $request, $id)
    {
        $request->validate(['user_id' => 'required|integer']);

        $set = StudyCardSet::where('id', $id)
            ->where('user_id', $request->user_id)
            ->with('cards')
            ->firstOrFail();

        $dueCards = SrsCard::where('user_id', $request->user_id)
            ->where('study_card_set_id', $set->id)
            ->where('next_review_at', '<=', Carbon::now())
            ->orderBy('next_review_at', 'asc')
            ->get();

        $cards = $dueCards->map(function ($srsCard) {
            $card = $srsCard->studyCard;
            return [
                'srs_id'       => $srsCard->id,
                'card_id'      => $card->id,
                'card_index'   => $srsCard->card_index,
                'front'        => $card->front,
                'back'         => $card->back,
                'ease_factor'  => $srsCard->ease_factor,
                'interval_days'=> $srsCard->interval_days,
                'repetitions'  => $srsCard->repetitions,
                'next_review'  => $srsCard->next_review_at?->toISOString(),
                'last_reviewed'=> $srsCard->last_reviewed_at?->toISOString(),
            ];
        });

        $totalInSet = SrsCard::where('user_id', $request->user_id)
            ->where('study_card_set_id', $set->id)
            ->count();

        $masteredCount = SrsCard::where('user_id', $request->user_id)
            ->where('study_card_set_id', $set->id)
            ->where('interval_days', '>=', 21)
            ->count();

        return response()->json([
            'success' => true,
            'set' => [
                'id'    => $set->id,
                'title' => $set->title,
            ],
            'due_cards'    => $cards->values(),
            'total_due'    => $cards->count(),
            'total_cards'  => $totalInSet,
            'mastered'     => $masteredCount,
        ]);
    }

    /**
     * Sincronizar datos SRS para un set (Android).
     */
    public function apiSync(Request $request)
    {
        $request->validate([
            'set_id'  => 'required|exists:study_card_sets,id',
            'user_id' => 'required|integer',
        ]);

        $set = StudyCardSet::where('id', $request->set_id)
            ->where('user_id', $request->user_id)
            ->with('cards')
            ->firstOrFail();

        $created = 0;
        foreach ($set->cards as $index => $card) {
            $existing = SrsCard::where('user_id', $request->user_id)
                ->where('study_card_set_id', $set->id)
                ->where('card_index', $index)
                ->first();

            if (!$existing) {
                SrsCard::create([
                    'user_id'           => $request->user_id,
                    'study_card_set_id' => $set->id,
                    'study_card_id'     => $card->id,
                    'card_index'        => $index,
                    'ease_factor'       => 2.5,
                    'interval_days'     => 1,
                    'repetitions'       => 0,
                    'next_review_at'    => Carbon::now(),
                    'last_reviewed_at'  => null,
                ]);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "SRS inicializado: $created tarjetas registradas.",
            'created' => $created,
        ]);
    }

    /**
     * Registrar revisión SM-2 (Android).
     */
    public function apiReview(Request $request, $id)
    {
        $request->validate([
            'card_index' => 'required|integer|min:0',
            'quality'    => 'required|integer|in:0,1,2,3',
            'user_id'    => 'required|integer',
        ]);

        $srsCard = SrsCard::where('user_id', $request->user_id)
            ->where('study_card_set_id', $id)
            ->where('card_index', $request->card_index)
            ->firstOrFail();

        $qualityMap = [0 => 1, 1 => 3, 2 => 4, 3 => 5];
        $q = $qualityMap[$request->quality];

        $easeFactor = $srsCard->ease_factor;
        $interval   = $srsCard->interval_days;
        $reps       = $srsCard->repetitions;

        if ($q < 3) {
            $reps = 0;
            $interval = 1;
        } else {
            $reps += 1;
            if ($reps === 1) {
                $interval = 1;
            } elseif ($reps === 2) {
                $interval = 6;
            } else {
                $interval = (int) round($interval * $easeFactor);
            }
        }

        $easeFactor = $easeFactor + (0.1 - (5 - $q) * (0.08 + (5 - $q) * 0.02));
        if ($easeFactor < 1.3) $easeFactor = 1.3;
        if ($easeFactor > 3.0) $easeFactor = 3.0;

        $srsCard->update([
            'ease_factor'      => round($easeFactor, 4),
            'interval_days'    => $interval,
            'repetitions'      => $reps,
            'next_review_at'   => Carbon::now()->addDays($interval),
            'last_reviewed_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'next_review'   => $srsCard->next_review_at->toISOString(),
            'interval_days' => $interval,
            'ease_factor'   => round($easeFactor, 2),
            'repetitions'   => $reps,
        ]);
    }

    /**
     * Obtener estadísticas SRS (Android).
     */
    public function apiStats(Request $request)
    {
        $request->validate(['user_id' => 'required|integer']);
        $userId = $request->user_id;

        $totalCards = SrsCard::where('user_id', $userId)->count();
        $dueNow    = SrsCard::where('user_id', $userId)
            ->where('next_review_at', '<=', Carbon::now())
            ->count();
        $mastered  = SrsCard::where('user_id', $userId)
            ->where('interval_days', '>=', 21)
            ->count();
        $learning  = SrsCard::where('user_id', $userId)
            ->where('interval_days', '<', 21)
            ->where('repetitions', '>', 0)
            ->count();
        $newCards  = SrsCard::where('user_id', $userId)
            ->where('repetitions', 0)
            ->count();

        return response()->json([
            'success'      => true,
            'total_cards'  => $totalCards,
            'due_now'      => $dueNow,
            'mastered'     => $mastered,
            'learning'     => $learning,
            'new_cards'    => $newCards,
        ]);
    }
}
