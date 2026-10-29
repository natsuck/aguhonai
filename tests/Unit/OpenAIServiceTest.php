<?php

use App\Exceptions\OpenAIQuotaExceededException;
use App\Exceptions\OpenAIRateLimitException;
use App\Exceptions\OpenAIRequestException;
use App\Services\OpenAIService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function assessmentRow(string $question, string $category, int $answer): array
{
    return [
        'question' => $question,
        'category' => $category,
        'category_label' => ucfirst($category),
        'answer' => $answer,
        'answer_label' => 'Agree',
    ];
}

function fakeOpenAIResponse(array|string $body): void
{
    $content = is_array($body) ? $body : $body;

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => $content]],
            ],
        ]),
    ]);
}

it('normalizes a valid structured ai recommendation', function () {
    fakeOpenAIResponse(json_encode([
        'recommended_program' => [
            'code' => 'bsit',
            'name' => 'Whatever the model said',
        ],
        'confidence' => '91',
        'fit_level' => 'Strong Fit',
        'reason' => 'Technical alignment.',
        'strengths' => ['Logic', 'Technology'],
        'alternative_programs' => [
            ['code' => 'BSCS', 'name' => 'CS', 'reason' => 'Related.'],
            ['code' => 'NOPE', 'name' => 'Invalid', 'reason' => 'Bad code.'],
        ],
    ]));

    $result = (new OpenAIService)->analyze([
        assessmentRow('I enjoy solving logical problems.', 'skills', 5),
        assessmentRow('I am interested in computer technology.', 'technical', 5),
    ]);

    expect($result['source'])->toBe('openai')
        ->and($result['recommended_program']['code'])->toBe('BSIT')
        ->and($result['recommended_program']['name'])
        ->toBe('Bachelor of Science in Information Technology')
        ->and($result['confidence'])->toBe(91)
        // Unknown alternative codes are replaced with a catalog entry
        ->and($result['alternative_programs'][1]['code'])->toBe('BSIT');
});

it('clamps out-of-range confidence values', function () {
    fakeOpenAIResponse(json_encode([
        'recommended_program' => ['code' => 'BSCS', 'name' => 'CS'],
        'confidence' => 150,
    ]));

    $result = (new OpenAIService)->analyze([
        assessmentRow('I enjoy solving logical problems.', 'skills', 5),
    ]);

    expect($result['confidence'])->toBe(100);
});

it('replaces an unknown program code with a deterministic trait match', function () {
    fakeOpenAIResponse(json_encode([
        'recommended_program' => ['code' => 'BSPHILO', 'name' => 'Not offered'],
        'confidence' => 80,
    ]));

    $result = (new OpenAIService)->analyze([
        assessmentRow('I enjoy solving logical problems.', 'skills', 5),
        assessmentRow('I am interested in computer technology.', 'technical', 5),
    ]);

    expect($result['recommended_program']['code'])->toBe('BSCS');
});

it('produces a marked fallback when the ai output is not json', function () {
    fakeOpenAIResponse('Sure! Here is your recommendation, dear student.');

    $result = (new OpenAIService)->analyze([
        assessmentRow('I enjoy helping people.', 'interest', 5),
        assessmentRow('I am interested in business management.', 'interest', 5),
    ]);

    expect($result['source'])->toBe('fallback')
        ->and($result['fit_level'])->toBe('Preliminary Fit')
        ->and($result['error'])->toContain('JSON object')
        ->and($result['disclaimer'])->toContain('guidance');
});

it('produces a marked fallback when the openai service fails', function () {
    config(['services.openai.rate_limit_base_delay' => 0]);

    Http::fake([
        'https://api.openai.com/*' => Http::response('server exploded', 500),
    ]);

    $result = (new OpenAIService)->analyze([
        assessmentRow('I enjoy solving logical problems.', 'skills', 5),
    ]);

    expect($result['source'])->toBe('fallback')
        ->and($result['error'])->toContain('500');
});

it('surfaces a specific connectivity message when DNS cannot resolve the OpenAI host', function () {
    config(['services.openai.rate_limit_base_delay' => 0]);

    Http::fake([
        'https://api.openai.com/*' => fn () => throw new ConnectionException(
            'cURL error 6: Could not resolve host: api.openai.com'
        ),
    ]);

    expect(fn () => (new OpenAIService)->generate('Generate a quiz.'))
        ->toThrow(OpenAIRequestException::class, 'Check internet/DNS connectivity');
});

it('retries a short rate limit and succeeds', function () {
    config([
        'services.openai.rate_limit_max_retries' => 2,
        'services.openai.rate_limit_base_delay' => 0,
        'services.openai.rate_limit_inline_max_wait' => 10,
    ]);
    Sleep::fake();

    Http::fakeSequence()
        ->push([
            'error' => ['code' => 'rate_limit_exceeded', 'message' => 'Rate limit reached.'],
        ], 429, ['Retry-After' => '1'])
        ->push([
            'choices' => [['message' => ['content' => 'Recovered after waiting.']]],
        ]);

    $result = (new OpenAIService)->generate('Generate a quiz.');

    expect($result)->toBe('Recovered after waiting.');
    Http::assertSentCount(2);
});

it('surfaces a rate limit exception after retries are exhausted', function () {
    config([
        'services.openai.rate_limit_max_retries' => 1,
        'services.openai.rate_limit_base_delay' => 0,
        'services.openai.rate_limit_inline_max_wait' => 10,
    ]);
    Sleep::fake();

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'error' => ['code' => 'rate_limit_exceeded', 'message' => 'Rate limit reached.'],
        ], 429, ['Retry-After' => '3']),
    ]);

    try {
        (new OpenAIService)->generate('Generate a quiz.');
        $this->fail('Expected a rate limit exception.');
    } catch (OpenAIRateLimitException $exception) {
        expect($exception->retryAfter)->toBe(3)
            ->and($exception->getMessage())->toContain('rate limit');
    }

    Http::assertSentCount(2);
});

it('does not retry an exhausted openai quota', function () {
    config(['services.openai.rate_limit_max_retries' => 3]);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'error' => [
                'code' => 'insufficient_quota',
                'message' => 'You exceeded your current quota, please check your plan and billing details.',
            ],
        ], 429),
    ]);

    expect(fn () => (new OpenAIService)->generate('Generate a quiz.'))
        ->toThrow(OpenAIQuotaExceededException::class);

    Http::assertSentCount(1);
});

it('stops retrying immediately when the server asks for a long wait', function () {
    config([
        'services.openai.rate_limit_max_retries' => 3,
        'services.openai.rate_limit_inline_max_wait' => 5,
    ]);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'error' => ['code' => 'rate_limit_exceeded', 'message' => 'Rate limit reached.'],
        ], 429, ['Retry-After' => '60']),
    ]);

    try {
        (new OpenAIService)->generate('Generate a quiz.');
        $this->fail('Expected a rate limit exception.');
    } catch (OpenAIRateLimitException $exception) {
        expect($exception->retryAfter)->toBe(60);
    }

    Http::assertSentCount(1);
});

it('parses the openai rate limit reset header', function () {
    config(['services.openai.rate_limit_max_retries' => 0]);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'error' => ['code' => 'rate_limit_exceeded', 'message' => 'Rate limit reached.'],
        ], 429, ['x-ratelimit-reset-requests' => '6m0s']),
    ]);

    try {
        (new OpenAIService)->generate('Generate a quiz.');
        $this->fail('Expected a rate limit exception.');
    } catch (OpenAIRateLimitException $exception) {
        expect($exception->retryAfter)->toBe(360);
    }
});

it('returns a profile explanation without AI-generated program rankings', function () {
    fakeOpenAIResponse(json_encode([
        'reason' => 'Your interest scores suggest several programs worth exploring.',
        'strengths' => ['Investigative interests'],
        'development_areas' => [],
        'feedback' => 'These are suggestions, and your choice is yours.',
        'evidence' => ['Investigative average: 4.2 / 5'],
        'next_steps' => ['Review program curricula with a counselor.'],
    ]));

    $result = (new OpenAIService)->analyzeAssessmentProfile([
        'interest' => [
            'I' => ['label' => 'Investigative', 'average' => 4.2, 'percentage' => 84, 'items' => 15],
            'C' => ['label' => 'Conventional', 'average' => 3.8, 'percentage' => 76, 'items' => 15],
            'S' => ['label' => 'Social', 'average' => 3.5, 'percentage' => 70, 'items' => 15],
        ],
        'aptitude' => [
            'verbal' => ['label' => 'Verbal', 'correct' => 14, 'total' => 20, 'percentage' => 70],
            'quantitative' => ['label' => 'Quantitative', 'correct' => 12, 'total' => 20, 'percentage' => 60],
            'analytical' => ['label' => 'Analytical', 'correct' => 16, 'total' => 20, 'percentage' => 80],
        ],
    ]);

    expect($result['source'])->toBe('openai')
        ->and($result['reason'])->toContain('scores suggest')
        ->and($result)->not->toHaveKey('recommended_program')
        ->and($result)->not->toHaveKey('program_matches');
});

it('ignores any AI-supplied program ranking data', function () {
    fakeOpenAIResponse(json_encode([
        'reason' => 'Profile summary.',
        'feedback' => 'Guidance only.',
        'program_matches' => [['code' => 'FAKE']],
        'recommended_program' => ['code' => 'FAKE', 'name' => 'Not offered'],
    ]));

    $result = (new OpenAIService)->analyzeAssessmentProfile([
        'interest' => [],
        'aptitude' => [],
    ]);

    expect($result['source'])->toBe('openai')
        ->and($result)->not->toHaveKey('program_matches')
        ->and($result)->not->toHaveKey('recommended_program');
});

it('maps technical-heavy patterns to computing programs and social-heavy to service programs', function () {
    $service = new OpenAIService;

    $method = new ReflectionMethod($service, 'fallbackProgramCode');

    $technicalCode = $method->invoke($service, [
        assessmentRow('I love computer systems and software.', 'technical', 5),
        assessmentRow('I like hands-on practical activities.', 'work_style', 5),
        assessmentRow('I enjoy applied real-world tasks.', 'work_style', 5),
    ]);

    $socialCode = $method->invoke($service, [
        assessmentRow('I enjoy communicating and helping people.', 'interest', 5),
        assessmentRow('I like planning business tasks.', 'interest', 5),
        assessmentRow('I enjoy interacting with people.', 'work_style', 5),
    ]);

    expect($technicalCode)->toBe('BSIT')
        ->and($socialCode)->toBe('BSHM');
});

it('still weighs reworded questions through category-level defaults', function () {
    $service = new OpenAIService;

    $method = new ReflectionMethod($service, 'fallbackProgramCode');

    $code = $method->invoke($service, [
        assessmentRow('Completely reworded item with zero keywords.', 'technical', 5),
        assessmentRow('Another unrecognizable statement here.', 'technical', 5),
    ]);

    expect($code)->toBe('BSCS');
});
