<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyCard extends Model
{
    protected $fillable = [
        'study_card_set_id',
        'front',
        'back',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(StudyCardSet::class, 'study_card_set_id');
    }
}
