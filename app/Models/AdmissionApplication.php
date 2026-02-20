<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplication extends Model
{
    protected $fillable = [
        'full_name',
        'age',
        'gender',
        'primary_course',
        'secondary_course',
        'email',
        'application_type',
        'valid_id_type',
        'facebook_account',
        'contact_no',
        'enrollment_purposes',
        'enrollment_purpose_others',
        'form_data',
        'id_picture_path',
        'one_by_one_picture_path',
        'right_thumbmark_path',
        'birth_certificate_path',
        'valid_id_path',
        'status',
        'remarks',
        'approved_at',
        'rejected_at',
        'processed_by',
        'created_user_id',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'enrollment_purposes' => 'array',
            'form_data' => 'array',
        ];
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }
}
