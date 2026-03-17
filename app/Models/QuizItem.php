<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizItem extends Model
{
    protected $fillable = [
        'quiz_id',
        'prompt',
        'choices',
        'correct_choice_id',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'quiz_id' => 'integer',
            'choices' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
