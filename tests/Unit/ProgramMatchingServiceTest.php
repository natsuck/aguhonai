<?php

use App\Models\ProgramProfile;
use App\Services\ProgramMatchingService;
use Database\Seeders\ProgramProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes profile distances and combines matches using the 60/40 formula', function () {
    ProgramProfile::query()->create([
        'program_code' => 'TEST',
        'program_name' => 'Test Program',
        'realistic_weight' => 3,
        'investigative_weight' => 5,
        'artistic_weight' => 2,
        'social_weight' => 2,
        'enterprising_weight' => 3,
        'conventional_weight' => 4,
        'verbal_target' => 75,
        'quantitative_target' => 100,
        'analytical_target' => 90,
        'is_active' => true,
    ]);

    $scores = [
        'interest' => [
            'R' => ['percentage' => 72],
            'I' => ['percentage' => 91],
            'A' => ['percentage' => 43],
            'S' => ['percentage' => 35],
            'E' => ['percentage' => 58],
            'C' => ['percentage' => 84],
        ],
        'aptitude' => [
            'verbal' => ['percentage' => 80],
            'quantitative' => ['percentage' => 90],
            'analytical' => ['percentage' => 85],
        ],
    ];

    $result = app(ProgramMatchingService::class)->match($scores);
    $recommendation = $result['recommendations'][0];

    expect($result['holland_code'])->toBe('ICR')
        ->and($result['riasec_scores'])->toBe([
            'R' => 72.0,
            'I' => 91.0,
            'A' => 43.0,
            'S' => 35.0,
            'E' => 58.0,
            'C' => 84.0,
        ])
        ->and($recommendation['riasec_match'])->toBe(92.71)
        ->and($recommendation['aptitude_match'])->toBe(94.81)
        ->and($recommendation['suitability_score'])->toBe(93.55)
        ->and($recommendation['match_level'])->toBe('High Match')
        ->and($recommendation['profile_snapshot']['realistic_weight'])->toBe(3)
        ->and($recommendation['strongest_interests'])->toBe([
            ['code' => 'I', 'name' => 'Investigative'],
        ])
        ->and($recommendation['explanation'])->toContain('Clearly differentiated RIASEC interests include Investigative')
        ->and($recommendation['explanation'])->toContain('Quantitative Reasoning')
        ->and($result['formula']['aptitude_match'])->toContain('average(min(student percentage / program minimum target, 1) × 100)')
        ->and($result['formula']['suitability_score'])->toBe('(RIASEC match × 0.60) + (aptitude match × 0.40)');
});

it('marks equal RIASEC scores as broad without claiming only two interests are strongest', function () {
    $this->seed(ProgramProfileSeeder::class);

    $scores = [
        'interest' => array_fill_keys(['R', 'I', 'A', 'S', 'E', 'C'], ['percentage' => 100]),
        'aptitude' => [
            'verbal' => ['percentage' => 80],
            'quantitative' => ['percentage' => 80],
            'analytical' => ['percentage' => 80],
        ],
    ];

    $result = app(ProgramMatchingService::class)->match($scores);
    $recommendations = $result['recommendations'];

    expect($result['holland_code'])->toBe('RIA')
        ->and($result['holland_profile_type'])->toBe('broad')
        ->and($result['holland_code_is_differentiated'])->toBeFalse()
        ->and($result['riasec_score_spread'])->toBe(0.0)
        ->and($result['strongest_interests'])->toBe([])
        ->and($recommendations)->toHaveCount(5)
        ->and(array_column($recommendations, 'rank'))->toBe([1, 2, 3, 4, 5])
        ->and(array_column($recommendations, 'program_code'))->toContain('BSAIS', 'BSIT', 'BSCS', 'BSTM', 'BSHM')
        ->and($recommendations[0]['explanation'])->toContain('No clearly dominant RIASEC interests')
        ->and(collect($recommendations)->pluck('suitability_score')->all())
        ->toBe(collect($recommendations)->pluck('suitability_score')->sortDesc()->values()->all());
});

it('returns the clearly differentiated top interests', function () {
    $this->seed(ProgramProfileSeeder::class);

    $scores = [
        'interest' => [
            'R' => ['percentage' => 95],
            'I' => ['percentage' => 90],
            'A' => ['percentage' => 75],
            'S' => ['percentage' => 60],
            'E' => ['percentage' => 50],
            'C' => ['percentage' => 45],
        ],
        'aptitude' => [
            'verbal' => ['percentage' => 80],
            'quantitative' => ['percentage' => 80],
            'analytical' => ['percentage' => 80],
        ],
    ];

    $result = app(ProgramMatchingService::class)->match($scores);

    expect($result['holland_code'])->toBe('RIA')
        ->and($result['holland_profile_type'])->toBe('differentiated')
        ->and($result['holland_code_is_differentiated'])->toBeTrue()
        ->and($result['strongest_interests'])->toBe([
            ['code' => 'R', 'name' => 'Realistic'],
            ['code' => 'I', 'name' => 'Investigative'],
        ]);
});

it('includes all closely tied top interests in a differentiated profile', function () {
    $this->seed(ProgramProfileSeeder::class);

    $scores = [
        'interest' => [
            'R' => ['percentage' => 95],
            'I' => ['percentage' => 94],
            'A' => ['percentage' => 94],
            'S' => ['percentage' => 60],
            'E' => ['percentage' => 50],
            'C' => ['percentage' => 45],
        ],
        'aptitude' => [
            'verbal' => ['percentage' => 80],
            'quantitative' => ['percentage' => 80],
            'analytical' => ['percentage' => 80],
        ],
    ];

    $result = app(ProgramMatchingService::class)->match($scores);

    expect($result['holland_code'])->toBe('RIA')
        ->and($result['strongest_interests'])->toBe([
            ['code' => 'R', 'name' => 'Realistic'],
            ['code' => 'I', 'name' => 'Investigative'],
            ['code' => 'A', 'name' => 'Artistic'],
        ]);
});

it('caps aptitude attainment at target and averages below-target attainment', function () {
    $this->seed(ProgramProfileSeeder::class);

    $baseScores = [
        'interest' => [
            'R' => ['percentage' => 95],
            'I' => ['percentage' => 90],
            'A' => ['percentage' => 75],
            'S' => ['percentage' => 60],
            'E' => ['percentage' => 50],
            'C' => ['percentage' => 45],
        ],
        'aptitude' => [
            'verbal' => ['percentage' => 80],
            'quantitative' => ['percentage' => 95],
            'analytical' => ['percentage' => 100],
        ],
    ];

    $recommendations = collect(app(ProgramMatchingService::class)->match($baseScores)['recommendations'])
        ->keyBy('program_code');

    expect($recommendations['BSCS']['aptitude_match'])->toBe(100.0)
        ->and($recommendations['BSCS']['profile_snapshot']['verbal_target'])->toBe(70)
        ->and($recommendations['BSCS']['profile_snapshot']['quantitative_target'])->toBe(90)
        ->and($recommendations['BSCS']['profile_snapshot']['analytical_target'])->toBe(95);

    $baseScores['aptitude'] = [
        'verbal' => ['percentage' => 60],
        'quantitative' => ['percentage' => 80],
        'analytical' => ['percentage' => 85],
    ];
    $recommendations = collect(app(ProgramMatchingService::class)->match($baseScores)['recommendations'])
        ->keyBy('program_code');

    expect($recommendations['BSCS']['aptitude_match'])->toBe(88.03);

    $baseScores['aptitude'] = [
        'verbal' => ['percentage' => 70],
        'quantitative' => ['percentage' => 90],
        'analytical' => ['percentage' => 95],
    ];
    $recommendations = collect(app(ProgramMatchingService::class)->match($baseScores)['recommendations'])
        ->keyBy('program_code');

    expect($recommendations['BSCS']['aptitude_match'])->toBe(100.0);
});

it('breaks identical program-score ties by program code', function () {
    foreach (['ZZZ', 'AAA'] as $code) {
        ProgramProfile::query()->create([
            'program_code' => $code,
            'program_name' => $code.' Program',
            'realistic_weight' => 3,
            'investigative_weight' => 3,
            'artistic_weight' => 3,
            'social_weight' => 3,
            'enterprising_weight' => 3,
            'conventional_weight' => 3,
            'verbal_target' => 100,
            'quantitative_target' => 100,
            'analytical_target' => 100,
            'is_active' => true,
        ]);
    }

    $scores = [
        'interest' => array_fill_keys(['R', 'I', 'A', 'S', 'E', 'C'], ['percentage' => 60]),
        'aptitude' => [
            'verbal' => ['percentage' => 50],
            'quantitative' => ['percentage' => 50],
            'analytical' => ['percentage' => 50],
        ],
    ];

    $result = app(ProgramMatchingService::class)->match($scores);

    expect(array_column($result['recommendations'], 'program_code'))->toBe(['AAA', 'ZZZ']);
});

it('uses the documented high and moderate suitability boundaries', function () {
    foreach ([
        [
            'program_code' => 'HIGH',
            'realistic_weight' => 3,
            'investigative_weight' => 3,
            'artistic_weight' => 3,
            'social_weight' => 3,
            'enterprising_weight' => 3,
            'conventional_weight' => 3,
            'verbal_target' => 100,
            'quantitative_target' => 100,
            'analytical_target' => 100,
        ],
        [
            'program_code' => 'MOD',
            'realistic_weight' => 2,
            'investigative_weight' => 2,
            'artistic_weight' => 2,
            'social_weight' => 4,
            'enterprising_weight' => 4,
            'conventional_weight' => 4,
            'verbal_target' => 100,
            'quantitative_target' => 100,
            'analytical_target' => 100,
        ],
    ] as $profile) {
        ProgramProfile::query()->create([
            ...$profile,
            'program_name' => $profile['program_code'].' Program',
            'is_active' => true,
        ]);
    }

    $scores = [
        'interest' => array_fill_keys(['R', 'I', 'A', 'S', 'E', 'C'], ['percentage' => 60]),
        'aptitude' => [
            'verbal' => ['percentage' => 50],
            'quantitative' => ['percentage' => 50],
            'analytical' => ['percentage' => 50],
        ],
    ];

    $recommendations = collect(app(ProgramMatchingService::class)->match($scores)['recommendations'])
        ->keyBy('program_code');

    expect($recommendations['HIGH']['suitability_score'])->toBe(80.0)
        ->and($recommendations['HIGH']['match_level'])->toBe('High Match')
        ->and($recommendations['MOD']['suitability_score'])->toBe(65.0)
        ->and($recommendations['MOD']['match_level'])->toBe('Moderate Match');
});

it('rejects program aptitude targets outside the valid range', function () {
    ProgramProfile::query()->create([
        'program_code' => 'INVALID',
        'program_name' => 'Invalid Target Program',
        'realistic_weight' => 3,
        'investigative_weight' => 3,
        'artistic_weight' => 3,
        'social_weight' => 3,
        'enterprising_weight' => 3,
        'conventional_weight' => 3,
        'verbal_target' => 0,
        'quantitative_target' => 50,
        'analytical_target' => 50,
        'is_active' => true,
    ]);

    $scores = [
        'interest' => array_fill_keys(['R', 'I', 'A', 'S', 'E', 'C'], ['percentage' => 60]),
        'aptitude' => [
            'verbal' => ['percentage' => 50],
            'quantitative' => ['percentage' => 50],
            'analytical' => ['percentage' => 50],
        ],
    ];

    expect(fn () => app(ProgramMatchingService::class)->match($scores))
        ->toThrow(RuntimeException::class, 'expected a value from 1 to 100');
});

it('requires at least one active program profile', function () {
    $scores = [
        'interest' => array_fill_keys(['R', 'I', 'A', 'S', 'E', 'C'], ['percentage' => 60]),
        'aptitude' => [
            'verbal' => ['percentage' => 50],
            'quantitative' => ['percentage' => 50],
            'analytical' => ['percentage' => 50],
        ],
    ];

    expect(fn () => app(ProgramMatchingService::class)->match($scores))
        ->toThrow(RuntimeException::class, 'No active program profiles are configured');
});
