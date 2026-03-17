<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'scope',
        'title',
        'instructions',
        'pass_rate',
        'duration_minutes',
        'status',
        'quiz_type',
        'created_by_user_id',
        'offering_id',
        'course_program_id',
        'shuffle_items',
        'shuffle_choices',
        'published_at',
        'expires_at',
        'share_token',
        'invited_admission_ids',
        'invited_recipient_emails',
    ];

    protected function casts(): array
    {
        return [
            'pass_rate' => 'integer',
            'duration_minutes' => 'integer',
            'created_by_user_id' => 'integer',
            'offering_id' => 'integer',
            'course_program_id' => 'integer',
            'shuffle_items' => 'boolean',
            'shuffle_choices' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'invited_admission_ids' => 'array',
            'invited_recipient_emails' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuizItem::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
