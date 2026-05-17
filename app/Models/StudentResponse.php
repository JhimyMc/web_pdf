<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_code',
        'student_name',
        'question_index',
        'selected_option',
        'is_correct',
        'is_flagged',
    ];

    // Le decimos a Laravel que estos campos son booleanos (true/false)
    protected $casts = [
        'is_correct' => 'boolean',
        'is_flagged' => 'boolean',
    ];

    // Relación: Una respuesta pertenece a una sala
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_code', 'code');
    }
}
