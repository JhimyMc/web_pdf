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
            'questions' => 'nullable|string',
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

        // La generación de preguntas ahora se maneja por separado
        // vía LM Studio directo (GenerateQuestionsBatch job)

        return response()->json([
            'message' => 'Sala creada exitosamente',
            'room_code' => $code
        ], 201);
    }

    // 2. Monitoreo (Android App y Web)
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
