<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Document;
use App\Models\StudentResponse;
use App\Models\StudyCard;
use App\Models\StudyCardSet;
use App\Models\StudyCardDifficult;
use App\Jobs\GenerateQuestionsBatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SoloExamController extends Controller
{
    /**
     * Mostrar vista de configuración del examen individual.
     */
    public function configurar()
    {
        $this->limpiarSalasIndividualesExpiradas();
        $documentos = Document::where('user_id', Auth::id())->latest()->get();
        return view('solo-exam-configurar', compact('documentos'));
    }

    /**
     * Crear examen individual (web).
     */
    public function crear(Request $request)
    {
        $request->validate([
            'document_id'   => 'required|exists:documents,id',
            'num_questions' => 'required|integer|min:3|max:50',
            'difficulty'    => 'required|in:basico,intermedio,avanzado',
        ]);

        $userId = Auth::id();
        $this->cancelarSalasIndividualesActivasForUser($userId);

        $documento = Document::where('id', $request->document_id)
            ->where('user_id', $userId)->firstOrFail();

        $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));

        $room = Room::create([
            'user_id'       => $userId,
            'code'          => $code,
            'is_individual' => true,
            'pdf_name'      => $documento->name ?? 'Documento_PDF',
            'num_questions' => $request->num_questions,
            'difficulty'    => $request->difficulty,
            'status'        => 'generando',
        ]);

        $this->despacharGeneracion($room, $documento, $request->num_questions, $request->difficulty);

        return redirect()->route('solo-exam.play', $code);
    }

    /**
     * Vista de juego para examen individual.
     */
    public function play($code)
    {
        $room = Room::where('code', $code)
            ->where('user_id', Auth::id())
            ->where('is_individual', true)->firstOrFail();
        $nombre = Auth::user()->name;
        return view('solo-exam-play', compact('room', 'nombre'));
    }

    /**
     * API: Obtener estado del examen individual (polling).
     */
    public function apiStatus($code)
    {
        $room = Room::where('code', $code)->first();
        if (!$room) return response()->json(['error' => 'Sala no encontrada'], 404);

        $questionsArray = is_string($room->questions)
            ? json_decode($room->questions, true) : $room->questions;

        // Auto-corrección: si es examen individual con preguntas listas pero status en "espera", corregir a "en_vivo"
        if ($room->is_individual && $room->status === 'espera' && !empty($questionsArray)) {
            $room->update(['status' => 'en_vivo']);
            $room->status = 'en_vivo';
            Log::info("[SoloExam] Auto-corregido status de sala {$code} de 'espera' a 'en_vivo'");
        }

        return response()->json([
            'status'            => $room->status,
            'questions'         => $questionsArray,
            'total_batches'     => (int) ($room->total_batches ?? 1),
            'batches_completed' => (int) ($room->batches_completed ?? 0),
        ]);
    }

    /**
     * API: Guardar respuesta individual (web).
     */
    public function guardarRespuesta(Request $request)
    {
        $request->validate([
            'room_code'       => 'required|string',
            'question_index'  => 'required|integer',
            'selected_option' => 'required|integer',
            'is_correct'      => 'required|boolean',
            'is_flagged'      => 'required|boolean',
        ]);

        $room = Room::where('code', $request->room_code)->where('is_individual', true)->first();
        if (!$room) return response()->json(['error' => 'Sala no encontrada'], 404);

        $studentName = Auth::user()->name;
        StudentResponse::updateOrCreate(
            ['room_code' => $request->room_code, 'student_name' => $studentName, 'question_index' => $request->question_index],
            ['selected_option' => $request->selected_option, 'is_correct' => $request->is_correct, 'is_flagged' => $request->is_flagged]
        );
        return response()->json(['success' => true]);
    }

    /**
     * API: Finalizar examen individual (web).
     */
    public function finalizar($code)
    {
        $room = Room::where('code', $code)->where('is_individual', true)->first();
        if (!$room) return response()->json(['error' => 'Sala no encontrada'], 404);
        $room->update(['status' => 'finalizado', 'finished_at' => now()]);
        return response()->json(['success' => true]);
    }

    /**
     * Ver reporte del examen individual.
     */
    public function reporte($code)
    {
        $room = Room::where('code', $code)->where('user_id', Auth::id())->where('is_individual', true)->firstOrFail();
        $preguntas = is_array($room->questions) ? $room->questions : [];
        $studentName = Auth::user()->name;

        $respuestas = StudentResponse::where('room_code', $code)
            ->where('student_name', $studentName)
            ->where('question_index', '>=', 0)->orderBy('question_index')->get();

        $detallePreguntas = [];
        foreach ($preguntas as $idx => $p) {
            $resp = $respuestas->firstWhere('question_index', $idx);
            $detallePreguntas[] = [
                'index'           => $idx,
                'pregunta'        => $p['pregunta'] ?? '—',
                'opciones'        => $p['opciones'] ?? [],
                'correcta'        => $p['correcta'] ?? 0,
                'selected_option' => $resp ? $resp->selected_option : null,
                'is_correct'      => $resp ? $resp->is_correct : false,
                'is_flagged'      => $resp ? $resp->is_flagged : false,
                'respondio'       => $resp !== null,
            ];
        }

        $score = $respuestas->where('is_correct', true)->count();
        $total = count($preguntas);
        $estudiantes = collect([[
            'student_name' => $studentName, 'detalle' => $detallePreguntas,
            'score' => $score, 'total' => $total, 'tieneBandera' => $respuestas->contains('is_flagged', true),
        ]]);
        return view('solo-exam-reporte', compact('room', 'preguntas', 'estudiantes'));
    }

    /**
     * API: Marcar pregunta como difícil (para el ahorcado) — versión web.
     */
    public function marcarDificil(Request $request)
    {
        $request->validate([
            'room_code'      => 'required|string',
            'question_index' => 'required|integer|min:0',
            'pregunta'       => 'required|string',
            'respuesta'      => 'required|string',
        ]);

        $userId = Auth::id();
        if (!$userId) return response()->json(['error' => 'No autenticado'], 401);

        return $this->procesarMarcarDificil($request, $userId);
    }

    /**
     * API: Marcar pregunta como difícil — versión Android (sin auth:sanctum).
     */
    public function apiMarcarDificil(Request $request)
    {
        $request->validate([
            'room_code'      => 'required|string',
            'question_index' => 'required|integer|min:0',
            'pregunta'       => 'required|string',
            'respuesta'      => 'required|string',
            'user_id'        => 'required|integer',
        ]);

        return $this->procesarMarcarDificil($request, $request->input('user_id'));
    }

    /**
     * Lógica compartida para marcar una pregunta como difícil.
     */
    protected function procesarMarcarDificil(Request $request, int $userId): \Illuminate\Http\JsonResponse
    {
        $set = StudyCardSet::firstOrCreate(
            ['user_id' => $userId, 'title' => 'Examen Individual - Difíciles'],
            ['status' => 'activo']
        );

        // Verificar si ya existe una StudyCard con el mismo contenido en este set
        $existingCard = StudyCard::where('study_card_set_id', $set->id)
            ->where('front', $request->pregunta)
            ->where('back', $request->respuesta)
            ->first();

        if ($existingCard) {
            // La tarjeta ya existe; verificar si ya está marcada como difícil
            $existingDifficult = StudyCardDifficult::where('user_id', $userId)
                ->where('study_card_set_id', $set->id)
                ->where('card_index', $existingCard->id)->first();

            if ($existingDifficult) {
                return response()->json(['success' => true, 'message' => 'Ya marcada como difícil']);
            }

            $existingCardIndex = StudyCard::where('study_card_set_id', $set->id)
                ->orderBy('id')->pluck('id')->search($existingCard->id);

            StudyCardDifficult::create([
                'user_id'           => $userId,
                'study_card_set_id' => $set->id,
                'card_index'        => $existingCardIndex,
            ]);

            return response()->json(['success' => true, 'message' => 'Pregunta marcada como difícil para el ahorcado']);
        }

        $card = StudyCard::create([
            'study_card_set_id' => $set->id,
            'front'             => $request->pregunta,
            'back'              => $request->respuesta,
        ]);

        // card_index = posición de la tarjeta en el set (ordenado por id)
        $cardIndex = StudyCard::where('study_card_set_id', $set->id)
            ->orderBy('id')->pluck('id')->search($card->id);

        StudyCardDifficult::create([
            'user_id'           => $userId,
            'study_card_set_id' => $set->id,
            'card_index'        => $cardIndex,
        ]);

        return response()->json(['success' => true, 'message' => 'Pregunta marcada como difícil para el ahorcado']);
    }

    // ══════════════════════════════════════════════════════════════
    // API ENDPOINTS (para la app Android — sin auth:sanctum)
    // ══════════════════════════════════════════════════════════════

    /**
     * API: Crear examen individual desde Android.
     */
    public function apiCrear(Request $request)
    {
        $request->validate([
            'document_id'   => 'required|exists:documents,id',
            'num_questions' => 'required|integer|min:3|max:50',
            'difficulty'    => 'required|in:basico,intermedio,avanzado',
        ]);

        $userId = $request->input('user_id');
        if (!$userId) return response()->json(['error' => 'user_id requerido'], 400);

        $this->cancelarSalasIndividualesActivasForUser($userId);

        $documento = Document::where('id', $request->document_id)
            ->where('user_id', $userId)->firstOrFail();

        $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));

        $room = Room::create([
            'user_id'       => $userId,
            'code'          => $code,
            'is_individual' => true,
            'pdf_name'      => $documento->name ?? 'Documento_PDF',
            'num_questions' => $request->num_questions,
            'difficulty'    => $request->difficulty,
            'status'        => 'generando',
        ]);

        $this->despacharGeneracion($room, $documento, $request->num_questions, $request->difficulty);

        return response()->json(['success' => true, 'code' => $code, 'message' => 'Examen creado.']);
    }

    /**
     * API: Guardar respuesta individual desde Android.
     */
    public function apiGuardarRespuesta(Request $request)
    {
        $request->validate([
            'room_code'       => 'required|string',
            'question_index'  => 'required|integer',
            'selected_option' => 'required|integer',
            'is_correct'      => 'required|boolean',
            'is_flagged'      => 'required|boolean',
        ]);

        $room = Room::where('code', $request->room_code)->where('is_individual', true)->first();
        if (!$room) return response()->json(['error' => 'Sala no encontrada'], 404);

        $studentName = $request->input('student_name', 'Estudiante');
        StudentResponse::updateOrCreate(
            ['room_code' => $request->room_code, 'student_name' => $studentName, 'question_index' => $request->question_index],
            ['selected_option' => $request->selected_option, 'is_correct' => $request->is_correct, 'is_flagged' => $request->is_flagged]
        );
        return response()->json(['success' => true]);
    }

    /**
     * API: Finalizar examen individual desde Android.
     */
    public function apiFinalizar($code)
    {
        $room = Room::where('code', $code)->where('is_individual', true)->first();
        if (!$room) return response()->json(['error' => 'Sala no encontrada'], 404);
        $room->update(['status' => 'finalizado', 'finished_at' => now()]);
        return response()->json(['success' => true]);
    }

    /**
     * API: Reporte del examen individual desde Android.
     */
    public function apiReporte(Request $request, $code)
    {
        $room = Room::where('code', $code)->where('is_individual', true)->first();
        if (!$room) return response()->json(['error' => 'Sala no encontrada'], 404);

        $preguntas = is_array($room->questions) ? $room->questions : [];
        $studentName = $request->input('student_name', 'Estudiante');

        $respuestas = StudentResponse::where('room_code', $code)
            ->where('student_name', $studentName)
            ->where('question_index', '>=', 0)->orderBy('question_index')->get();

        $score = $respuestas->where('is_correct', true)->count();
        $total = count($preguntas);

        $detallePreguntas = [];
        foreach ($preguntas as $idx => $p) {
            $resp = $respuestas->firstWhere('question_index', $idx);
            $detallePreguntas[] = [
                'index'           => $idx,
                'pregunta'        => $p['pregunta'] ?? '—',
                'opciones'        => $p['opciones'] ?? [],
                'correcta'        => $p['correcta'] ?? 0,
                'selected_option' => $resp?->selected_option,
                'is_correct'      => $resp?->is_correct ?? false,
            ];
        }

        return response()->json([
            'success' => true, 'code' => $room->code, 'pdf_name' => $room->pdf_name,
            'difficulty' => $room->difficulty, 'score' => $score, 'total' => $total,
            'percentage' => $total > 0 ? round(($score / $total) * 100, 1) : 0,
            'finished_at' => $room->finished_at?->format('d/m/Y H:i'),
            'detalle_preguntas' => $detallePreguntas,
        ]);
    }

    /**
     * API: Historial de exámenes individuales desde Android.
     */
    public function apiHistorial(Request $request)
    {
        $userId = $request->input('user_id');
        if (!$userId) return response()->json(['error' => 'user_id requerido'], 400);
        $salas = Room::where('user_id', $userId)->where('is_individual', true)
            ->orderBy('created_at', 'desc')->get()
            ->map(function ($room) use ($userId) {
                $score = StudentResponse::where('room_code', $room->code)
                    ->where('question_index', '>=', 0)->where('is_correct', true)->count();
                $total = $room->num_questions;
                $room->score = $score;
                $room->percentage = $total > 0 ? round(($score / $total) * 100, 1) : 0;
                return $room;
            });
        return response()->json(['success' => true, 'salas' => $salas]);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ══════════════════════════════════════════════════════════════

    protected function despacharGeneracion(Room $room, Document $documento, int $numQuestions, string $difficulty): void
    {
        $ragResult = $this->obtenerContextoRAG($documento->id, $numQuestions);
        $batches = $ragResult['batches'];

        $textoPdf = '';
        foreach ($batches as $b) {
            $nuevoTexto = ($textoPdf ? "\n\n" : '') . $b['context'];
            if (mb_strlen($nuevoTexto) > 7000) break;
            $textoPdf = $nuevoTexto;
        }
        if (mb_strlen($textoPdf) > 5000) {
            $textoPdf = mb_substr($textoPdf, 0, 5000);
        }

        GenerateQuestionsBatch::dispatch(
            $room->code, $textoPdf, $numQuestions, $difficulty, 0, 1
        )->onConnection('database')->onQueue('default');
    }

    protected function obtenerContextoRAG(int $documentId, int $numQuestions): array
    {
        $documento = \App\Models\Document::find($documentId);

        // Obtener chunks de la tabla document_chunks
        $totalChunks = \App\Models\DocumentChunk::where('document_id', $documentId)->count();

        // Si no hay chunks, intentar usar extracted_text del documento como fallback
        if ($totalChunks === 0) {
            $extractedText = trim($documento->extracted_text ?? '');

            // Si extracted_text es genérico o está vacío, no hay contenido procesable
            if (empty($extractedText) || $extractedText === 'Texto guardado en chunks' || $extractedText === 'Texto guardado en chunks desde App') {
                return ['batches' => [['context' => 'El documento no tiene texto procesable. Asegúrate de que el PDF fue cargado correctamente.', 'questions' => $numQuestions]]];
            }

            // Usar extracted_text directamente: dividirlo en chunks al vuelo
            $totalChunks = 0; // Forzar el camino de chunks
            $chunks = $this->dividirTextoEnChunks($extractedText);
            Log::info("[SoloExam] No hay chunks en BD para doc {$documentId}, usando extracted_text (" . mb_strlen($extractedText) . " chars, " . count($chunks) . " fragmentos)");
        } else {
            $chunks = \App\Models\DocumentChunk::where('document_id', $documentId)
                ->orderBy('id', 'asc')->pluck('chunk_text')->toArray();
        }


        $maxCharsPorBatch = 7000;

        $textoCompleto = implode("\n\n", $chunks);
        if (mb_strlen($textoCompleto) <= $maxCharsPorBatch) {
            $ctx = $this->instruccionContexto() . $textoCompleto;
            if (mb_strlen($ctx) > $maxCharsPorBatch) $ctx = mb_substr($ctx, 0, $maxCharsPorBatch);
            return ['batches' => [['context' => $ctx, 'questions' => $numQuestions]]];
        }

        $chunksSeleccionados = array_slice($chunks, 0, 6);
        $secciones = 3;
        $chunksRestantes = array_slice($chunks, 6);
        $tamanoSeccion = (int) ceil(count($chunksRestantes) / $secciones);
        for ($s = 0; $s < $secciones; $s++) {
            $seccion = array_slice($chunksRestantes, $s * $tamanoSeccion, $tamanoSeccion);
            $chunksSeleccionados = array_merge($chunksSeleccionados, array_slice($seccion, 0, min(4, count($seccion))));
        }
        $chunksSeleccionados = array_unique(array_merge($chunksSeleccionados, array_slice($chunks, -3)));

        $batches = []; $batchActual = []; $charsActual = 0;
        foreach ($chunksSeleccionados as $chunk) {
            $chunkLen = mb_strlen($chunk) + 2;
            if ($charsActual + $chunkLen > $maxCharsPorBatch && !empty($batchActual)) {
                $batches[] = ['context' => $this->instruccionContexto() . implode("\n\n", $batchActual), 'questions' => 0];
                $batchActual = [$chunk]; $charsActual = $chunkLen;
            } else { $batchActual[] = $chunk; $charsActual += $chunkLen; }
        }
        if (!empty($batchActual)) {
            $batches[] = ['context' => $this->instruccionContexto() . implode("\n\n", $batchActual), 'questions' => 0];
        }

        $numBatches = count($batches); $restantes = $numQuestions;
        for ($i = 0; $i < $numBatches; $i++) {
            $paraEste = (int) ceil($restantes / ($numBatches - $i));
            $batches[$i]['questions'] = min($paraEste, $restantes);
            $restantes -= $paraEste;
        }
        foreach ($batches as &$b) {
            if (mb_strlen($b['context']) > $maxCharsPorBatch) $b['context'] = mb_substr($b['context'], 0, $maxCharsPorBatch);
        }
        unset($b);
        if (count($batches) > 4) $batches = array_slice($batches, 0, 4);

        return ['batches' => $batches];
    }

    protected function instruccionContexto(): string
    {
        return "INSTRUCCIONES IMPORTANTES: Genera preguntas SOLO sobre el CONTENIDO TEMÁTICO del texto. "
            . "NUNCA preguntes sobre el archivo, documento, PDF o dónde está almacenado. "
            . "Evalúa CONOCIMIENTO REAL sobre los temas del texto.\n\n";
    }

    /**
     * Divide texto largo en chunks con solapamiento (overlap) para procesamiento por lotes.
     * Copiado del patrón usado en DocumentController::upload() y ChunkableMindMap.
     */
    protected function dividirTextoEnChunks(string $texto, int $maxCaracteres = 1500, int $palabrasSolapamiento = 40): array
    {
        $textoLimpio = preg_replace('/\s+/', ' ', $texto);
        $palabrasArray = explode(' ', $textoLimpio);
        $fragmentos = [];
        $chunkActual = [];
        $longitudActual = 0;

        foreach ($palabrasArray as $palabra) {
            $chunkActual[] = $palabra;
            $longitudActual += mb_strlen($palabra) + 1;

            if ($longitudActual >= $maxCaracteres) {
                $fragmentos[] = implode(' ', $chunkActual);
                $chunkActual = array_slice($chunkActual, -$palabrasSolapamiento);
                $longitudActual = mb_strlen(implode(' ', $chunkActual));
            }
        }

        if (!empty($chunkActual)) {
            $fragmentos[] = implode(' ', $chunkActual);
        }

        return $fragmentos;
    }

    protected function limpiarSalasIndividualesExpiradas(): void
    {
        $userId = Auth::id();
        if (!$userId) return;
        $limite = now()->subMinutes(30);
        Room::where('user_id', $userId)->where('is_individual', true)
            ->whereIn('status', ['generando'])->where('created_at', '<', $limite)
            ->update(['status' => 'finalizado', 'finished_at' => now()]);
    }

    protected function cancelarSalasIndividualesActivas(): void
    {
        $userId = Auth::id();
        if (!$userId) return;
        Room::where('user_id', $userId)->where('is_individual', true)
            ->whereIn('status', ['generando', 'en_vivo'])
            ->update(['status' => 'finalizado', 'finished_at' => now()]);
    }

    protected function cancelarSalasIndividualesActivasForUser(int $userId): void
    {
        Room::where('user_id', $userId)->where('is_individual', true)
            ->whereIn('status', ['generando', 'en_vivo'])
            ->update(['status' => 'finalizado', 'finished_at' => now()]);
    }
}
