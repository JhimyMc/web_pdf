<?php

namespace App\Http\Controllers;

use App\Models\StudyCardSet;
use App\Models\StudyCard;
use App\Models\StudyCardReview;
use App\Models\StudyCardDifficult;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class StudyCardController extends Controller
{
    /**
     * Mostrar la vista principal con los sets de tarjetas del usuario.
     */
    public function index()
    {
        $sets = collect();
        $documentos = collect();
        $setsData = collect();

        if (Auth::check()) {
            $sets = StudyCardSet::where('user_id', Auth::id())
                ->where('status', 'activo')
                ->with('cards')
                ->latest()
                ->get();

            $documentos = Document::where('user_id', Auth::id())->latest()->get();

            // Cargar conteo de tarjetas difíciles por set
            $difficultCounts = StudyCardDifficult::whereIn('study_card_set_id', $sets->pluck('id'))
                ->selectRaw('study_card_set_id, COUNT(*) as count')
                ->groupBy('study_card_set_id')
                ->pluck('count', 'study_card_set_id');

            $setsData = $sets->map(function ($set) use ($difficultCounts) {
                return [
                    'id'              => $set->id,
                    'title'           => $set->title,
                    'status'          => $set->status,
                    'cards_count'     => $set->cards->count(),
                    'difficult_count' => (int) ($difficultCounts[$set->id] ?? 0),
                    'created_at'      => $set->created_at->toJSON(),
                ];
            })->values();
        }

        return view('tarjetas-estudio', compact('sets', 'documentos', 'setsData'));
    }

    /**
     * Mostrar las tarjetas de un set específico.
     * Incluye los índices de tarjetas ya repasadas.
     */
    public function show($id)
    {
        $set = StudyCardSet::where('id', $id)
            ->where('user_id', Auth::id())
            ->with('cards')
            ->firstOrFail();

        // Cargar qué tarjetas ya fueron repasadas
        $reviewedIndices = StudyCardReview::where('study_card_set_id', $set->id)
            ->where('user_id', Auth::id())
            ->pluck('card_index')
            ->toArray();

        // Cargar qué tarjetas están marcadas como difíciles
        $difficultIndices = StudyCardDifficult::where('study_card_set_id', $set->id)
            ->where('user_id', Auth::id())
            ->pluck('card_index')
            ->toArray();

        return response()->json([
            'success' => true,
            'set' => [
                'id'    => $set->id,
                'title' => $set->title,
                'cards' => $set->cards->map(function ($card) {
                    return [
                        'id'    => $card->id,
                        'front' => $card->front,
                        'back'  => $card->back,
                    ];
                }),
                'reviewed_indices' => $reviewedIndices,
                'difficult_indices' => $difficultIndices,
            ],
        ]);
    }

    /**
     * Marcar una tarjeta como repasada.
     */
    public function reviewed(Request $request, $id)
    {
        $request->validate([
            'card_index' => 'required|integer|min:0',
        ]);

        $set = StudyCardSet::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Evitar duplicados con firstOrCreate
        StudyCardReview::firstOrCreate([
            'user_id'          => Auth::id(),
            'study_card_set_id' => $set->id,
            'card_index'       => $request->card_index,
        ], [
            'reviewed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Marcar una tarjeta como difícil.
     */
    public function markDifficult(Request $request, $id)
    {
        $request->validate([
            'card_index' => 'required|integer|min:0',
        ]);

        $set = StudyCardSet::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        StudyCardDifficult::firstOrCreate([
            'user_id'          => Auth::id(),
            'study_card_set_id' => $set->id,
            'card_index'       => $request->card_index,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Desmarcar una tarjeta como difícil.
     */
    public function unmarkDifficult(Request $request, $id)
    {
        $request->validate([
            'card_index' => 'required|integer|min:0',
        ]);

        $set = StudyCardSet::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        StudyCardDifficult::where([
            'user_id'          => Auth::id(),
            'study_card_set_id' => $set->id,
            'card_index'       => $request->card_index,
        ])->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Generar tarjetas de estudio desde un PDF usando n8n → LM Studio.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id',
        ]);

        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        }

        try {
            $documento = Document::where('id', $request->document_id)
                ->where('user_id', Auth::id())
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
                    'message' => 'No pudimos leer el texto de este PDF. Asegúrate de que tu documento haya sido cargado correctamente.'
                ], 422);
            }

            // 🔄 INTENTO 1: Contexto completo seleccionado
            $resultadoIA = $this->llamarN8nTarjetas($contextoTexto);

            // 🔄 INTENTO 2: Si falla, intentar con menos contexto
            if ($resultadoIA === null && $totalChunks > 6) {
                $contextoReducido = mb_substr($contextoTexto, 0, 2000);
                $resultadoIA = $this->llamarN8nTarjetas($contextoReducido);
            }

            if ($resultadoIA === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'La IA no pudo procesar el documento. Intenta con un PDF más corto.'
                ], 500);
            }

            $cardsData = $resultadoIA;

            // Validar que sea un array de tarjetas
            if (!is_array($cardsData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El formato devuelto por la IA es inválido.'
                ], 422);
            }

            // Si viene dentro de una clave 'cards' o 'tarjetas', extraerla
            $rawCards = $cardsData;
            if (isset($cardsData['cards'])) {
                $rawCards = $cardsData['cards'];
            } elseif (isset($cardsData['tarjetas'])) {
                $rawCards = $cardsData['tarjetas'];
            }

            if (!is_array($rawCards) || count($rawCards) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron generar tarjetas. La IA no devolvió datos válidos.'
                ], 422);
            }

            // Crear el set de tarjetas
            $set = StudyCardSet::create([
                'user_id'     => Auth::id(),
                'title'       => 'Tarjetas: ' . $documento->name,
                'document_id' => $documento->id,
                'status'      => 'activo',
            ]);

            // Crear cada tarjeta
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
                $set->delete(); // Limpiar si no se crearon tarjetas
                return response()->json([
                    'success' => false,
                    'message' => 'La IA devolvió datos vacíos. Intenta con otro documento.'
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
     * Llamar a n8n para generar tarjetas de estudio.
     * Retorna el array de datos decodificado o null si falla.
     */
    private function llamarN8nTarjetas(string $contexto): ?array
    {
        $maxChars = mb_strlen($contexto);
        $intentos = 0;
        $maxIntentos = 2;

        while ($intentos < $maxIntentos) {
            $intentos++;
            $textoActual = mb_substr($contexto, 0, $maxChars);

            try {
                $response = Http::timeout(300)->post('http://localhost:5678/webhook/playdf-tarjetas-estudio', [
                    'model'    => 'meta-llama-3-8b-instruct',
                    'context'  => $textoActual,
                    'question' => 'Genera una lista de tarjetas de estudio con preguntas y respuestas.',
                ]);

                if (!$response->successful()) {
                    $maxChars = (int) ($maxChars * 0.5);
                    continue;
                }

                $jsonData = $response->json();

                // Decodificar respuesta de n8n
                $rawAnswer = $jsonData['answer'] ?? null;
                if ($rawAnswer) {
                    $rawAnswer = preg_replace('/```json\s*|```\s*/', '', $rawAnswer);
                    $rawAnswer = preg_replace('/^\s*\n/m', '', $rawAnswer);
                    $cardsData = json_decode(trim($rawAnswer), true);
                } else {
                    $cardsData = $jsonData;
                }

                // Si es string, intentar decodificar de nuevo
                if (is_string($cardsData)) {
                    $cardsData = json_decode($cardsData, true);
                }

                if (is_array($cardsData)) {
                    return $cardsData;
                }

                // JSON inválido: reducir contexto y reintentar
                $maxChars = (int) ($maxChars * 0.5);
            } catch (\Exception $e) {
                $maxChars = (int) ($maxChars * 0.5);
            }
        }

        return null;
    }

    /**
     * Eliminar (archivar) un set de tarjetas.
     */
    public function destroy($id)
    {
        $set = StudyCardSet::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $set->update(['status' => 'archivado']);

        return response()->json(['success' => true]);
    }
}
