<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id',
        'student_user_id',
        'student_admission_id',
        'student_name',
        'student_email',
        'started_at',
        'ends_at',
        'submitted_at',
        'auto_submitted',
        'score',
        'total',
        'correct_count',
        'wrong_count',
        'passed',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'quiz_id' => 'integer',
            'student_user_id' => 'integer',
            'student_admission_id' => 'integer',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'submitted_at' => 'datetime',
            'auto_submitted' => 'boolean',
            'score' => 'integer',
            'total' => 'integer',
            'correct_count' => 'integer',
            'wrong_count' => 'integer',
            'passed' => 'boolean',
            'context' => 'array',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class, 'attempt_id');
    }
}
