<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\StudentResponse;

class Room extends Model
{
    use HasFactory;

    // Los campos que podemos llenar masivamente
    protected $fillable = [
        'user_id',
        'code',
        'is_individual',
        'pdf_name',
        'num_questions',
        'total_batches',
        'batches_completed',
        'difficulty',
        'questions',
        'status',
        'finished_at',
    ];

    // Le decimos a Laravel que 'questions' es un JSON en la BD pero aquí lo maneje como Array
    protected $casts = [
        'questions'   => 'array',
        'finished_at' => 'datetime',
    ];

    // Relación: Una sala tiene muchas respuestas de estudiantes
    public function responses()
    {
        return $this->hasMany(StudentResponse::class, 'room_code', 'code');
    }

    // Relación: Una sala pertenece a un usuario (Docente)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
