<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id',
        'quiz_item_id',
        'selected_choice_id',
        'selected_choice_text',
        'correct_choice_id',
        'correct_choice_text',
        'is_correct',
    ];

    protected function casts(): array
    {
        return [
            'attempt_id' => 'integer',
            'quiz_item_id' => 'integer',
            'is_correct' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(QuizItem::class, 'quiz_item_id');
    }
}
