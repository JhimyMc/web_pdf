<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SrsCard extends Model
{
    protected $table = 'srs_cards';

    protected $fillable = [
        'user_id',
        'study_card_set_id',
        'study_card_id',
        'card_index',
        'ease_factor',
        'interval_days',
        'repetitions',
        'next_review_at',
        'last_reviewed_at',
    ];

    protected $casts = [
        'ease_factor'      => 'float',
        'interval_days'    => 'integer',
        'repetitions'      => 'integer',
        'next_review_at'   => 'datetime',
        'last_reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyCardSet(): BelongsTo
    {
        return $this->belongsTo(StudyCardSet::class);
    }

    public function studyCard(): BelongsTo
    {
        return $this->belongsTo(StudyCard::class);
    }
}
