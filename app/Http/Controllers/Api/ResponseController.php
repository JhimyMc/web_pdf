<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\StudentResponse;
use Illuminate\Http\Request;

class ResponseController extends Controller
{
    // 4. Guardar la respuesta del estudiante
    public function store(Request $request, $code)
    {
        $request->validate([
            'student_name' => 'required|string',
            'question_index' => 'required|integer',
            'selected_option' => 'required|integer|min:0|max:4',
        ]);

        $room = Room::where('code', $code)->first();

        if (!$room || !$room->questions) {
            return response()->json(['error' => 'Sala no válida o la IA aún no genera las preguntas'], 400);
        }

        // Verificamos internamente si la opción es la correcta
        $questions = $room->questions;
        $isCorrect = false;

        if (isset($questions[$request->question_index])) {
            $correctOptionIndex = $questions[$request->question_index]['correcta'];
            $isCorrect = ($request->selected_option == $correctOptionIndex);
        }

        // Guardamos
        StudentResponse::create([
            'room_code' => $code,
            'student_name' => $request->student_name,
            'question_index' => $request->question_index,
            'selected_option' => $request->selected_option,
            'is_correct' => $isCorrect,
            'is_flagged' => false,
        ]);

        return response()->json([
            'message' => 'Respuesta registrada',
            'is_correct' => $isCorrect
        ]);
    }

    // 5. Bandera Anti-Trampa (Si el alumno cambia de pestaña en la web)
    public function flag(Request $request, $code)
    {
        $request->validate([
            'student_name' => 'required|string'
        ]);

        // Marcamos como sospechoso a este estudiante
        StudentResponse::where('room_code', $code)
            ->where('student_name', $request->student_name)
            ->update(['is_flagged' => true]);

        // Si no ha respondido nada, creamos un registro vacío con la bandera roja
        $exists = StudentResponse::where('room_code', $code)
            ->where('student_name', $request->student_name)->exists();

        if (!$exists) {
            StudentResponse::create([
                'room_code' => $code,
                'student_name' => $request->student_name,
                'question_index' => -1,
                'selected_option' => -1,
                'is_correct' => false,
                'is_flagged' => true,
            ]);
        }

        return response()->json(['message' => 'Alerta de trampa registrada']);
    }
}
