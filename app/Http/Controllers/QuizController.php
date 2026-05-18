<?php

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
        $documentos = Document::where('user_id', Auth::id())->latest()->get();
        return view('sala-configurar', compact('documentos'));
    }

    // 2. Procesar el formulario y crear la sala en la BD
    public function crearSala(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id',
            'num_questions' => 'required|integer|min:1|max:20',
            'difficulty' => 'required|in:basico,intermedio,avanzado'
        ]);

        $documento = Document::where('id', $request->document_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));

        $room = Room::create([
            'user_id' => Auth::id(),
            'code' => $code,
            'pdf_name' => $documento->nombre ?? 'Documento_PDF',
            'status' => 'configurando',
        ]);

        session(["room_config_{$code}" => [
            'document_id' => $request->document_id,
            'num_questions' => $request->num_questions,
            'difficulty' => $request->difficulty
        ]]);

        return redirect()->route('sala.dashboard', $code);
    }

    // 3. Mostrar el Panel de Control del Creador (Docente)
    public function dashboard($code)
    {
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
                'student_name' => $name,
                // MEJORA APLICADA: Puntos multiplicados por 1
                'score' => $respuestasReales->where('is_correct', true)->count() * 1,
                'is_flagged' => $items->contains('is_flagged', true),
                'answered_questions' => $respuestasReales->count()
            ];
        })->values();

        // Si el modelo no lo convierte automáticamente, lo forzamos a Array
        $questionsArray = is_string($room->questions) ? json_decode($room->questions, true) : $room->questions;

        return response()->json([
            'status' => $room->status,
            'questions' => $questionsArray,
            'students' => $students
        ]);
    }

    // 5. Mostrar la vista de juego para el participante (El Lobby con marcado de asistencia -1)
    public function play(Request $request, $code)
    {
        $room = Room::where('code', $code)->firstOrFail();

        // Capturamos el nombre que viene en la URL (?nombre=Juan)
        $nombre = $request->query('nombre', 'Participante');

        // REGISTRO DE ASISTENCIA EN TIEMPO REAL: 
        // Creamos una fila "falsa" con la pregunta -1 apenas entra al lobby 
        // para que el Dashboard del profesor lo vea conectado inmediatamente.
        StudentResponse::firstOrCreate(
            [
                'room_code' => $room->code,
                'student_name' => $nombre,
                'question_index' => -1
            ],
            [
                'selected_option' => -1,
                'is_correct' => false,
                'is_flagged' => false
            ]
        );

        return view('sala-play', compact('room', 'nombre'));
    }

    // 6. Almacenar o actualizar respuestas enviadas por los alumnos en tiempo real
    public function apiSaveResponse(Request $request)
    {
        $request->validate([
            'room_code' => 'required|string',
            'student_name' => 'required|string',
            'question_index' => 'required|integer',
            'selected_option' => 'required|integer',
            'is_correct' => 'required|boolean',
            'is_flagged' => 'required|boolean',
        ]);

        StudentResponse::updateOrCreate(
            [
                'room_code' => $request->room_code,
                'student_name' => $request->student_name,
                'question_index' => $request->question_index,
            ],
            [
                'selected_option' => $request->selected_option,
                'is_correct' => $request->is_correct,
                'is_flagged' => $request->is_flagged,
            ]
        );

        return response()->json(['success' => true]);
    }

    // 7. Disparar generación asíncrona enviando los CHUNKS reales del PDF
    public function apiGenerateQuestions($code)
    {
        $room = Room::where('code', $code)->firstOrFail();

        $config = session("room_config_{$code}") ?? [
            'num_questions' => 5,
            'difficulty' => 'intermedio',
            'document_id' => null
        ];

        // OBTENEMOS EL TEXTO DESDE TU TABLA REAL DE CHUNKS
        $textoPdf = "Texto no encontrado.";
        if (isset($config['document_id'])) {
            $chunks = \App\Models\DocumentChunk::where('document_id', $config['document_id'])
                ->orderBy('id', 'asc')
                ->limit(7) // Límite seguro para LLaMA 3
                ->pluck('chunk_text');

            if ($chunks->isNotEmpty()) {
                $textoPdf = $chunks->implode("\n\n");
            } else {
                $textoPdf = "El documento está vacío.";
            }
        }

        $n8nWebhookUrl = 'http://127.0.0.1:5678/webhook/playdf-examen-sala';

        try {
            $room->update(['status' => 'generando']);

            $response = Http::timeout(300)->post($n8nWebhookUrl, [
                'code' => $code,
                'pdf_name' => $room->pdf_name,
                'num_questions' => $config['num_questions'],
                'difficulty' => $config['difficulty'],
                'context' => $textoPdf
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $questionsArray = null;

                if (isset($data['questions'])) {
                    $questionsArray = is_string($data['questions']) ? json_decode($data['questions'], true) : $data['questions'];
                } elseif (isset($data['choices'][0]['message']['content'])) {
                    $questionsArray = json_decode($data['choices'][0]['message']['content'], true);
                }

                // ESCUDO PROTECTOR DE LARAVEL
                if (is_array($questionsArray)) {

                    // 1. CORTAR AL NÚMERO EXACTO: Si pidió 10 y llegaron 11, nos quedamos con 10.
                    if (count($questionsArray) > $config['num_questions']) {
                        $questionsArray = array_slice($questionsArray, 0, $config['num_questions']);
                    }

                    // 2. REPARAR OPCIONES INCOMPLETAS: Aseguramos que TODAS tengan exactamente 5 opciones
                    foreach ($questionsArray as &$q) {
                        if (!isset($q['opciones']) || !is_array($q['opciones'])) {
                            $q['opciones'] = ["Opción A", "Opción B", "Opción C", "Opción D", "Opción E"];
                        }

                        // Si la IA mandó menos de 5 opciones, rellenamos con comodines
                        $comodines = ["Todas las anteriores", "Ninguna de las anteriores", "Falta información", "No aplica"];
                        $c = 0;
                        while (count($q['opciones']) < 5) {
                            $q['opciones'][] = $comodines[$c] ?? "Otra opción";
                            $c++;
                        }

                        // Si mandó más de 5, cortamos
                        if (count($q['opciones']) > 5) {
                            $q['opciones'] = array_slice($q['opciones'], 0, 5);
                        }

                        // Aseguramos que la respuesta correcta no apunte a un número que no existe
                        if (!isset($q['correcta']) || !is_numeric($q['correcta']) || $q['correcta'] < 0 || $q['correcta'] > 4) {
                            $q['correcta'] = 0;
                        }
                    }

                    // Guardamos las preguntas limpias, recortadas y reparadas
                    $room->update([
                        'questions' => $questionsArray,
                        'status' => 'espera'
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

    // 8. Webhook de regreso (Cuando n8n termina de procesar y responde en background)
    public function apiWebhookN8n(Request $request)
    {
        $code = $request->input('code');
        $questionsRaw = $request->input('questions');

        $room = Room::where('code', $code)->first();

        if (!$room) {
            return response()->json(['error' => 'Sala no encontrada'], 404);
        }

        $parsedQuestions = null;

        // FILTRO ANTI-ERRORES DE LA IA:
        if (is_string($questionsRaw)) {
            if (preg_match('/\[.*\]/s', $questionsRaw, $matches)) {
                $parsedQuestions = json_decode($matches[0], true);
            } else {
                $parsedQuestions = json_decode($questionsRaw, true);
            }
        } else {
            $parsedQuestions = $questionsRaw;
        }

        // FALLBACK: Emergencia en caso de que falle el JSON
        if (!$parsedQuestions || !is_array($parsedQuestions)) {
            $parsedQuestions = [
                [
                    "pregunta" => "La IA tuvo un problema de formato al generar el examen. Por favor avísale al profesor.",
                    "opciones" => ["Aceptar", "B", "C", "D", "E"],
                    "correcta" => 0
                ]
            ];
        }

        $room->update([
            'questions' => $parsedQuestions,
            'status' => 'espera'
        ]);

        return response()->json(['success' => true, 'message' => 'Preguntas guardadas con éxito']);
    }

    // 9. Iniciar Sala (Docente presiona "Iniciar")
    public function apiStartRoom($code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $room->update(['status' => 'en_vivo']);
        return response()->json(['success' => true]);
    }

    // 10. Finalizar Sala (Docente presiona "Finalizar")
    public function apiEndRoom($code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $room->update(['status' => 'finalizado']);
        return response()->json(['success' => true]);
    }

    // 11. Cancelar / Eliminar Sala por completo
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
    // EXCLUSIVO PARA LA APP MÓVIL ANDROID (NO AFECTA LA WEB)
    // =========================================================================

    public function apiObtenerEstadoSala($code)
    {
        // Reutilizamos tu lógica exacta de apiGetStatus para que Android la entienda
        return $this->apiGetStatus($code);
    }

    public function apiGuardarRespuestaApp(Request $request)
    {
        // Reutilizamos tu lógica exacta de apiSaveResponse para Android
        return $this->apiSaveResponse($request);
    }

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
                'code' => $code,
                'pdf_name' => $room->pdf_name,
                'num_questions' => $request->num_questions,
                'difficulty' => $request->difficulty,
                'context' => substr($request->pdf_text, 0, 15000)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $questionsArray = null;

                if (isset($data['questions'])) {
                    $questionsArray = is_string($data['questions']) ? json_decode($data['questions'], true) : $data['questions'];
                } elseif (isset($data['choices'][0]['message']['content'])) {
                    $questionsArray = json_decode($data['choices'][0]['message']['content'], true);
                }

                if (is_array($questionsArray)) {
                    if (count($questionsArray) > $request->num_questions) {
                        $questionsArray = array_slice($questionsArray, 0, $request->num_questions);
                    }
                    foreach ($questionsArray as &$q) {
                        if (!isset($q['opciones']) || !is_array($q['opciones'])) {
                            $q['opciones'] = ["Opción A", "Opción B", "Opción C", "Opción D", "Opción E"];
                        }
                        $comodines = ["Todas las anteriores", "Ninguna de las anteriores", "Falta información", "No aplica"];
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
}
