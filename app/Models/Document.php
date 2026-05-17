<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    // Campos que permitimos guardar desde el DocumentController
    protected $fillable = [
        'user_id',
        'name',
        'extracted_text',
        'tokens_estimated',
    ];

    /**
     * Relación: Un documento pertenece a un usuario/docente
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class)->oldest();
    }
}
