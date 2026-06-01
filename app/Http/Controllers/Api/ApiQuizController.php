<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\QuizController;
use App\Jobs\GenerateQuestionsBatch;
use App\Models\Room;
use App\Models\Document;
use App\Models\StudentResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiQuizController extends QuizController
{
    /**
     * Alias de apiGetStatus para la app móvil.
     */
    public function apiObtenerEstadoSala($code)
    {
        return $this->apiGetStatus($code);
    }

    /**
     * Alias de apiSaveResponse para la app móvil.
     */
    public function apiGuardarRespuestaApp(Request $request)
    {
        return $this->apiSaveResponse($request);
    }

    /**
     * POST /api/rooms/{code}/join
     * Endpoint que llama Android cuando el alumno pulsa "Ingresar".
     */
    public function apiJoinRoom(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'student_name' => 'required|string|max:60',
        ]);

        $room = Room::where('code', strtoupper($code))->first();

        if (!$room) {
            return response()->json(['error' => 'Sala no encontrada.'], 404);
        }

        if ($room->status === 'configurando') {
            return response()->json(['error' => 'La sala aún se está configurando.'], 422);
        }

        if ($room->status === 'finalizado') {
            return response()->json(['error' => 'La sala ya finalizó.'], 422);
        }

        StudentResponse::firstOrCreate(
            [
                'room_code'      => $room->code,
                'student_name'   => $request->student_name,
                'question_index' => -1,
            ],
            [
                'selected_option' => -1,
                'is_correct'      => false,
                'is_flagged'      => false,
            ]
        );

        return response()->json([
            'success' => true,
            'status'  => $room->status,
        ]);
    }

    /**
     * POST /api/rooms/create-from-document
     * Crea una sala desde un document_id usando RAG.
     *
     * FLUJO ASÍNCRONO:
     * - 1 batch  → n8n (sincrónico, rápido, siempre funciona)
     * - N batches → Laravel Queue (Jobs procesan cada batch en background,
     *               sin límite de tiempo, el worker llama a LM Studio)
     *               Android hace polling a GET /api/rooms/{code}/status-app
     */
    public function apiCrearSalaDesdeDocumento(Request $request)
    {
        try {
            $request->validate([
                'document_id'   => 'required|integer',
                'num_questions' => 'required|integer|min:1|max:20',
                'difficulty'    => 'required|in:basico,intermedio,avanzado'
            ]);

            $documento = Document::find($request->document_id);
            if (!$documento) {
                return response()->json(['success' => false, 'error' => 'Documento no encontrado.'], 404);
            }

            $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));

            // Obtener batches de contexto
            $ragResult = $this->obtenerContextoRAG($request->document_id, $request->num_questions);
            $batches = $ragResult['batches'];
            $totalBatches = count($batches);

            $room = Room::create([
                'user_id'          => $documento->user_id,
                'code'             => $code,
                'pdf_name'         => $documento->name ?? 'Documento_PDF',
                'num_questions'    => $request->num_questions,
                'total_batches'    => $totalBatches,
                'batches_completed'=> 0,
                'difficulty'       => $request->difficulty,
                'status'           => 'generando',
                'questions'        => []
            ]);

            if ($totalBatches === 1) {
                // 🟢 1 SOLO BATCH → n8n síncrono (rápido, siempre funciona)
                $n8nWebhookUrl = 'http://127.0.0.1:5678/webhook/playdf-examen-sala';

                $contextoBatch = $batches[0]['context'];
                if (mb_strlen($contextoBatch) > 7000) {
                    $contextoBatch = mb_substr($contextoBatch, 0, 7000);
                }

                $pregSolicitadas = $batches[0]['questions'] + min(3, $batches[0]['questions']);

                try {
                    $response = Http::timeout(300)->post($n8nWebhookUrl, [
                        'code'          => $code,
                        'pdf_name'      => $room->pdf_name,
                        'num_questions' => $pregSolicitadas,
                        'difficulty'    => $request->difficulty,
                        'context'       => $contextoBatch,
                        'batch_index'   => 0,
                        'total_batches' => 1
                    ]);

                    if ($response->successful()) {
                        $room->update([
                            'total_batches'    => 1,
                            'batches_completed'=> 1,
                        ]);
                        return response()->json(['success' => true, 'code' => $code], 201);
                    }

                    Log::warning("n8n single-batch falló HTTP " . $response->status() . " para sala {$code}");
                    $room->update(['status' => 'configurando']);
                    return response()->json(['success' => false, 'error' => 'Error de conexión con la IA.'], 500);

                } catch (\Exception $e) {
                    Log::error("n8n single-batch error: " . $e->getMessage());
                    $room->update(['status' => 'configurando']);
                    return response()->json(['success' => false, 'error' => 'Error de conexión con n8n.'], 500);
                }
            }

            // 🔵 MÚLTIPLES BATCHES → Jobs asíncronos vía Laravel Queue
            // El endpoint responde al instante; el Queue Worker procesa en background.
            foreach ($batches as $idx => $batch) {
                $contextoBatch = $batch['context'];
                if (mb_strlen($contextoBatch) > 7000) {
                    $contextoBatch = mb_substr($contextoBatch, 0, 7000);
                }

                $pregSolicitadas = $batch['questions'] + min(3, $batch['questions']);

                GenerateQuestionsBatch::dispatch(
                    roomCode:       $code,
                    context:        $contextoBatch,
                    numQuestions:   $pregSolicitadas,
                    difficulty:     $request->difficulty,
                    batchIndex:     $idx,
                    totalBatches:   $totalBatches,
                );
            }

            Log::info("Sala {$code}: {$totalBatches} jobs despachados a la queue");
            return response()->json(['success' => true, 'code' => $code], 201);

        } catch (\Exception $e) {
            Log::error("Error en apiCrearSalaDesdeDocumento: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/rooms/create-from-app
     * Crea una sala desde un PDF subido LOCALMENTE desde el teléfono Android.
     *
     * FLUJO ASÍNCRONO: igual que create-from-document.
     * - 1 batch  → n8n síncrono
     * - N batches → Jobs en background queue
     */
    public function apiCrearSalaDesdeApp(Request $request)
    {
        try {
            $request->validate([
                'pdf_text'      => 'required|string|min:10',
                'pdf_name'      => 'required|string|max:255',
                'num_questions' => 'required|integer|min:1|max:20',
                'difficulty'    => 'required|in:basico,intermedio,avanzado'
            ]);

            $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));

            $userId = Auth::id() ?? $request->input('user_id');
            if (!$userId) {
                return response()->json(['success' => false, 'error' => 'Usuario no autenticado.'], 401);
            }

            // Dividir texto en fragmentos para los batches
            $textoLimpio = preg_replace('/\s+/', ' ', $request->pdf_text);
            $fragmentos = [];
            $palabras = explode(' ', $textoLimpio);
            $chunkActual = [];
            $longitudActual = 0;
            $maxCaracteres = 1500;
            $solapamiento = 40;

            foreach ($palabras as $palabra) {
                $chunkActual[] = $palabra;
                $longitudActual += mb_strlen($palabra) + 1;
                if ($longitudActual >= $maxCaracteres) {
                    $fragmentos[] = implode(' ', $chunkActual);
                    $chunkActual = array_slice($chunkActual, -$solapamiento);
                    $longitudActual = mb_strlen(implode(' ', $chunkActual));
                }
            }
            if (!empty($chunkActual)) {
                $fragmentos[] = implode(' ', $chunkActual);
            }

            $batches = $this->prepararBatchesDesdeFragmentos($fragmentos, $request->num_questions);
            $totalBatches = count($batches);

            $room = Room::create([
                'user_id'          => $userId,
                'code'             => $code,
                'pdf_name'         => $request->pdf_name,
                'num_questions'    => $request->num_questions,
                'total_batches'    => $totalBatches,
                'batches_completed'=> 0,
                'difficulty'       => $request->difficulty,
                'status'           => 'generando',
                'questions'        => []
            ]);

            if ($totalBatches === 1) {
                // 🟢 1 SOLO BATCH → n8n síncrono
                $n8nWebhookUrl = 'http://127.0.0.1:5678/webhook/playdf-examen-sala';
                $contextoBatch = $batches[0]['context'];
                if (mb_strlen($contextoBatch) > 7000) {
                    $contextoBatch = mb_substr($contextoBatch, 0, 7000);
                }
                $pregSolicitadas = $batches[0]['questions'] + min(3, $batches[0]['questions']);

                try {
                    $response = Http::timeout(300)->post($n8nWebhookUrl, [
                        'code'          => $code,
                        'pdf_name'      => $request->pdf_name,
                        'num_questions' => $pregSolicitadas,
                        'difficulty'    => $request->difficulty,
                        'context'       => $contextoBatch,
                        'batch_index'   => 0,
                        'total_batches' => 1
                    ]);

                    if ($response->successful()) {
                        $room->update([
                            'total_batches'    => 1,
                            'batches_completed'=> 1,
                        ]);
                        return response()->json(['success' => true, 'code' => $code], 201);
                    }

                    $room->update(['status' => 'configurando']);
                    return response()->json(['success' => false, 'error' => 'Error de conexión con la IA.'], 500);
                } catch (\Exception $e) {
                    Log::error("App n8n single-batch error: " . $e->getMessage());
                    $room->update(['status' => 'configurando']);
                    return response()->json(['success' => false, 'error' => 'Error de conexión.'], 500);
                }
            }

            // 🔵 MÚLTIPLES BATCHES → Jobs en background
            foreach ($batches as $idx => $batch) {
                $contextoBatch = $batch['context'];
                if (mb_strlen($contextoBatch) > 7000) {
                    $contextoBatch = mb_substr($contextoBatch, 0, 7000);
                }
                $pregSolicitadas = $batch['questions'] + min(3, $batch['questions']);

                GenerateQuestionsBatch::dispatch(
                    roomCode:       $code,
                    context:        $contextoBatch,
                    numQuestions:   $pregSolicitadas,
                    difficulty:     $request->difficulty,
                    batchIndex:     $idx,
                    totalBatches:   $totalBatches,
                );
            }

            Log::info("App sala {$code}: {$totalBatches} jobs despachados");
            return response()->json(['success' => true, 'code' => $code], 201);

        } catch (\Exception $e) {
            Log::error("Error en apiCrearSalaDesdeApp: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/docente/{userId}/pdfs
     * Retorna los PDFs del docente para la app móvil.
     */
    public function apiObtenerPdfsDocente($userId)
    {
        try {
            $documentos = Document::where('user_id', $userId)
                ->latest()
                ->get(['id', 'nombre', 'user_id']);

            return response()->json($documentos, 200);
        } catch (\Exception $e) {
            Log::error("Error en apiObtenerPdfsDocente: " . $e->getMessage());
            return response()->json(['error' => 'No se pudieron obtener los documentos'], 500);
        }
    }

    /**
     * GET /api/rooms/history?user_id=1
     * Historial de salas del docente para la app móvil.
     */
    public function apiHistorialApp(Request $request)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'user_id requerido'], 400);
        }

        $salas = Room::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($room) {
                $totalEstudiantes = StudentResponse::where('room_code', $room->code)
                    ->where('question_index', '>=', 0)
                    ->distinct('student_name')
                    ->count('student_name');

                $totalRespuestas = StudentResponse::where('room_code', $room->code)
                    ->where('question_index', '>=', 0)
                    ->count();

                $totalCorrectas = StudentResponse::where('room_code', $room->code)
                    ->where('question_index', '>=', 0)
                    ->where('is_correct', true)
                    ->count();

                return [
                    'code'              => $room->code,
                    'pdf_name'          => $room->pdf_name,
                    'status'            => $room->status,
                    'num_questions'     => $room->num_questions,
                    'difficulty'        => $room->difficulty,
                    'created_at'        => $room->created_at->format('d/m/Y H:i'),
                    'finished_at'       => $room->finished_at ? $room->finished_at->format('d/m/Y H:i') : null,
                    'total_estudiantes' => $totalEstudiantes,
                    'promedio'          => $totalRespuestas > 0
                        ? round(($totalCorrectas / $totalRespuestas) * 100, 1)
                        : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'salas'   => $salas,
        ]);
    }

    /**
     * POST /api/rooms/join (legacy)
     * @deprecated Usar POST /api/rooms/{code}/join
     */
    public function apiUnirseSalaApp(Request $request)
    {
        $request->validate([
            'room_code'    => 'required|string',
            'student_name' => 'required|string'
        ]);

        return $this->apiJoinRoom($request, $request->room_code);
    }

    /**
     * POST /api/rooms/{code}/change-status
     * Cambia el estado de la sala desde la app del docente.
     */
    public function apiCambiarEstadoSalaApp(Request $request, $code)
    {
        $request->validate([
            'status' => 'required|string'
        ]);

        $room = Room::where('code', strtoupper($code))->first();

        if (!$room) {
            return response()->json(['success' => false, 'message' => 'La sala no existe.'], 404);
        }

        $room->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'El estado de la sala cambió a: ' . $request->status
        ]);
    }

    /**
     * GET /api/rooms/{code}/reporte
     * Devuelve el reporte de una sala para la app móvil.
     */
    public function apiReporteApp($code)
    {
        $room = Room::where('code', $code)->first();

        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Sala no encontrada'], 404);
        }

        $preguntas = is_array($room->questions) ? $room->questions : [];

        $respuestas = StudentResponse::where('room_code', $code)
            ->where('question_index', '>=', 0)
            ->orderBy('question_index')
            ->get();

        $estudiantes = $respuestas->groupBy('student_name')->map(function ($items, $name) use ($preguntas) {
            $detalle = [];
            foreach ($preguntas as $idx => $p) {
                $resp = $items->firstWhere('question_index', $idx);
                $detalle[] = [
                    'selected_option' => $resp ? $resp->selected_option : null,
                    'is_correct'      => $resp ? (bool) $resp->is_correct : false,
                    'is_flagged'      => $resp ? (bool) $resp->is_flagged : false,
                    'respondio'       => $resp !== null,
                ];
            }
            $total = count($preguntas);
            $score = $items->where('is_correct', true)->count();

            return [
                'student_name'  => $name,
                'score'         => $score,
                'total'         => $total,
                'porcentaje'    => $total > 0 ? round(($score / $total) * 100) : 0,
                'tiene_bandera' => $items->contains('is_flagged', true),
                'detalle'       => $detalle,
            ];
        })->values();

        $preguntasConStats = [];
        foreach ($preguntas as $idx => $p) {
            $respondieron = $estudiantes->filter(fn($e) => $e['detalle'][$idx]['respondio'] ?? false)->count();
            $acertaron = $estudiantes->filter(fn($e) => $e['detalle'][$idx]['is_correct'] ?? false)->count();
            $banderas = $estudiantes->filter(fn($e) => $e['detalle'][$idx]['is_flagged'] ?? false)->count();

            $preguntasConStats[] = [
                'pregunta'     => $p['pregunta'] ?? '—',
                'opciones'     => $p['opciones'] ?? [],
                'correcta'     => $p['correcta'] ?? 0,
                'respondieron' => $respondieron,
                'acertaron'    => $acertaron,
                'banderas'     => $banderas,
            ];
        }

        $totalBanderas = $estudiantes->filter(fn($e) => $e['tiene_bandera'])->count();

        return response()->json([
            'success'        => true,
            'room'           => [
                'code'          => $room->code,
                'pdf_name'      => $room->pdf_name,
                'status'        => $room->status,
                'num_questions' => $room->num_questions,
                'difficulty'    => $room->difficulty,
                'created_at'    => $room->created_at->format('d/m/Y H:i'),
                'finished_at'   => $room->finished_at ? $room->finished_at->format('d/m/Y H:i') : null,
            ],
            'preguntas'      => $preguntasConStats,
            'estudiantes'    => $estudiantes,
            'total_banderas' => $totalBanderas,
        ]);
    }
}
