<?php
//C:\laragon\www\web-pdf\app\Http\Controllers\Api\RoomController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\StudentResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class RoomController extends Controller
{
    // 1. Docente crea la sala (Llamado desde Android y Web)
    public function create(Request $request)
    {
        // Validamos lo que llega de la App (Retrofit)
        $request->validate([
            'pdf_name' => 'nullable|string',
            'questions' => 'nullable|string', // Por ahora lo recibimos, aunque usaremos n8n luego
        ]);

        // Generar código único de 5 caracteres en mayúsculas
        $code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));

        // Creamos la sala. Si el docente está logueado por Sanctum, toma su ID. Si no, queda en null.
        $room = Room::create([
            'user_id' => auth('sanctum')->id(),
            'code' => $code,
            'pdf_name' => $request->input('pdf_name', 'Documento_PDF'),
            'status' => 'generando',
        ]);

        // ----- CONEXIÓN CON n8n (Fase 3) -----
        // Aquí le enviamos el texto extraído a n8n. 
        // Descomenta y cambia la URL cuando tu flujo de n8n esté listo.
        /*
        $webhookUrl = 'https://TU_URL_DE_N8N/webhook/generar-examen';
        try {
            Http::timeout(3)->post($webhookUrl, [
                'room_code' => $code,
                'text' => $request->input('questions', '') // El texto del PDF
            ]);
        } catch (\Exception $e) {
            Log::error("Error al contactar a n8n: " . $e->getMessage());
        }
        */

        return response()->json([
            'message' => 'Sala creada exitosamente',
            'room_code' => $code
        ], 201);
    }

    // 2. Webhook que recibe las preguntas desde n8n
    public function updateQuestions(Request $request)
    {
        $request->validate([
            'room_code' => 'required|string',
            'questions' => 'required|array'
        ]);

        $room = Room::where('code', $request->room_code)->first();

        if (!$room) {
            return response()->json(['error' => 'Sala no encontrada'], 404);
        }

        $room->update([
            'questions' => $request->questions,
            'status' => 'espera' // ¡Listo para que entren los alumnos!
        ]);

        return response()->json(['message' => 'Preguntas generadas por IA guardadas correctamente']);
    }

    // 3. Monitoreo (Android App y Web)
    public function getStatus($code)
    {
        $room = Room::where('code', $code)->first();

        if (!$room) {
            return response()->json(['error' => 'Sala no encontrada'], 404);
        }

        // Obtener respuestas de los estudiantes
        $responses = StudentResponse::where('room_code', $code)->get();

        // Agrupamos por estudiante para enviarlo bonito a la tabla de Android y Web
        $students = $responses->groupBy('student_name')->map(function ($items, $name) {
            return [
                'student_name' => $name,
                'score' => $items->where('is_correct', true)->count(),
                'is_flagged' => $items->contains('is_flagged', true),
                'answered_questions' => $items->count()
            ];
        })->values();

        return response()->json([
            'status' => $room->status,
            'questions' => $room->questions,
            'students' => $students,
            'responses' => $students // Para compatibilidad con tu código actual de Android
        ]);
    }
}
