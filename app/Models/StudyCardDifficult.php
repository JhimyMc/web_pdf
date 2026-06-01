<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyCardDifficult extends Model
{
    protected $table = 'study_card_difficult';

    protected $fillable = [
        'user_id',
        'study_card_set_id',
        'card_index',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyCardSet(): BelongsTo
    {
        return $this->belongsTo(StudyCardSet::class);
    }
}
