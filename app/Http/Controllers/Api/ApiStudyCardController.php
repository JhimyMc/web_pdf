<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudyCardSet;
use App\Models\StudyCard;
use App\Models\StudyCardReview;
use App\Models\StudyCardDifficult;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Http;

class ApiStudyCardController extends Controller
{
    /**
     * Obtener todos los sets de tarjetas del usuario (Android)
     */
    public function apiObtenerMisSets(Request $request)
    {
        $request->validate(['user_id' => 'required|integer']);

        try {
            $sets = StudyCardSet::where('user_id', $request->user_id)
                ->where('status', 'activo')
                ->withCount('cards')
                ->latest()
                ->get()
                ->map(function ($set) {
                    // Contar tarjetas difíciles
                    $difficultCount = StudyCardDifficult::where('study_card_set_id', $set->id)
                        ->where('user_id', $set->user_id)
                        ->count();

                    return [
                        'id'              => $set->id,
                        'title'           => $set->title,
                        'status'          => $set->status,
                        'cards_count'     => $set->cards_count,
                        'difficult_count' => $difficultCount,
                        'created_at'      => $set->created_at ? $set->created_at->toISOString() : null,
                    ];
                });

            return response()->json(['success' => true, 'sets' => $sets]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener las tarjetas de un set específico (Android)
     */
    public function apiMostrar($id)
    {
        $set = StudyCardSet::with('cards')->find($id);

        if (!$set) {
            return response()->json(['success' => false, 'message' => 'Set no encontrado.'], 404);
        }

        $reviewedIndices = StudyCardReview::where('study_card_set_id', $set->id)
            ->where('user_id', $set->user_id)
            ->pluck('card_index')
            ->toArray();

        $difficultIndices = StudyCardDifficult::where('study_card_set_id', $set->id)
            ->where('user_id', $set->user_id)
            ->pluck('card_index')
            ->toArray();

        return response()->json([
            'success' => true,
            'set' => [
                'id'               => $set->id,
                'title'            => $set->title,
                'cards'            => $set->cards->map(function ($card) {
                    return [
                        'id'    => $card->id,
                        'front' => $card->front,
                        'back'  => $card->back,
                    ];
                }),
                'reviewed_indices'  => $reviewedIndices,
                'difficult_indices' => $difficultIndices,
            ],
        ]);
    }

    /**
     * Generar tarjetas desde un PDF (Android)
     */
    public function apiGenerar(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id',
            'user_id'     => 'required|integer',
        ]);

        try {
            $documento = Document::where('id', $request->document_id)
                ->where('user_id', $request->user_id)
                ->firstOrFail();

            $contextoTexto = DocumentChunk::where('document_id', $documento->id)
                ->pluck('chunk_text')
                ->implode(' ');

            if (empty(trim($contextoTexto))) {
                return response()->json([
                    'success' => false,
                    'message' => 'El PDF no tiene texto procesable.'
                ], 422);
            }

            $response = Http::timeout(300)->post('http://localhost:5678/webhook/playdf-tarjetas-estudio', [
                'model'    => 'meta-llama-3-8b-instruct',
                'context'  => mb_substr($contextoTexto, 0, 15000),
                'question' => 'Genera una lista de tarjetas de estudio con preguntas y respuestas.',
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La IA local no respondió.'
                ], 500);
            }

            $jsonData = $response->json();

            if (isset($jsonData['answer'])) {
                $rawAnswer = preg_replace('/```json\s*|```\s*/', '', $jsonData['answer']);
                $cardsData = json_decode(trim($rawAnswer), true);
            } else {
                $cardsData = $jsonData;
            }

            if (!is_array($cardsData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato inválido de la IA.'
                ], 422);
            }

            $rawCards = $cardsData;
            if (isset($cardsData['cards'])) {
                $rawCards = $cardsData['cards'];
            } elseif (isset($cardsData['tarjetas'])) {
                $rawCards = $cardsData['tarjetas'];
            }

            if (!is_array($rawCards) || count($rawCards) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se generaron tarjetas.'
                ], 422);
            }

            $set = StudyCardSet::create([
                'user_id'     => $request->user_id,
                'title'       => 'Tarjetas: ' . $documento->name,
                'document_id' => $documento->id,
                'status'      => 'activo',
            ]);

            $cardsCreated = [];
            foreach ($rawCards as $item) {
                $front = $item['front'] ?? $item['pregunta'] ?? $item['question'] ?? $item['concepto'] ?? '';
                $back  = $item['back']  ?? $item['respuesta'] ?? $item['answer'] ?? $item['definicion'] ?? '';

                if (empty($front) || empty($back)) continue;

                $card = StudyCard::create([
                    'study_card_set_id' => $set->id,
                    'front' => $front,
                    'back'  => $back,
                ]);

                $cardsCreated[] = [
                    'id'    => $card->id,
                    'front' => $card->front,
                    'back'  => $card->back,
                ];
            }

            if (count($cardsCreated) === 0) {
                $set->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'La IA devolvió datos vacíos.'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'set' => [
                    'id'    => $set->id,
                    'title' => $set->title,
                    'cards' => $cardsCreated,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar (archivar) un set (Android)
     */
    public function apiEliminar($id)
    {
        $set = StudyCardSet::find($id);
        if ($set) {
            $set->update(['status' => 'archivado']);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Marcar tarjeta como repasada (Android)
     */
    public function apiMarcarRepasada(Request $request, $id)
    {
        $request->validate(['card_index' => 'required|integer|min:0']);

        $set = StudyCardSet::find($id);
        if (!$set) {
            return response()->json(['success' => false, 'message' => 'Set no encontrado.'], 404);
        }

        StudyCardReview::firstOrCreate([
            'user_id'           => $set->user_id,
            'study_card_set_id' => $set->id,
            'card_index'        => $request->card_index,
        ], [
            'reviewed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Marcar tarjeta como difícil (Android)
     */
    public function apiMarcarDificil(Request $request, $id)
    {
        $request->validate(['card_index' => 'required|integer|min:0']);

        $set = StudyCardSet::find($id);
        if (!$set) {
            return response()->json(['success' => false, 'message' => 'Set no encontrado.'], 404);
        }

        StudyCardDifficult::firstOrCreate([
            'user_id'           => $set->user_id,
            'study_card_set_id' => $set->id,
            'card_index'        => $request->card_index,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Desmarcar tarjeta como difícil (Android)
     */
    public function apiDesmarcarDificil(Request $request, $id)
    {
        $request->validate(['card_index' => 'required|integer|min:0']);

        $set = StudyCardSet::find($id);
        if (!$set) {
            return response()->json(['success' => false, 'message' => 'Set no encontrado.'], 404);
        }

        StudyCardDifficult::where([
            'user_id'           => $set->user_id,
            'study_card_set_id' => $set->id,
            'card_index'        => $request->card_index,
        ])->delete();

        return response()->json(['success' => true]);
    }
}
