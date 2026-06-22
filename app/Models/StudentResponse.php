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
        'is_kicked',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'is_flagged' => 'boolean',
        'is_kicked' => 'boolean',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_code', 'code');
    }
}
