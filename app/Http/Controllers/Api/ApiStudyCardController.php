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
use Illuminate\Support\Facades\Log;
use App\Traits\ConnectsToLMStudio;

class ApiStudyCardController extends Controller
{
    use ConnectsToLMStudio;
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

            // 🚀 SELECCIÓN INTELIGENTE DE CHUNKS (igual que DocumentController)
            $totalChunks = DocumentChunk::where('document_id', $documento->id)->count();

            if ($totalChunks <= 6) {
                // PDF corto: enviar todos los chunks
                $chunksRelevantes = DocumentChunk::where('document_id', $documento->id)
                    ->orderBy('id', 'asc')
                    ->get();
            } else {
                // PDF largo: tomar muestra estratégica (inicio + medio + fin)
                $chunksInicio = DocumentChunk::where('document_id', $documento->id)
                    ->orderBy('id', 'asc')->limit(3)->get();

                $mitad = (int) floor($totalChunks / 2);
                $chunksMedio = DocumentChunk::where('document_id', $documento->id)
                    ->orderBy('id', 'asc')->skip($mitad - 1)->take(2)->get();

                $chunksFin = DocumentChunk::where('document_id', $documento->id)
                    ->orderBy('id', 'desc')->limit(2)->get();

                $chunksRelevantes = $chunksInicio->merge($chunksMedio)->merge($chunksFin);
            }

            // Construir contexto limitado (~4000 chars para que el LLM no se sature)
            $contextoTexto = '';
            foreach ($chunksRelevantes as $chunk) {
                $fragmento = mb_substr($chunk->chunk_text, 0, 700);
                $contextoTexto .= $fragmento . "\n\n";
            }
            $contextoTexto = mb_substr($contextoTexto, 0, 4000);

            // Fallback: si no hay chunks, usar extracted_text directamente del documento
            if (empty(trim($contextoTexto))) {
                $contextoTexto = $documento->extracted_text ?? '';
                $contextoTexto = mb_substr($contextoTexto, 0, 4000);
            }

            if (empty(trim($contextoTexto))) {
                return response()->json([
                    'success' => false,
                    'message' => 'El PDF no tiene texto procesable.'
                ], 422);
            }

            // 🔄 INTENTO 1: Contexto completo seleccionado
            $resultadoIA = $this->llamarLMStudioTarjetas($contextoTexto);

            // 🔄 INTENTO 2: Si falla, intentar con menos contexto
            if ($resultadoIA === null && $totalChunks > 6) {
                $contextoReducido = mb_substr($contextoTexto, 0, 2000);
                $resultadoIA = $this->llamarLMStudioTarjetas($contextoReducido);
            }

            if ($resultadoIA === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'La IA no pudo procesar el documento. Intenta con un PDF más corto.'
                ], 500);
            }

            $cardsData = $resultadoIA;

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
     * Llamar a LM Studio para generar tarjetas de estudio.
     * Retorna el array de datos decodificado o null si falla.
     */
    private function llamarLMStudioTarjetas(string $contexto): ?array
    {
        $maxChars = mb_strlen($contexto);
        $intentos = 0;
        $maxIntentos = 2;

        while ($intentos < $maxIntentos) {
            $intentos++;
            $textoActual = mb_substr($contexto, 0, $maxChars);

            $systemPrompt = "Eres un experto creando tarjetas de estudio (flashcards). Genera tarjetas con PREGUNTA (front) y RESPUESTA (back) basándote EXCLUSIVAMENTE en el texto proporcionado.\n\n"
                . "REGLAS:\n"
                . "1. Devuelve SOLO un array JSON válido. NADA de texto antes o después.\n"
                . "2. Cada tarjeta tiene 'front' (pregunta) y 'back' (respuesta).\n"
                . "3. Genera entre 8 y 15 tarjetas variadas.\n"
                . "4. Preguntas sobre CONCEPTOS del texto, NO sobre el archivo o documento.\n"
                . "5. Respuestas claras y concisas (1-3 oraciones).\n\n"
                . "FORMATO: [{\"front\": \"¿Pregunta?\", \"back\": \"Respuesta\"}]";

            $userMessage = "Texto del documento:\n\n{$textoActual}";

            $cardsData = $this->llamarLMStudioJSON($systemPrompt, $userMessage, 0.6, 2048);

            if (is_array($cardsData)) {
                return $cardsData;
            }

            // JSON inválido: reducir contexto y reintentar
            $maxChars = (int) ($maxChars * 0.5);
        }

        return null;
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
