<?php
// C:\laragon\www\web-pdf\app\Models\MindMap.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MindMap extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'prompt_original',
        'map_data',
        'status',
    ];

    protected $casts = [
        'map_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
