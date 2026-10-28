<?php

namespace App\Services;

use App\Models\ProgramProfile;
use InvalidArgumentException;
use RuntimeException;

class ProgramMatchingService
{
    private const RIASEC_CODES = ['R', 'I', 'A', 'S', 'E', 'C'];

    private const RIASEC_DIFFERENTIATION_THRESHOLD = 10.0;

    private const TOP_SCORE_TIE_THRESHOLD = 5.0;

    private const RIASEC_WEIGHT = 0.60;

    private const APTITUDE_WEIGHT = 0.40;

    private const RIASEC_PROFILE_FIELDS = [
        'R' => 'realistic_weight',
        'I' => 'investigative_weight',
        'A' => 'artistic_weight',
        'S' => 'social_weight',
        'E' => 'enterprising_weight',
        'C' => 'conventional_weight',
    ];

    private const APTITUDE_PROFILE_FIELDS = [
        'verbal' => 'verbal_target',
        'quantitative' => 'quantitative_target',
        'analytical' => 'analytical_target',
    ];

    /**
     * @param  array<string, mixed>  $scoreBreakdown
     * @return array<string, mixed>
     */
    public function match(array $scoreBreakdown): array
    {
        $riasecScores = [];

        foreach (self::RIASEC_CODES as $code) {
            $percentage = $scoreBreakdown['interest'][$code]['percentage'] ?? null;
            if (! is_numeric($percentage)) {
                throw new InvalidArgumentException("Missing RIASEC percentage for {$code}.");
            }

            $riasecScores[$code] = (float) $percentage;
        }

        $aptitudeScores = [];
        foreach (self::APTITUDE_PROFILE_FIELDS as $section => $_field) {
            $percentage = $scoreBreakdown['aptitude'][$section]['percentage'] ?? null;
            if (! is_numeric($percentage)) {
                throw new InvalidArgumentException("Missing aptitude percentage for {$section}.");
            }

            $aptitudeScores[$section] = (float) $percentage;
        }

        $orderedRiasecCodes = collect(self::RIASEC_CODES)
            ->sort(function (string $left, string $right) use ($riasecScores): int {
                $scoreOrder = $riasecScores[$right] <=> $riasecScores[$left];

                return $scoreOrder !== 0
                    ? $scoreOrder
                    : array_search($left, self::RIASEC_CODES, true) <=> array_search($right, self::RIASEC_CODES, true);
            });
        $hollandCode = $orderedRiasecCodes->take(3)->implode('');
        $riasecSpread = max($riasecScores) - min($riasecScores);
        $hollandCodeIsDifferentiated = $riasecSpread >= self::RIASEC_DIFFERENTIATION_THRESHOLD;
        $hollandProfileType = $hollandCodeIsDifferentiated ? 'differentiated' : 'broad';

        $profiles = ProgramProfile::query()
            ->where('is_active', true)
            ->orderBy('program_code')
            ->get();

        if ($profiles->isEmpty()) {
            throw new RuntimeException('No active program profiles are configured. Run the program profile seeder.');
        }

        $interestNames = [
            'R' => 'Realistic',
            'I' => 'Investigative',
            'A' => 'Artistic',
            'S' => 'Social',
            'E' => 'Enterprising',
            'C' => 'Conventional',
        ];
        $strongestInterests = $hollandCodeIsDifferentiated
            ? $orderedRiasecCodes
                ->filter(fn (string $code): bool => max($riasecScores) - $riasecScores[$code] <= self::TOP_SCORE_TIE_THRESHOLD)
                ->map(fn (string $code): array => ['code' => $code, 'name' => $interestNames[$code]])
                ->values()
                ->all()
            : [];

        $strongestAptitudes = collect(array_keys($aptitudeScores))
            ->sort(function (string $left, string $right) use ($aptitudeScores): int {
                $scoreOrder = $aptitudeScores[$right] <=> $aptitudeScores[$left];

                return $scoreOrder !== 0
                    ? $scoreOrder
                    : array_search($left, array_keys(self::APTITUDE_PROFILE_FIELDS), true) <=> array_search($right, array_keys(self::APTITUDE_PROFILE_FIELDS), true);
            })
            ->filter(fn (string $section): bool => max($aptitudeScores) - $aptitudeScores[$section] <= self::TOP_SCORE_TIE_THRESHOLD)
            ->map(fn (string $section): array => [
                'code' => $section,
                'name' => [
                    'verbal' => 'Verbal Reasoning',
                    'quantitative' => 'Quantitative Reasoning',
                    'analytical' => 'Analytical Reasoning',
                ][$section],
            ])
            ->values()
            ->all();

        $recommendations = $profiles
            ->map(function (ProgramProfile $profile) use ($riasecScores, $aptitudeScores, $strongestInterests, $strongestAptitudes): array {
                $profileSnapshot = [];
                $riasecDistance = 0.0;

                foreach (self::RIASEC_PROFILE_FIELDS as $code => $field) {
                    $target = (int) $profile->{$field};
                    if ($target < 1 || $target > 5) {
                        throw new RuntimeException("Program profile {$profile->program_code} has an invalid {$field}; expected a value from 1 to 5.");
                    }

                    $profileSnapshot[$field] = $target;
                    $studentAverage = $riasecScores[$code] / 20;
                    $riasecDistance += abs($studentAverage - $target);
                }

                $aptitudeCompatibility = [];
                foreach (self::APTITUDE_PROFILE_FIELDS as $section => $field) {
                    $target = (int) $profile->{$field};
                    if ($target < 1 || $target > 100) {
                        throw new RuntimeException("Program profile {$profile->program_code} has an invalid {$field}; expected a value from 1 to 100.");
                    }

                    $profileSnapshot[$field] = $target;
                    $aptitudeCompatibility[] = min($aptitudeScores[$section] / $target, 1) * 100;
                }

                $riasecMatch = round((1 - ($riasecDistance / (count(self::RIASEC_CODES) * 4))) * 100, 2);
                $aptitudeMatch = round(array_sum($aptitudeCompatibility) / count($aptitudeCompatibility), 2);
                $suitabilityScore = round(($riasecMatch * self::RIASEC_WEIGHT) + ($aptitudeMatch * self::APTITUDE_WEIGHT), 2);
                $matchLevel = match (true) {
                    $suitabilityScore >= 80 => 'High Match',
                    $suitabilityScore >= 60 => 'Moderate Match',
                    default => 'Low Match',
                };
                $interestDescription = $strongestInterests === []
                    ? 'No clearly dominant RIASEC interests'
                    : 'Clearly differentiated RIASEC interests include '.implode(' and ', array_column($strongestInterests, 'name'));
                $aptitudeDescription = $strongestAptitudes === []
                    ? 'no highest aptitude areas were identified'
                    : 'highest aptitude areas are '.implode(' and ', array_column($strongestAptitudes, 'name'));

                return [
                    'program_code' => $profile->program_code,
                    'program_name' => $profile->program_name,
                    'riasec_match' => $riasecMatch,
                    'aptitude_match' => $aptitudeMatch,
                    'suitability_score' => $suitabilityScore,
                    'match_level' => $matchLevel,
                    'strongest_interests' => $strongestInterests,
                    'strongest_aptitudes' => $strongestAptitudes,
                    'explanation' => sprintf(
                        '%s, and %s. Compared with the initial %s profile, these scores produce a Program Suitability Score of %s.',
                        $interestDescription,
                        $aptitudeDescription,
                        $profile->program_code,
                        number_format($suitabilityScore, 2),
                    ),
                    'profile_snapshot' => $profileSnapshot,
                ];
            })
            ->sort(function (array $left, array $right): int {
                $scoreOrder = $right['suitability_score'] <=> $left['suitability_score'];

                return $scoreOrder !== 0
                    ? $scoreOrder
                    : strcmp($left['program_code'], $right['program_code']);
            })
            ->values()
            ->map(fn (array $recommendation, int $index): array => [
                ...$recommendation,
                'rank' => $index + 1,
            ])
            ->all();

        return [
            'holland_code' => $hollandCode,
            'holland_profile_type' => $hollandProfileType,
            'holland_code_is_differentiated' => $hollandCodeIsDifferentiated,
            'riasec_score_spread' => $riasecSpread,
            'strongest_interests' => $strongestInterests,
            'riasec_scores' => $riasecScores,
            'aptitude_scores' => $aptitudeScores,
            'recommendations' => $recommendations,
            'formula' => [
                'riasec_match' => '100 × (1 − sum(abs(student average − profile weight)) / 24)',
                'aptitude_match' => 'average(min(student percentage / program minimum target, 1) × 100)',
                'suitability_score' => sprintf('(RIASEC match × %.2f) + (aptitude match × %.2f)', self::RIASEC_WEIGHT, self::APTITUDE_WEIGHT),
            ],
            'profile_status' => 'Initial proposed profiles; not expert-validated.',
        ];
    }
}
