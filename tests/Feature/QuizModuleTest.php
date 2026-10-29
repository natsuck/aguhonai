<?php

use App\Jobs\GenerateQuizFromMaterial;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\AnswerEvaluationService;
use App\Services\QuizGeneratorService;
use App\Services\TextExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('queues quiz generation from an uploaded learning material', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent(
        'networking.txt',
        str_repeat('Networking connects devices and allows data exchange across systems. ', 20)
    );

    $response = $this->actingAs($user)->post(route('quizzes.store'), [
        'title' => 'Networking Basics',
        'question_count' => 10,
        'material' => $file,
    ]);

    $quiz = Quiz::query()->first();

    $response->assertRedirect(route('quizzes.show', $quiz));
    expect($quiz)
        ->not->toBeNull()
        ->user_id->toBe($user->id)
        ->title->toBe('Networking Basics')
        ->requested_question_count->toBe(10)
        ->status->toBe(Quiz::STATUS_QUEUED);

    Storage::disk('local')->assertExists($quiz->source_file);
    Queue::assertPushed(GenerateQuizFromMaterial::class);
});

it('allows only the supported quiz question counts', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('quizzes.create'))
        ->assertOk()
        ->assertSee('10 questions')
        ->assertSee('15 questions')
        ->assertSee('20 questions')
        ->assertSee('30 questions')
        ->assertDontSee('25 questions');

    foreach ([5, 25, 31] as $invalidCount) {
        $this->from(route('quizzes.create'))->post(route('quizzes.store'), [
            'question_count' => $invalidCount,
            'material' => UploadedFile::fake()->createWithContent(
                'networking.txt',
                str_repeat('Networking connects devices and allows data exchange across systems. ', 20)
            ),
        ])->assertSessionHasErrors('question_count');
    }

    expect(Quiz::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('stores quiz source material on the configured default filesystem disk', function () {
    Storage::fake('s3');
    Queue::fake();
    config(['filesystems.default' => 's3']);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->post(route('quizzes.store'), [
        'title' => 'Networking Basics',
        'material' => UploadedFile::fake()->createWithContent(
            'networking.txt',
            str_repeat('Networking connects devices and allows data exchange across systems. ', 20)
        ),
    ]);

    $quiz = Quiz::query()->firstOrFail();

    $response->assertRedirect(route('quizzes.show', $quiz));
    Storage::disk('s3')->assertExists($quiz->source_file);
});

it('explains that queued quiz generation needs a queue worker and exposes its status', function () {
    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Networking Basics',
        'source_file' => 'uploads/networking.txt',
        'status' => Quiz::STATUS_QUEUED,
    ]);

    $this->actingAs($user)
        ->get(route('quizzes.show', $quiz))
        ->assertOk()
        ->assertSee('Waiting for a queue worker')
        ->assertSee('still marked queued after a minute')
        ->assertSee('same database as the app')
        ->assertSee('data-created-at', false);

    $this->get(route('quizzes.status', $quiz))
        ->assertOk()
        ->assertJsonPath('status', Quiz::STATUS_QUEUED)
        ->assertJsonPath('ready', false);
});

it('does not show AI confidence on quiz list or detail pages', function () {
    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Networking Basics',
        'source_file' => 'uploads/networking.txt',
        'status' => Quiz::STATUS_READY,
        'ai_confidence' => 87.5,
    ]);

    $this->actingAs($user)
        ->get(route('quizzes.index'))
        ->assertOk()
        ->assertSee('Quiz Generator')
        ->assertDontSee('AI Quizzes')
        ->assertDontSee('Confidence')
        ->assertDontSee('87.5%');

    $this->get(route('quizzes.show', $quiz))
        ->assertOk()
        ->assertDontSee('Confidence')
        ->assertDontSee('87.5%');
});

it('limits quiz generation across uploads and retries per user', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $upload = fn () => $this->actingAs($user)->post(route('quizzes.store'), [
        'title' => 'Networking Basics',
        'material' => UploadedFile::fake()->createWithContent(
            'networking.txt',
            str_repeat('Networking connects devices and allows data exchange across systems. ', 20)
        ),
    ]);

    foreach (range(1, 4) as $_) {
        $upload()->assertRedirect();
    }

    $failedQuiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Failed Quiz',
        'source_file' => 'uploads/failed.txt',
        'status' => Quiz::STATUS_FAILED,
    ]);

    $this->actingAs($user)->post(route('quizzes.retry', $failedQuiz))->assertRedirect();
    $upload()->assertTooManyRequests();
});

it('limits quiz generation by IP across user accounts', function () {
    Storage::fake('local');
    Queue::fake();

    foreach (range(1, 20) as $_) {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('quizzes.store'), [
            'title' => 'Networking Basics',
            'material' => UploadedFile::fake()->createWithContent(
                'networking.txt',
                str_repeat('Networking connects devices and allows data exchange across systems. ', 20)
            ),
        ])->assertRedirect();
    }

    $user = User::factory()->create();
    $this->actingAs($user)->post(route('quizzes.store'), [
        'title' => 'Networking Basics',
        'material' => UploadedFile::fake()->createWithContent(
            'networking.txt',
            str_repeat('Networking connects devices and allows data exchange across systems. ', 20)
        ),
    ])->assertTooManyRequests();
});

it('only allows failed quizzes to be retried', function () {
    Queue::fake();

    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Ready Quiz',
        'source_file' => 'uploads/ready.txt',
        'status' => Quiz::STATUS_READY,
    ]);

    $this->actingAs($user)
        ->post(route('quizzes.retry', $quiz))
        ->assertRedirect(route('quizzes.show', $quiz))
        ->assertSessionHas('status', 'Only failed quizzes can be retried.');

    expect($quiz->fresh()->status)->toBe(Quiz::STATUS_READY);
    Queue::assertNothingPushed();
});

it('saves generated mcqs and flashcards separately and makes the quiz ready to attempt', function () {
    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Software Quality',
        'source_file' => 'uploads/quality.txt',
        'requested_question_count' => 10,
        'status' => Quiz::STATUS_QUEUED,
    ]);

    $generated = [
        'confidence' => 90,
        'mcqs' => array_map(fn (int $index): array => [
            'question' => 'Question '.$index,
            'choices' => ['Correct', 'Option B', 'Option C', 'Option D'],
            'answer' => 'Correct',
            'explanation' => 'This is supported by the source.',
        ], range(1, 10)),
        'flashcards' => array_map(fn (int $index): array => [
            'front' => 'Front '.$index,
            'back' => 'Back '.$index,
        ], range(1, 10)),
    ];

    $extractor = new class extends TextExtractionService
    {
        public function extract(string $storedPath): string
        {
            return 'Extracted source material for quiz generation.';
        }
    };
    $generator = new class($generated) extends QuizGeneratorService
    {
        public function __construct(private readonly array $generated) {}

        public function generate(string $documentText, ?int $questionCount = null): array
        {
            expect($questionCount)->toBe(10);

            return $this->generated;
        }
    };

    (new GenerateQuizFromMaterial($quiz->id))->handle($extractor, $generator);

    $quiz->refresh();

    expect($quiz->status)->toBe(Quiz::STATUS_READY)
        ->and($quiz->questions)->toHaveCount(10)
        ->and($quiz->questions->every(fn (QuizQuestion $question) => $question->isMcq() && $question->explanation !== null))
        ->toBeTrue()
        ->and($quiz->flashcards)->toHaveCount(10);
});

it('allows quiz generation retries and keeps its timeout aligned with config', function () {
    config(['services.openai.quiz_job_timeout' => 900]);

    $job = new GenerateQuizFromMaterial(1);

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(900)
        ->and($job->backoff)->toBe([10, 30, 60])
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout);
});

it('clears stale quiz items and stores a student-safe error after generation failure', function () {
    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Game Programming',
        'source_file' => 'uploads/game-programming.txt',
        'extracted_text' => 'Previously extracted material.',
        'status' => Quiz::STATUS_READY,
        'ai_confidence' => 80,
        'generated_at' => now(),
    ]);
    $quiz->questions()->create([
        'type' => QuizQuestion::TYPE_MCQ,
        'question' => 'Old question?',
        'choices' => ['A', 'B', 'C', 'D'],
        'correct_answer' => 'A',
    ]);
    $quiz->flashcards()->create(['front' => 'Old front?', 'back' => 'Old back.']);

    $job = new GenerateQuizFromMaterial($quiz->id);
    $job->failed(new RuntimeException('provider secret response details'));

    $quiz->refresh();
    expect($quiz->status)->toBe(Quiz::STATUS_FAILED)
        ->and($quiz->generation_error)->toContain('reliable quiz could not be verified')
        ->and($quiz->generation_error)->not->toContain('provider secret')
        ->and($quiz->extracted_text)->toBeNull()
        ->and($quiz->ai_confidence)->toBeNull()
        ->and($quiz->generated_at)->toBeNull()
        ->and($quiz->questions()->count())->toBe(0)
        ->and($quiz->flashcards()->count())->toBe(0);
});

it('allows only the owner to download the uploaded learning material', function () {
    Storage::fake('s3');
    config(['filesystems.default' => 's3']);
    Storage::disk('s3')->put('uploads/material.txt', 'Sample learning material content.');

    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $quiz = Quiz::query()->create([
        'user_id' => $owner->id,
        'title' => 'Material Quiz',
        'source_file' => 'uploads/material.txt',
        'original_filename' => 'material.txt',
        'status' => Quiz::STATUS_READY,
    ]);

    $this->actingAs($intruder)->get(route('quizzes.material', $quiz))->assertForbidden();

    $this->actingAs($owner)->get(route('quizzes.material', $quiz))
        ->assertOk()
        ->assertDownload('material.txt');
});

it('deletes quiz source material from the configured upload disk', function () {
    Storage::fake('s3');
    config(['filesystems.default' => 's3']);
    Storage::disk('s3')->put('uploads/material.txt', 'Sample learning material content.');

    $owner = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $owner->id,
        'title' => 'Material Quiz',
        'source_file' => 'uploads/material.txt',
        'status' => Quiz::STATUS_READY,
    ]);

    $this->actingAs($owner)
        ->delete(route('quizzes.destroy', $quiz))
        ->assertRedirect(route('quizzes.index'));

    Storage::disk('s3')->assertMissing('uploads/material.txt');
    expect(Quiz::query()->find($quiz->id))->toBeNull();
});

it('scores quiz attempts with automatic mcq and semantic identification checking', function () {
    $user = User::factory()->create();

    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Computer Fundamentals',
        'source_file' => 'uploads/computer.txt',
        'status' => Quiz::STATUS_READY,
    ]);

    $mcq = $quiz->questions()->create([
        'type' => QuizQuestion::TYPE_MCQ,
        'question' => 'What does CPU stand for?',
        'choices' => ['Central Processing Unit', 'Computer Personal Unit', 'Central Program Utility', 'Control Processing User'],
        'correct_answer' => 'Central Processing Unit',
        'explanation' => 'The CPU is the computer component that executes instructions.',
    ]);

    $identification = $quiz->questions()->create([
        'type' => QuizQuestion::TYPE_IDENTIFICATION,
        'question' => 'Identify the main processing component of a computer.',
        'correct_answer' => 'Central Processing Unit',
    ]);

    $this->app->bind(AnswerEvaluationService::class, fn () => new class extends AnswerEvaluationService
    {
        public function __construct() {}

        public function evaluate(string $question, string $correctAnswer, ?string $studentAnswer): array
        {
            return [
                'is_correct' => str_contains(strtolower((string) $studentAnswer), 'central processing unit'),
                'confidence' => 92.0,
                'explanation' => 'The student answer is semantically equivalent.',
            ];
        }

        public function evaluateBatch(array $items): array
        {
            return collect($items)
                ->mapWithKeys(fn (array $item): array => [
                    $item['key'] => [
                        'is_correct' => str_contains(strtolower((string) ($item['student_answer'] ?? '')), 'central processing unit'),
                        'confidence' => 92.0,
                        'explanation' => 'The student answer is semantically equivalent.',
                    ],
                ])
                ->all();
        }
    });

    $this->actingAs($user)->get(route('quizzes.take', $quiz))->assertOk();

    $response = $this->actingAs($user)->post(route('quizzes.attempts.store', $quiz), [
        'answers' => [
            $mcq->id => 'Central Processing Unit',
            $identification->id => 'CPU means central processing unit',
        ],
    ]);

    $attempt = QuizAttempt::query()->first();

    $response->assertRedirect(route('quiz-attempts.show', $attempt));
    expect((float) $attempt->score)->toBe(100.0)
        ->and($attempt->correct_count)->toBe(2)
        ->and($attempt->mcq_score)->toBe(1)
        ->and($attempt->identification_score)->toBe(1)
        ->and($attempt->answers()->where('question_id', $mcq->id)->value('ai_explanation'))
        ->toBe('The CPU is the computer component that executes instructions.')
        ->and($attempt->feedback)->toContain('MCQ correct: 1/1. Identification correct: 1/1.');

    $this->get(route('quiz-attempts.show', $attempt))
        ->assertOk()
        ->assertDontSee('Confidence')
        ->assertDontSee('92%');
});

it('requires an attempt before question review and reveals answers after the first attempt', function () {
    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Software Quality',
        'source_file' => 'uploads/quality.txt',
        'status' => Quiz::STATUS_READY,
    ]);
    $quiz->questions()->create([
        'type' => QuizQuestion::TYPE_MCQ,
        'question' => 'Which activity detects software errors?',
        'choices' => ['Review', 'Marketing', 'Hiring', 'Budgeting'],
        'correct_answer' => 'Review',
        'explanation' => 'Reviews are appraisal activities that detect errors.',
    ]);
    $quiz->flashcards()->create([
        'front' => 'What are appraisal costs?',
        'back' => 'Costs of activities performed to detect software errors.',
    ]);

    $this->actingAs($user)->get(route('quizzes.show', $quiz))
        ->assertOk()
        ->assertSee('Take the quiz before reviewing answers')
        ->assertDontSee('Which activity detects software errors?')
        ->assertDontSee('Answer key:')
        ->assertDontSee('Reviews are appraisal activities');

    $this->actingAs($user)->get(route('quizzes.take', $quiz))
        ->assertOk()
        ->assertDontSee('Answer key:')
        ->assertDontSee('Reviews are appraisal activities');

    $this->actingAs($user)->get(route('quizzes.flashcards', $quiz))
        ->assertOk()
        ->assertSee('Study mode')
        ->assertSee('Not graded')
        ->assertSee('Reveal answer');

    $this->actingAs($user)->get(route('quizzes.take', $quiz))->assertOk();
    $firstAttempt = $this->actingAs($user)->post(route('quizzes.attempts.store', $quiz), [
        'answers' => [$quiz->questions->first()->id => 'Marketing'],
    ]);
    $firstAttempt->assertRedirect(route('quiz-attempts.show', QuizAttempt::query()->firstOrFail()));

    $this->actingAs($user)->get($firstAttempt->headers->get('Location'))
        ->assertOk()
        ->assertSee('>Correct Answer</p>', false)
        ->assertSee('bg-rose-100 text-rose-700')
        ->assertSee('Reviews are appraisal activities');

    $this->actingAs($user)->get(route('quizzes.show', $quiz))
        ->assertOk()
        ->assertSee('Study Flashcards')
        ->assertSee('Answer key:')
        ->assertSee('Reviews are appraisal activities');

    $this->actingAs($user)->get('/quiz-analytics')->assertNotFound();
});

it('keeps the first attempt in order and randomizes retakes while keeping the current take stable', function () {
    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Randomized Questions',
        'source_file' => 'uploads/randomized.txt',
        'status' => Quiz::STATUS_READY,
    ]);

    foreach (range(1, 15) as $number) {
        $quiz->questions()->create([
            'type' => QuizQuestion::TYPE_MCQ,
            'question' => 'Question '.$number,
            'choices' => ['Correct '.$number, 'Option B', 'Option C', 'Option D'],
            'correct_answer' => 'Correct '.$number,
        ]);
    }

    $extractOrder = function ($response): array {
        preg_match_all('/<h2[^>]*>\\s*\\d+\\.\\s*Question (\\d+)\\s*<\\/h2>/', $response->getContent(), $matches);

        return $matches[1];
    };

    $firstAttempt = $this->actingAs($user)->get(route('quizzes.take', $quiz))->assertOk();
    $firstOrder = $extractOrder($firstAttempt);
    $refreshedFirstAttempt = $this->actingAs($user)->get(route('quizzes.take', $quiz))->assertOk();

    expect($firstOrder)->toBe(array_map('strval', range(1, 15)))
        ->and($extractOrder($refreshedFirstAttempt))->toBe($firstOrder);

    $this->actingAs($user)->post(route('quizzes.attempts.store', $quiz), [
        'answers' => $quiz->questions()->get()->mapWithKeys(fn (QuizQuestion $question): array => [
            $question->id => 'Option B',
        ])->all(),
    ])->assertRedirect();

    $retake = $this->actingAs($user)->get(route('quizzes.take', $quiz))->assertOk();
    $retakeOrder = $extractOrder($retake);
    $refreshedRetake = $this->actingAs($user)->get(route('quizzes.take', $quiz))->assertOk();

    expect($retakeOrder)->toHaveCount(15)
        ->and($retakeOrder)->not->toBe($firstOrder)
        ->and($extractOrder($refreshedRetake))->toBe($retakeOrder);
});

it('requires a started quiz, complete answers before timeout, and valid mcq choices', function () {
    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Validated Submission',
        'source_file' => 'uploads/validated.txt',
        'status' => Quiz::STATUS_READY,
    ]);
    $question = $quiz->questions()->create([
        'type' => QuizQuestion::TYPE_MCQ,
        'question' => 'Which option is valid?',
        'choices' => ['A', 'B', 'C', 'D'],
        'correct_answer' => 'A',
    ]);

    $this->actingAs($user)->post(route('quizzes.attempts.store', $quiz), [
        'answers' => [$question->id => 'A'],
    ])->assertRedirect(route('quizzes.take', $quiz))
        ->assertSessionHas('status');

    $this->actingAs($user)->get(route('quizzes.take', $quiz))->assertOk();
    $this->actingAs($user)->post(route('quizzes.attempts.store', $quiz), [
        'answers' => [],
    ])->assertSessionHasErrors('answers.'.$question->id);

    $this->actingAs($user)->post(route('quizzes.attempts.store', $quiz), [
        'answers' => [$question->id => 'Not an available choice'],
    ])->assertSessionHasErrors('answers.'.$question->id);

    expect(QuizAttempt::query()->where('quiz_id', $quiz->id)->count())->toBe(0);
});

it('accepts a partial quiz submission only after the server-side time limit expires', function () {
    $user = User::factory()->create();
    $quiz = Quiz::query()->create([
        'user_id' => $user->id,
        'title' => 'Timed Submission',
        'source_file' => 'uploads/timed.txt',
        'status' => Quiz::STATUS_READY,
    ]);
    $question = $quiz->questions()->create([
        'type' => QuizQuestion::TYPE_MCQ,
        'question' => 'Which option is valid?',
        'choices' => ['A', 'B', 'C', 'D'],
        'correct_answer' => 'A',
    ]);
    $sessionKey = 'quiz_attempt_started_at_'.$quiz->id;

    $this->actingAs($user)
        ->withSession([$sessionKey => now()->subMinutes(11)->timestamp])
        ->get(route('quizzes.take', $quiz))
        ->assertOk()
        ->assertSee('00:00');

    $response = $this->actingAs($user)->post(route('quizzes.attempts.store', $quiz), [
        'answers' => [],
    ]);

    $attempt = QuizAttempt::query()->where('quiz_id', $quiz->id)->firstOrFail();
    $response->assertRedirect(route('quiz-attempts.show', $attempt));
    expect($attempt->correct_count)->toBe(0)
        ->and($attempt->answers)->toHaveCount(1)
        ->and($attempt->answers->first()->user_answer)->toBe('');
});
