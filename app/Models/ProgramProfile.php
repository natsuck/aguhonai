<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramProfile extends Model
{
    protected $fillable = [
        'program_code',
        'program_name',
        'realistic_weight',
        'investigative_weight',
        'artistic_weight',
        'social_weight',
        'enterprising_weight',
        'conventional_weight',
        'verbal_target',
        'quantitative_target',
        'analytical_target',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'realistic_weight' => 'integer',
            'investigative_weight' => 'integer',
            'artistic_weight' => 'integer',
            'social_weight' => 'integer',
            'enterprising_weight' => 'integer',
            'conventional_weight' => 'integer',
            'verbal_target' => 'integer',
            'quantitative_target' => 'integer',
            'analytical_target' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
