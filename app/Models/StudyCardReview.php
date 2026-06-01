<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyCardReview extends Model
{
    protected $fillable = [
        'user_id',
        'study_card_set_id',
        'card_index',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
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
