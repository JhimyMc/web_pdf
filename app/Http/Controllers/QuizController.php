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
    public function configurar()
    {
        $documentos = Document::where('user_id', Auth::id())->latest()->get();
        return view('sala-configurar', compact('documentos'));
    }

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

        // Traemos TODAS las interacciones de esta sala
        $responses = StudentResponse::where('room_code', $code)->get();

        // Agrupamos por nombre de estudiante
        $students = $responses->groupBy('student_name')->map(function ($items, $name) {

            // Filtramos las respuestas reales (quitamos el registro de asistencia que es -1)
            $respuestasReales = $items->where('question_index', '>=', 0);

            return [
                'student_name' => $name,
                // Multiplicamos por 20 puntos cada respuesta correcta para que coincida con la pantalla del alumno
                'score' => $respuestasReales->where('is_correct', true)->count() * 20,
                'is_flagged' => $items->contains('is_flagged', true),
                'answered_questions' => $respuestasReales->count()
            ];
        })->values();

        return response()->json([
            'status' => $room->status,
            'questions' => $room->questions,
            'students' => $students
        ]);
    }

    // (Aquí en medio van tus otros métodos como apiGenerateQuestions, apiStartRoom, etc...)

    // Mostrar la vista de juego para el participante (El Lobby)
    public function play(Request $request, $code)
    {
        $room = Room::where('code', $code)->firstOrFail();

        // Capturamos el nombre que viene en la URL (?nombre=Juan)
        $nombre = $request->query('nombre', 'Participante');

        // REGISTRO DE ASISTENCIA: 
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

    public function apiGenerateQuestions($code)
    {
        $room = Room::where('code', $code)->firstOrFail();

        $config = session("room_config_{$code}") ?? [
            'num_questions' => 5,
            'difficulty' => 'intermedio'
        ];

        // URL DE PRODUCCIÓN FIJA (Tu flujo en n8n debe estar en "Active")
        $n8nWebhookUrl = 'http://127.0.0.1:5678/webhook/playdf-examen-sala';

        try {
            $room->update(['status' => 'generando']);

            Log::info("=== LLAMANDO A N8N (MODO ACTIVO) ===");
            Log::info("URL destino: " . $n8nWebhookUrl);

            $response = Http::timeout(120)->post($n8nWebhookUrl, [
                'code' => $code,
                'pdf_name' => $room->pdf_name,
                'num_questions' => $config['num_questions'],
                'difficulty' => $config['difficulty']
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['questions'])) {
                    $questionsArray = is_string($data['questions']) ? json_decode($data['questions'], true) : $data['questions'];
                    $room->update([
                        'questions' => $questionsArray,
                        'status' => 'espera'
                    ]);
                    return response()->json(['success' => true, 'status' => 'espera']);
                }

                if (isset($data['choices'][0]['message']['content'])) {
                    $questionsArray = json_decode($data['choices'][0]['message']['content'], true);
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
            return response()->json([
                'error' => 'Error al intentar conectar con el motor de IA.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function apiWebhookN8n(Request $request)
    {
        $code = $request->input('code');
        $questions = $request->input('questions');

        $room = Room::where('code', $code)->first();

        if (!$room) {
            return response()->json(['error' => 'Sala no encontrada'], 404);
        }

        $parsedQuestions = is_string($questions) ? json_decode($questions, true) : $questions;

        $room->update([
            'questions' => $parsedQuestions,
            'status' => 'espera'
        ]);

        return response()->json(['success' => true, 'message' => 'Preguntas guardadas con éxito']);
    }

    public function apiStartRoom($code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $room->update(['status' => 'en_vivo']);
        return response()->json(['success' => true]);
    }

    public function apiEndRoom($code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $room->update(['status' => 'finalizado']);
        return response()->json(['success' => true]);
    }

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
}
