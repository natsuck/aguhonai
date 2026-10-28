<?php

namespace Database\Seeders;

use App\Models\ProgramProfile;
use Illuminate\Database\Seeder;

class ProgramProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'program_code' => 'BSAIS',
                'program_name' => 'BS Accounting Information System',
                'realistic_weight' => 2,
                'investigative_weight' => 4,
                'artistic_weight' => 1,
                'social_weight' => 2,
                'enterprising_weight' => 4,
                'conventional_weight' => 5,
                'verbal_target' => 75,
                'quantitative_target' => 85,
                'analytical_target' => 85,
            ],
            [
                'program_code' => 'BSIT',
                'program_name' => 'BS Information Technology',
                'realistic_weight' => 4,
                'investigative_weight' => 5,
                'artistic_weight' => 2,
                'social_weight' => 2,
                'enterprising_weight' => 2,
                'conventional_weight' => 4,
                'verbal_target' => 70,
                'quantitative_target' => 80,
                'analytical_target' => 90,
            ],
            [
                'program_code' => 'BSCS',
                'program_name' => 'BS Computer Science',
                'realistic_weight' => 3,
                'investigative_weight' => 5,
                'artistic_weight' => 2,
                'social_weight' => 1,
                'enterprising_weight' => 1,
                'conventional_weight' => 3,
                'verbal_target' => 70,
                'quantitative_target' => 90,
                'analytical_target' => 95,
            ],
            [
                'program_code' => 'BSTM',
                'program_name' => 'BS Tourism Management',
                'realistic_weight' => 1,
                'investigative_weight' => 2,
                'artistic_weight' => 4,
                'social_weight' => 5,
                'enterprising_weight' => 5,
                'conventional_weight' => 2,
                'verbal_target' => 85,
                'quantitative_target' => 65,
                'analytical_target' => 75,
            ],
            [
                'program_code' => 'BSHM',
                'program_name' => 'BS Hospitality Management',
                'realistic_weight' => 1,
                'investigative_weight' => 2,
                'artistic_weight' => 3,
                'social_weight' => 5,
                'enterprising_weight' => 5,
                'conventional_weight' => 3,
                'verbal_target' => 90,
                'quantitative_target' => 60,
                'analytical_target' => 70,
            ],
        ];

        foreach ($profiles as $profile) {
            ProgramProfile::query()->firstOrCreate(
                ['program_code' => $profile['program_code']],
                [...$profile, 'is_active' => true],
            );
        }
    }
}
