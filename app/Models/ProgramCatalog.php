<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramCatalog extends Model
{
    protected $fillable = [
        'type',
        'title',
        'slug',
        'category',
        'description',
        'duration',
        'credential_label',
        'badge_text',
        'icon_key',
        'theme_key',
        'sort_order',
        'is_limited_slots',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_limited_slots' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
