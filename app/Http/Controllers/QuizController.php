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

class QuizController extends Controller
{
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
            'status'    => $room->status,
            'questions' => $questionsArray,
            'students'  => $students
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
    }    // 7. Disparar generación asíncrona enviando los CHUNKS reales del PDF
    public function apiGenerateQuestions($code)
    {
        $room = Room::where('code', $code)->firstOrFail();

        // Usar num_questions desde el modelo Room (ya se guarda al crear)
        $numQuestions = $room->num_questions ?? 5;
        $difficulty   = $room->difficulty ?? 'intermedio';

        // Respaldo desde session por si el docente usó flujo anterior
        $config = session("room_config_{$code}");
        $documentId = $config['document_id'] ?? null;

        $textoPdf = "Texto no encontrado.";
        if ($documentId) {
            $chunks = \App\Models\DocumentChunk::where('document_id', $documentId)
                ->orderBy('id', 'asc')
                ->limit(7)
                ->pluck('chunk_text');

            $textoPdf = $chunks->isNotEmpty()
                ? $chunks->implode("\n\n")
                : "El documento está vacío.";
        }

        $n8nWebhookUrl = 'http://127.0.0.1:5678/webhook/playdf-examen-sala';

        try {
            $room->update(['status' => 'generando']);

            $response = Http::timeout(300)->post($n8nWebhookUrl, [
                'code'          => $code,
                'pdf_name'      => $room->pdf_name,
                'num_questions' => $numQuestions,
                'difficulty'    => $difficulty,
                'context'       => $textoPdf
            ]);

            if ($response->successful()) {
                $data          = $response->json();
                $questionsArray = null;

                if (isset($data['questions'])) {
                    $questionsArray = is_string($data['questions'])
                        ? json_decode($data['questions'], true)
                        : $data['questions'];
                } elseif (isset($data['choices'][0]['message']['content'])) {
                    $questionsArray = json_decode($data['choices'][0]['message']['content'], true);
                }

                if (is_array($questionsArray)) {
                    $questionsArray = $this->sanitizarPreguntas($questionsArray, $numQuestions);

                    $room->update([
                        'questions' => $questionsArray,
                        'status'    => 'espera'
                    ]);

                    return response()->json(['success' => true, 'status' => 'espera']);
                }

                return response()->json(['success' => true, 'status' => 'generando']);
            }

            return response()->json(['error' => 'n8n devolvió código de error: ' . $response->status()], 500);
        } catch (\Exception $e) {
            Log::error("Error en motor de IA n8n: " . $e->getMessage());
            return response()->json(['error' => 'Error de conexión con IA.', 'details' => $e->getMessage()], 500);
        }
    }

    // 8. Webhook de regreso (n8n termina en background)
    public function apiWebhookN8n(Request $request)
    {
        $code        = $request->input('code');
        $questionsRaw = $request->input('questions');

        $room = Room::where('code', $code)->first();

        if (!$room) {
            return response()->json(['error' => 'Sala no encontrada'], 404);
        }

        if (is_string($questionsRaw)) {
            if (preg_match('/\[.*\]/s', $questionsRaw, $matches)) {
                $parsedQuestions = json_decode($matches[0], true);
            } else {
                $parsedQuestions = json_decode($questionsRaw, true);
            }
        } else {
            $parsedQuestions = $questionsRaw;
        }

        if (!$parsedQuestions || !is_array($parsedQuestions)) {
            $parsedQuestions = [[
                "pregunta" => "La IA tuvo un problema de formato. Por favor avísale al profesor.",
                "opciones" => ["Aceptar", "B", "C", "D", "E"],
                "correcta" => 0
            ]];
        }

        // Sanitizar preguntas al límite real de la sala
        $parsedQuestions = $this->sanitizarPreguntas($parsedQuestions, $room->num_questions ?? 10);

        $room->update([
            'questions' => $parsedQuestions,
            'status'    => 'espera'
        ]);

        return response()->json(['success' => true, 'message' => 'Preguntas guardadas con éxito']);
    }

    // 9. Iniciar Sala
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
    // EXCLUSIVO PARA LA APP MÓVIL ANDROID
    // =========================================================================

    // Alias de apiGetStatus para la app
    public function apiObtenerEstadoSala($code)
    {
        return $this->apiGetStatus($code);
    }

    // Alias de apiSaveResponse para la app
    public function apiGuardarRespuestaApp(Request $request)
    {
        return $this->apiSaveResponse($request);
    }

    /**
     * POST /api/rooms/{code}/join
     *
     * ✅ NUEVO — Endpoint que llama Android cuando el alumno pulsa "Ingresar".
     * Recibe el código en el PATH (no en el body) para ser consistente con
     * la ruta registrada en api.php y con el método unirseASala de Retrofit.
     *
     * Body JSON: { "student_name": "Juan" }
     *
     * 200 → { "success": true,  "status": "espera"|"generando"|"en_vivo" }
     * 404 → { "error": "Sala no encontrada." }
     * 422 → { "error": "..." }
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

        // Registrar presencia del alumno usando el mismo patrón que `play()`
        // (question_index = -1 como "fila de asistencia") para que el dashboard
        // del docente lo vea conectado inmediatamente, igual que en la web.
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
     * POST /api/rooms/create-from-app
     * Crea una sala enviando el texto del PDF extraído en el móvil.
     */
    public function apiCrearSalaDesdeApp(Request $request)
    {
        try {
            $request->validate([
                'pdf_text'      => 'required|string',
                'pdf_name'      => 'required|string',
                'num_questions' => 'required|integer|min:1|max:20',
                'difficulty'    => 'required|in:basico,intermedio,avanzado'
            ]);

            $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));

            $room = Room::create([
                'user_id'       => Auth::id() ?? null,
                'code'          => $code,
                'pdf_name'      => $request->pdf_name,
                'num_questions' => $request->num_questions,
                'difficulty'    => $request->difficulty,
                'status'        => 'generando',
                'questions'     => []
            ]);

            $n8nWebhookUrl = 'http://127.0.0.1:5678/webhook/playdf-examen-sala';

            $response = Http::timeout(120)->post($n8nWebhookUrl, [
                'code'          => $code,
                'pdf_name'      => $room->pdf_name,
                'num_questions' => $request->num_questions,
                'difficulty'    => $request->difficulty,
                'context'       => substr($request->pdf_text, 0, 15000)
            ]);

            if ($response->successful()) {
                $data           = $response->json();
                $questionsArray = null;

                if (isset($data['questions'])) {
                    $questionsArray = is_string($data['questions'])
                        ? json_decode($data['questions'], true)
                        : $data['questions'];
                } elseif (isset($data['choices'][0]['message']['content'])) {
                    $questionsArray = json_decode($data['choices'][0]['message']['content'], true);
                }

                if (is_array($questionsArray)) {
                    $questionsArray = $this->sanitizarPreguntas($questionsArray, $request->num_questions);
                    $room->update(['questions' => $questionsArray, 'status' => 'espera']);
                }

                return response()->json(['success' => true, 'code' => $code], 201);
            }

            $room->update(['status' => 'configurando']);
            return response()->json(['success' => false, 'error' => 'El servidor n8n no respondió correctamente.'], 500);
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
            $documentos = \App\Models\Document::where('user_id', $userId)
                ->latest()
                ->get(['id', 'nombre', 'user_id']);

            return response()->json($documentos, 200);
        } catch (\Exception $e) {
            Log::error("Error en apiObtenerPdfsDocente: " . $e->getMessage());
            return response()->json(['error' => 'No se pudieron obtener los documentos'], 500);
        }
    }

    /**
     * POST /api/rooms/join  (ruta legacy del NetworkClient anterior)
     * Se conserva para no romper versiones viejas de la app.
     * Internamente redirige a apiJoinRoom.
     *
     * @deprecated Usar POST /api/rooms/{code}/join (apiJoinRoom)
     */
    public function apiUnirseSalaApp(Request $request)
    {
        $request->validate([
            'room_code'    => 'required|string',
            'student_name' => 'required|string'
        ]);

        // Reutiliza la nueva lógica, inyectando el código desde el body
        $request->merge(['student_name' => $request->student_name]);
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

    // =========================================================================
    // AUTO-EXPIRACIÓN DE SALAS
    // =========================================================================

    /**
     * Cancela salas que llevan más de 30 min sin iniciar el examen.
     * Estados expirables: configurando, generando, espera.
     */
    private function limpiarSalasExpiradas(): void
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
    private function cancelarSalasActivas(): void
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
     * Sanitiza y normaliza el array de preguntas recibido de la IA.
     * Extrae la lógica repetida en apiGenerateQuestions y apiCrearSalaDesdeApp.
     */
    private function sanitizarPreguntas(array $preguntas, int $limite): array
    {
        // Cortar al número exacto pedido
        if (count($preguntas) > $limite) {
            $preguntas = array_slice($preguntas, 0, $limite);
        }

        $comodines = ["Todas las anteriores", "Ninguna de las anteriores", "Falta información", "No aplica"];

        foreach ($preguntas as &$q) {
            // Asegurar que exista el array de opciones
            if (!isset($q['opciones']) || !is_array($q['opciones'])) {
                $q['opciones'] = ["Opción A", "Opción B", "Opción C", "Opción D", "Opción E"];
            }

            // Rellenar si hay menos de 5 opciones
            $c = 0;
            while (count($q['opciones']) < 5) {
                $q['opciones'][] = $comodines[$c] ?? "Otra opción";
                $c++;
            }

            // Recortar si hay más de 5
            if (count($q['opciones']) > 5) {
                $q['opciones'] = array_slice($q['opciones'], 0, 5);
            }

            // Asegurar índice de respuesta correcta válido
            if (!isset($q['correcta']) || !is_numeric($q['correcta']) || $q['correcta'] < 0 || $q['correcta'] > 4) {
                $q['correcta'] = 0;
            }
        }

        return $preguntas;
    }
}
