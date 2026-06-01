<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyCardSet extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'document_id',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(StudyCard::class, 'study_card_set_id');
    }
}
