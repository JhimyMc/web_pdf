<?php
//C:\laragon\www\web-pdf\app\Http\Controllers\QuizController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Document;
use App\Models\StudentResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Traits\ConnectsToLMStudio;

class QuizController extends Controller
{
    use ConnectsToLMStudio;
    // 1. Mostrar la vista para configurar una nueva sala
    public function configurar()
    {
        $this->limpiarSalasExpiradas();

        $documentos = Document::where('user_id', Auth::id())->latest()->get();

        // Detectar si ya hay una sala activa (no finalizada)
        $salaActiva = Room::where('user_id', Auth::id())
            ->whereIn('status', ['configurando', 'generando', 'espera', 'en_vivo'])
            ->first();

        return view('sala-configurar', compact('documentos', 'salaActiva'));
    }

    // 2. Procesar el formulario y crear la sala en la BD
    public function crearSala(Request $request)
    {
        $request->validate([
            'document_id'   => 'required|exists:documents,id',
            'num_questions' => 'required|integer|min:1|max:20',
            'difficulty'    => 'required|in:basico,intermedio,avanzado'
        ]);

        // Si el usuario ya tiene una sala activa, cancelarla automáticamente
        $this->cancelarSalasActivas();

        $documento = Document::where('id', $request->document_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));

        $room = Room::create([
            'user_id'       => Auth::id(),
            'code'          => $code,
            'pdf_name'      => $documento->name ?? 'Documento_PDF',
            'num_questions' => $request->num_questions,
            'difficulty'    => $request->difficulty,
            'status'        => 'configurando',
        ]);

        session(["room_config_{$code}" => [
            'document_id'   => $request->document_id,
            'num_questions' => $request->num_questions,
            'difficulty'    => $request->difficulty
        ]]);

        return redirect()->route('sala.dashboard', $code);
    }

    // 3. Mostrar el Panel de Control del Creador (Docente)
    public function dashboard($code)
    {
        $this->limpiarSalasExpiradas();

        $room = Room::where('code', $code)->where('user_id', Auth::id())->firstOrFail();
        return view('sala-dashboard', compact('room'));
    }

    // 4. Obtener estado en tiempo real (Polling Público)
    public function apiGetStatus($code)
    {
        $room = Room::where('code', $code)->first();

        if (!$room) {
            return response()->json(['error' => 'Sala no encontrada'], 404);
        }

        $responses = StudentResponse::where('room_code', $code)->get();

        $students = $responses->groupBy('student_name')->map(function ($items, $name) {
            $respuestasReales = $items->where('question_index', '>=', 0);
            return [
                'student_name'       => $name,
                'score'              => $respuestasReales->where('is_correct', true)->count() * 1,
                'is_flagged'         => $items->contains('is_flagged', true),
                'answered_questions' => $respuestasReales->count()
            ];
        })->values();

        $questionsArray = is_string($room->questions)
            ? json_decode($room->questions, true)
            : $room->questions;

        return response()->json([
            'status'            => $room->status,
            'questions'         => $questionsArray,
            'students'          => $students,
            'total_batches'     => (int) ($room->total_batches ?? 1),
            'batches_completed' => (int) ($room->batches_completed ?? 0),
        ]);
    }

    // 5. Mostrar la vista de juego para el participante (Lobby con marcado de asistencia -1)
    public function play(Request $request, $code)
    {
        $room   = Room::where('code', $code)->firstOrFail();
        $nombre = $request->query('nombre', 'Participante');

        // REGISTRO DE ASISTENCIA: fila con question_index -1 para que
        // el dashboard del profesor lo vea conectado inmediatamente.
        StudentResponse::firstOrCreate(
            [
                'room_code'      => $room->code,
                'student_name'   => $nombre,
                'question_index' => -1
            ],
            [
                'selected_option' => -1,
                'is_correct'      => false,
                'is_flagged'      => false
            ]
        );

        return view('sala-play', compact('room', 'nombre'));
    }

    // 6. Almacenar o actualizar respuestas enviadas por los alumnos
    public function apiSaveResponse(Request $request)
    {
        $request->validate([
            'room_code'       => 'required|string',
            'student_name'    => 'required|string',
            'question_index'  => 'required|integer',
            'selected_option' => 'required|integer',
            'is_correct'      => 'required|boolean',
            'is_flagged'      => 'required|boolean',
        ]);

        StudentResponse::updateOrCreate(
            [
                'room_code'      => $request->room_code,
                'student_name'   => $request->student_name,
                'question_index' => $request->question_index,
            ],
            [
                'selected_option' => $request->selected_option,
                'is_correct'      => $request->is_correct,
                'is_flagged'      => $request->is_flagged,
            ]
        );

        return response()->json(['success' => true]);
    }    // 7. Disparar generación asíncrona (flujo WEB - contexto optimizado para 4K tokens)
    public function apiGenerateQuestions($code)
    {
        $room = Room::where('code', $code)->firstOrFail();

        $numQuestions = $room->num_questions ?? 5;
        $difficulty   = $room->difficulty ?? 'intermedio';

        $config = session("room_config_{$code}");
        $documentId = $config['document_id'] ?? null;

        $textoPdf = "Texto no encontrado.";
        if ($documentId) {
            // Usar el nuevo RAG (solo el primer batch si es pequeño, o batches)
            $ragResult = $this->obtenerContextoRAG($documentId, $numQuestions);
            $batches = $ragResult['batches'];

            // Para el flujo web, usar el primer batch (más importante) o concatenar si cabe
            if (count($batches) === 1) {
                $textoPdf = $batches[0]['context'];
            } else {
                // Unir los primeros N batches que quepan en ~10K chars
                $textoPdf = '';
                foreach ($batches as $b) {
                    $nuevoTexto = ($textoPdf ? "\n\n" : '') . $b['context'];
                    if (mb_strlen($nuevoTexto) > 10000) break;
                    $textoPdf = $nuevoTexto;
                }
            }
        }

        // 🔴 CAP DURO DE SEGURIDAD para contexto
        if (mb_strlen($textoPdf) > 5000) {
            $charsBefore = mb_strlen($textoPdf);
            $textoPdf = mb_substr($textoPdf, 0, 5000);
            Log::warning("apiGenerateQuestions contexto recortado de {$charsBefore} a 5000 chars para sala {$code}");
        }

        try {
            $room->update(['status' => 'generando']);

            $systemPrompt = $this->getSystemPromptExamen($difficulty);
            $userMessage = "Genera EXACTAMENTE " . ($numQuestions + 5) . " preguntas diferentes basándote EXCLUSIVAMENTE en el siguiente texto:\n\n\"\"\"\n{$textoPdf}\n\"\"\"";

            $resultado = $this->llamarLMStudio($systemPrompt, $userMessage, 0.6, 2048);

            if ($resultado) {
                $content = $resultado['content'];
                $questionsArray = null;

                if (preg_match('/\[.*\]/s', $content, $matches)) {
                    $questionsArray = json_decode($matches[0], true);
                } else {
                    $questionsArray = json_decode($content, true);
                }

                if (is_array($questionsArray)) {
                    $questionsArray = $this->sanitizarPreguntas($questionsArray, $numQuestions);
                    if (empty($questionsArray)) {
                        $questionsArray = [["pregunta" => "Error al generar preguntas. Intente crear la sala de nuevo.", "opciones" => ["Reintentar", "B", "C", "D", "E"], "correcta" => 0]];
                    }

                    $room->update([
                        'questions' => $questionsArray,
                        'status'    => 'espera'
                    ]);

                    return response()->json(['success' => true, 'status' => 'espera']);
                }
            }

            return response()->json(['success' => true, 'status' => 'generando']);
        } catch (\Exception $e) {
            Log::error("Error en motor de IA: " . $e->getMessage());
            return response()->json(['error' => 'Error de conexión con IA.', 'details' => $e->getMessage()], 500);
        }
    }

    // 8. Iniciar Sala
    public function apiStartRoom($code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $room->update(['status' => 'en_vivo']);
        return response()->json(['success' => true]);
    }

    // 10. Finalizar Sala
    public function apiEndRoom($code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $room->update([
            'status'      => 'finalizado',
            'finished_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }

    // 11. Ver reporte detallado de la sala (docente)
    public function reporte($code)
    {
        $room = Room::where('code', $code)->where('user_id', Auth::id())->firstOrFail();

        $preguntas = is_array($room->questions) ? $room->questions : [];

        $respuestas = StudentResponse::where('room_code', $code)
            ->where('question_index', '>=', 0)
            ->orderBy('question_index')
            ->get();

        // Agrupar respuestas por estudiante
        $estudiantes = $respuestas->groupBy('student_name')->map(function ($items, $name) use ($preguntas) {
            $detallePreguntas = [];
            foreach ($preguntas as $idx => $p) {
                $resp = $items->firstWhere('question_index', $idx);
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
            return [
                'student_name' => $name,
                'detalle'      => $detallePreguntas,
                'score'        => $items->where('is_correct', true)->count(),
                'total'        => count($preguntas),
                'tieneBandera' => $items->contains('is_flagged', true),
            ];
        })->values();

        return view('sala-reporte', compact('room', 'preguntas', 'estudiantes'));
    }

    // 12. Historial de salas del docente
    public function historial()
    {
        $this->limpiarSalasExpiradas();

        $salas = Room::where('user_id', Auth::id())
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

                $room->total_estudiantes = $totalEstudiantes;
                $room->total_respuestas  = $totalRespuestas;
                $room->promedio          = $totalRespuestas > 0
                    ? round(($totalCorrectas / $totalRespuestas) * 100, 1)
                    : 0;

                return $room;
            });

        return view('sala-historial', compact('salas'));
    }

    // 13. Cancelar / Eliminar Sala
    public function apiDeleteRoom($code)
    {
        $room = Room::where('code', $code)->first();

        if (!$room) {
            return response()->json(['error' => 'Sala no encontrada'], 404);
        }

        StudentResponse::where('room_code', $code)->delete();
        $room->delete();

        return response()->json(['success' => true, 'message' => 'Sala eliminada con éxito']);
    }

    // =========================================================================
    // AUTO-EXPIRACIÓN DE SALAS
    // =========================================================================

    /**
     * Cancela salas que llevan más de 30 min sin iniciar el examen.
     * Estados expirables: configurando, generando, espera.
     */
    protected function limpiarSalasExpiradas(): void
    {
        $limite = now()->subMinutes(30);

        Room::where('user_id', Auth::id())
            ->whereIn('status', ['configurando', 'generando', 'espera'])
            ->where('created_at', '<', $limite)
            ->update([
                'status'      => 'finalizado',
                'finished_at' => now(),
            ]);
    }

    /**
     * Cancela todas las salas activas del usuario (para permitir crear una nueva).
     */
    protected function cancelarSalasActivas(): void
    {
        Room::where('user_id', Auth::id())
            ->whereIn('status', ['configurando', 'generando', 'espera', 'en_vivo'])
            ->update([
                'status'      => 'finalizado',
                'finished_at' => now(),
            ]);
    }

    /**
     * GET /sala/api/check-active-room
     * Devuelve información si el usuario tiene una sala activa.
     */
    public function apiCheckActiveRoom()
    {
        $room = Room::where('user_id', Auth::id())
            ->whereIn('status', ['configurando', 'generando', 'espera', 'en_vivo'])
            ->first();

        if (!$room) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active'    => true,
            'code'      => $room->code,
            'status'    => $room->status,
            'pdf_name'  => $room->pdf_name,
            'created_at' => $room->created_at->format('d/m/Y H:i'),
            'minutos_transcurridos' => $room->created_at->diffInMinutes(now()),
        ]);
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * System prompt IDÉNTICO al del flujo n8n para mantener consistencia.
     * Se usa cuando llamamos a LM Studio DIRECTAMENTE (sin n8n).
     */
    protected function getSystemPromptExamen(string $difficulty): string
    {
        return "Eres un profesor universitario experto creando exámenes. Tu tarea es generar EXACTAMENTE la cantidad de preguntas solicitadas basándote EXCLUSIVAMENTE en el texto.\n\n"
            . "REGLAS ESTRICTAS:\n"
            . "1. Devuelve ÚNICAMENTE un array JSON válido. NADA de texto antes o después.\n"
            . "2. Las preguntas deben ser SOBRE EL CONTENIDO TEMÁTICO del texto, NO sobre el archivo, documento o PDF en sí.\n"
            . "3. NUNCA generes preguntas como: '¿Dónde se almacena?', '¿Qué tipo de archivo es?', '¿Cuál es el título del documento?'.\n"
            . "4. VARIEDAD: Las preguntas NO DEBEN REPETIRSE. Cambia de tema, explora todo el texto.\n"
            . "5. OPCIONES LIMPIAS: NO incluyas letras como \"A)\", \"B)\", \"C)\" ni viñetas en las opciones. Escribe solo el texto de la respuesta.\n"
            . "6. ESTRUCTURA: Exactamente 5 opciones por pregunta. La clave 'correcta' debe ser un número entero (0 al 4).\n"
            . "7. Dificultad solicitada: {$difficulty}.\n\n"
            . "EJEMPLO DEL FORMATO ESPERADO:\n"
            . "[\n"
            . "  {\n"
            . "    \"pregunta\": \"¿Cuál es el propósito principal de un Firewall?\",\n"
            . "    \"opciones\": [\"Bloquear accesos no autorizados\", \"Acelerar el internet\", \"Crear copias de seguridad\", \"Revisar el código fuente\", \"Ninguna de las anteriores\"],\n"
            . "    \"correcta\": 0\n"
            . "  }\n"
            . "]";
    }

    /*
     * NUEVO RAG: Obtiene contexto en batches optimizados para modelos con 4K tokens.
     *
     - Documentos que caben en ≤10,000 chars → 1 batch.
     - Documentos grandes → múltiples batches, cada uno con chunks de distintas secciones.
     *
     * @return array{batches: array<int, array{context: string, questions: int}>}
     */
    protected function obtenerContextoRAG(int $documentId, int $numQuestions): array
    {
        $totalChunks = \App\Models\DocumentChunk::where('document_id', $documentId)->count();

        if ($totalChunks === 0) {
            return [
                'batches' => [
                    ['context' => 'El documento está vacío.', 'questions' => $numQuestions]
                ]
            ];
        }

        $chunks = \App\Models\DocumentChunk::where('document_id', $documentId)
            ->orderBy('id', 'asc')
            ->pluck('chunk_text')
            ->toArray();

        // 🔴 Cap para modelo con 4096 tokens:
        // 7000 chars ≈ 1750 tokens + system prompt (~500) + wrapper (~80) + respuesta (~500) = ~2830 tokens
        // Multi-batch ahora va DIRECTAMENTE a LM Studio (secuencial), sin concurrencia.
        $maxCharsPorBatch = 7000;

        $textoCompleto = implode("\n\n", $chunks);
        if (mb_strlen($textoCompleto) <= $maxCharsPorBatch) {
            $contextoConInstruccion = $this->instruccionContexto() . $textoCompleto;
            // Cap duro: incluso con instrucción, no superar el máximo
            if (mb_strlen($contextoConInstruccion) > $maxCharsPorBatch) {
                $contextoConInstruccion = mb_substr($contextoConInstruccion, 0, $maxCharsPorBatch);
            }
            return [
                'batches' => [
                    ['context' => $contextoConInstruccion, 'questions' => $numQuestions]
                ]
            ];
        }

        // --- Documento grande: selección estratégica de chunks ---
        $chunksSeleccionados = [];

        // 1. Primeros 6 chunks (fundamentos del documento)
        $inicio = array_slice($chunks, 0, 6);
        $chunksSeleccionados = array_merge($chunksSeleccionados, $inicio);

        // 2. Dividir el resto en secciones y tomar muestras de cada una
        $secciones = 3;
        $chunksRestantes = array_slice($chunks, 6);
        $tamanoSeccion = (int) ceil(count($chunksRestantes) / $secciones);

        for ($s = 0; $s < $secciones; $s++) {
            $seccion = array_slice($chunksRestantes, $s * $tamanoSeccion, $tamanoSeccion);
            // De cada sección tomar los primeros 3-4 chunks (los más representativos)
            $tomar = min(4, count($seccion));
            $muestra = array_slice($seccion, 0, $tomar);
            $chunksSeleccionados = array_merge($chunksSeleccionados, $muestra);
        }

        // 3. Últimos 3 chunks (conclusiones)
        $final = array_slice($chunks, -3);
        $chunksSeleccionados = array_merge($chunksSeleccionados, $final);

        // Deduplicar
        $chunksSeleccionados = array_unique($chunksSeleccionados);

        // Ahora dividir los chunks seleccionados en batches de ≤maxCharsPorBatch chars
        $batches = [];
        $batchActual = [];
        $charsActual = 0;

        foreach ($chunksSeleccionados as $chunk) {
            $chunkLen = mb_strlen($chunk) + 2; // +2 por \n\n

            if ($charsActual + $chunkLen > $maxCharsPorBatch && !empty($batchActual)) {
                $contexto = $this->instruccionContexto() . implode("\n\n", $batchActual);
                $batches[] = ['context' => $contexto, 'questions' => 0]; // questions se distribuye después
                $batchActual = [$chunk];
                $charsActual = $chunkLen;
            } else {
                $batchActual[] = $chunk;
                $charsActual += $chunkLen;
            }
        }

        if (!empty($batchActual)) {
            $contexto = $this->instruccionContexto() . implode("\n\n", $batchActual);
            $batches[] = ['context' => $contexto, 'questions' => 0];
        }

        // Distribuir preguntas entre batches
        $numBatches = count($batches);
        $restantes = $numQuestions;
        for ($i = 0; $i < $numBatches; $i++) {
            $paraEste = (int) ceil($restantes / ($numBatches - $i));
            $batches[$i]['questions'] = min($paraEste, $restantes);
            $restantes -= $paraEste;
        }

        // Aplicar cap duro a CADA batch individualmente (seguridad extra)
        foreach ($batches as &$b) {
            if (mb_strlen($b['context']) > $maxCharsPorBatch) {
                $b['context'] = mb_substr($b['context'], 0, $maxCharsPorBatch);
            }
        }
        unset($b);

        // Limitar a máximo 4 batches para no saturar el modelo
        if (count($batches) > 4) {
            $fusionados = array_slice($batches, 0, 3);
            $extra = array_slice($batches, 3);
            $contextoExtra = '';
            $pregExtra = 0;
            foreach ($extra as $e) {
                $ctxSinInstruccion = preg_replace('/^INSTRUCCIONES IMPORTANTES:.*?\n{2}/s', '', $e['context']);
                $contextoExtra .= ($contextoExtra ? "\n\n" : '') . $ctxSinInstruccion;
                $pregExtra += $e['questions'];
            }
            $fusionados[2]['questions'] += $pregExtra;
            $contextoCombinado = $fusionados[2]['context'] . "\n\n" . $contextoExtra;
            // Cap duro reducido: máximo 8000 chars para el batch fusionado
            if (mb_strlen($contextoCombinado) > 8000) {
                $contextoCombinado = mb_substr($contextoCombinado, 0, 8000);
            }
            $fusionados[2]['context'] = $contextoCombinado;
            $batches = $fusionados;
        }

        return ['batches' => $batches];
    }

    /**
     * Prepara batches desde fragmentos de texto (para PDFs subidos localmente).
     */
    protected function prepararBatchesDesdeFragmentos(array $fragmentos, int $numQuestions): array
    {
        $maxCharsPorBatch = 7000; // Multi-batch va directo a LM Studio (secuencial, sin concurrencia)
        $batches = [];
        $batchActual = [];
        $charsActual = 0;

        foreach ($fragmentos as $fragmento) {
            $fragLen = mb_strlen($fragmento) + 2;

            if ($charsActual + $fragLen > $maxCharsPorBatch && !empty($batchActual)) {
                $contexto = $this->instruccionContexto() . implode("\n\n", $batchActual);
                $batches[] = ['context' => $contexto, 'questions' => 0];
                $batchActual = [$fragmento];
                $charsActual = $fragLen;
            } else {
                $batchActual[] = $fragmento;
                $charsActual += $fragLen;
            }
        }

        if (!empty($batchActual)) {
            $contexto = $this->instruccionContexto() . implode("\n\n", $batchActual);
            $batches[] = ['context' => $contexto, 'questions' => 0];
        }

        // Aplicar cap duro a CADA batch
        foreach ($batches as &$b) {
            if (mb_strlen($b['context']) > $maxCharsPorBatch) {
                $b['context'] = mb_substr($b['context'], 0, $maxCharsPorBatch);
            }
        }
        unset($b);

        // Distribuir preguntas
        $numBatches = count($batches);
        $restantes = $numQuestions;
        for ($i = 0; $i < $numBatches; $i++) {
            $paraEste = (int) ceil($restantes / ($numBatches - $i));
            $batches[$i]['questions'] = min($paraEste, $restantes);
            $restantes -= $paraEste;
        }

        return $batches;
    }

    /**
     * Breve instrucción antepuesta al contexto para recordarle a la IA
     * que genere preguntas de CONTENIDO, no meta-preguntas.
     */
    protected function instruccionContexto(): string
    {
        return "INSTRUCCIONES IMPORTANTES: Genera preguntas SOLO sobre el CONTENIDO TEMÁTICO del texto. "
            . "NUNCA preguntes sobre el archivo, documento, PDF o dónde está almacenado. "
            . "Evalúa CONOCIMIENTO REAL sobre los temas del texto.\n\n";
    }

    /**
     * Legacy: selecciona contexto inteligente desde chunks en BD.
     * Optimizado para 4K tokens. Ya no usa getPreamble() (las instrucciones
     * ahora van en el system prompt de n8n).
     */
    protected function obtenerContextoInteligente(int $documentId): string
    {
        $totalChunks = \App\Models\DocumentChunk::where('document_id', $documentId)->count();

        if ($totalChunks === 0) {
            return "El documento está vacío.";
        }

        // Para documentos cortos, enviar TODO (cap a 10K chars)
        if ($totalChunks <= 8) {
            $texto = \App\Models\DocumentChunk::where('document_id', $documentId)
                ->orderBy('id', 'asc')
                ->pluck('chunk_text')
                ->implode("\n\n");
            if (mb_strlen($texto) > 10000) {
                $texto = mb_substr($texto, 0, 10000);
            }
            return $texto;
        }

        // Documentos grandes: selección estratégica con cap de 10K
        $chunksEnvio = [];

        // Primeros 5 chunks
        $inicio = \App\Models\DocumentChunk::where('document_id', $documentId)
            ->orderBy('id', 'asc')->limit(5)->pluck('chunk_text')->toArray();
        $chunksEnvio = array_merge($chunksEnvio, $inicio);

        // 4 chunks del medio
        $mitad = (int) floor($totalChunks / 2);
        $medio = \App\Models\DocumentChunk::where('document_id', $documentId)
            ->orderBy('id', 'asc')->skip($mitad - 2)->take(4)->pluck('chunk_text')->toArray();
        $chunksEnvio = array_merge($chunksEnvio, $medio);

        // 4 chunks del cuarto 3/4
        $tresCuartos = (int) floor($totalChunks * 0.75);
        $tarde = \App\Models\DocumentChunk::where('document_id', $documentId)
            ->orderBy('id', 'asc')->skip($tresCuartos - 2)->take(4)->pluck('chunk_text')->toArray();
        $chunksEnvio = array_merge($chunksEnvio, $tarde);

        // Últimos 3 chunks
        $final = \App\Models\DocumentChunk::where('document_id', $documentId)
            ->orderBy('id', 'desc')->limit(3)->pluck('chunk_text')->reverse()->values()->toArray();
        $chunksEnvio = array_merge($chunksEnvio, $final);

        $chunksEnvio = array_unique($chunksEnvio);
        $contextoFinal = implode("\n\n", $chunksEnvio);

        // Cap duro: 10,000 chars para que quepa en 4K tokens
        if (mb_strlen($contextoFinal) > 10000) {
            $contextoFinal = mb_substr($contextoFinal, 0, 10000);
        }

        return $contextoFinal;
    }

    /**
     * @deprecated Usar obtenerContextoRAG() en su lugar.
     */
    protected function seleccionarContextoDeTexto(string $textoPdf): string
    {
        $textoLimpio = preg_replace('/\s+/', ' ', $textoPdf);
        $totalChars = mb_strlen($textoLimpio);

        if ($totalChars <= 10000) {
            return $textoLimpio;
        }

        // Dividir en fragmentos y seleccionar estratégicamente
        $palabrasArray = explode(' ', $textoLimpio);
        $fragmentos = [];
        $chunkActual = [];
        $longitudActual = 0;
        $maxCaracteres = 1500;
        $palabrasSolapamiento = 40;

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

        $totalFragmentos = count($fragmentos);

        // Selección estratégica: inicio + medio + final
        $envio = [];
        $envio = array_merge($envio, array_slice($fragmentos, 0, 4));
        $mitad = (int) floor($totalFragmentos / 2);
        $envio = array_merge($envio, array_slice($fragmentos, $mitad - 2, 3));
        $tresCuartos = (int) floor($totalFragmentos * 0.75);
        $envio = array_merge($envio, array_slice($fragmentos, $tresCuartos - 2, 3));
        $envio = array_merge($envio, array_slice($fragmentos, -2));

        $contextoFinal = implode("\n\n", array_unique($envio));
        if (mb_strlen($contextoFinal) > 10000) {
            $contextoFinal = mb_substr($contextoFinal, 0, 10000);
        }

        return $contextoFinal;
    }

    /**
     * Patrones de preguntas genéricas/meta que NO deben generarse.
     */
    protected function esPreguntaGenerica(array $q): bool
    {
        $pregunta = strtolower($q['pregunta'] ?? '');

        $patrones = [
            '/d[oó]nde.*(almacen|guard|archivo|pdf|documento|file)/i',
            '/qu[eé] beneficio.*(almacen|guard|nube|cloud|archivo)/i',
            '/c[oó]mo se (guarda|almacena|crea|sube|almacen)/i',
            '/qu[eé] tipo de (archivo|documento|formato)/i',
            '/para qu[eé] sirve (el|la|este|esta) (archivo|documento|pdf|aplicaci[oó]n|app)/i',
            '/cu[aá]l es el (nombre|tama[ñn]o|formato) (del|de el|de la) (archivo|documento)/i',
            '/en qu[eé] (formato|idioma) est[aá]/i',
            '/qu[eé] (aplicaci[oó]n|app|programa|software) se usa/i',
            '/d[oó]nde est[aá] (guardado|almacenado|el archivo|el documento)/i',
            '/qu[eé] es (un|una) (pdf|archivo|documento)/i',
            '/cu[aá]l es el t[íi]tulo (del|de el) (documento|archivo|pdf)/i',
            '/cu[aá]ntas p[aá]ginas tiene/i',
            '/qu[eé] (contiene|incluye|tiene) el (documento|archivo|pdf)/i',
            '/c[oó]mo se llama el (documento|archivo|pdf)/i',
            '/d[oó]nde se (encuentra|localiza|ubica) (el|este) (archivo|documento)/i',
            '/cu[aá]l es la (fecha|fuente|referencia) (del|de) (documento|archivo)/i',
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $pregunta)) {
                return true;
            }
        }

        return false;
    }

    protected function sanitizarPreguntas(array $preguntas, int $limite): array
    {
        // Filtrar preguntas genéricas/meta
        $preguntas = array_values(array_filter($preguntas, fn($q) => !$this->esPreguntaGenerica($q)));

        // Cortar al número exacto pedido
        if (count($preguntas) > $limite) {
            $preguntas = array_slice($preguntas, 0, $limite);
        }

        $comodines = ["Todas las anteriores", "Ninguna de las anteriores", "Falta información", "No aplica"];

        foreach ($preguntas as &$q) {
            if (!isset($q['opciones']) || !is_array($q['opciones'])) {
                $q['opciones'] = ["Opción A", "Opción B", "Opción C", "Opción D", "Opción E"];
            }

            $c = 0;
            while (count($q['opciones']) < 5) {
                $q['opciones'][] = $comodines[$c] ?? "Otra opción";
                $c++;
            }

            if (count($q['opciones']) > 5) {
                $q['opciones'] = array_slice($q['opciones'], 0, 5);
            }

            if (!isset($q['correcta']) || !is_numeric($q['correcta']) || $q['correcta'] < 0 || $q['correcta'] > 4) {
                $q['correcta'] = 0;
            }
        }

        return $preguntas;
    }
}
