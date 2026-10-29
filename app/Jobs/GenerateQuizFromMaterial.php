<?php

namespace App\Jobs;

use App\Exceptions\OpenAIQuotaExceededException;
use App\Exceptions\OpenAIRateLimitException;
use App\Exceptions\OpenAIRequestException;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\QuizGeneratorService;
use App\Services\TextExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateQuizFromMaterial implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $quizId)
    {
        $this->timeout = max(60, (int) config('services.openai.quiz_job_timeout', 1800));
        $this->onQueue('ai');
    }

    public function handle(TextExtractionService $extractor, QuizGeneratorService $generator): void
    {
        $quiz = Quiz::query()->findOrFail($this->quizId);

        Log::info('Quiz material generation job started.', ['quiz_id' => $quiz->id]);

        DB::transaction(function () use ($quiz): void {
            $quiz->questions()->delete();
            $quiz->flashcards()->delete();
            $quiz->update([
                'status' => Quiz::STATUS_PROCESSING,
                'generation_error' => null,
                'rate_limited_until' => null,
                'extracted_text' => null,
                'ai_confidence' => null,
                'generated_at' => null,
                'ai_model' => config('services.openai.models.quiz', 'gpt-4o-mini'),
            ]);
        });

        $extractedText = $extractor->extract($quiz->source_file);
        Log::info('Quiz source text extraction completed.', [
            'quiz_id' => $quiz->id,
            'extracted_characters' => mb_strlen($extractedText),
        ]);

        try {
            $generatedQuiz = $generator->generate($extractedText, (int) $quiz->requested_question_count);
        } catch (OpenAIRateLimitException|OpenAIQuotaExceededException $exception) {
            // The provider already retried with Retry-After, so retrying the
            // whole queue job would only burn more quota and worker time.
            $this->fail($exception);

            if ($this->job === null) {
                $this->failed($exception);
            }

            return;
        }

        DB::transaction(function () use ($quiz, $extractedText, $generatedQuiz): void {
            $quiz->questions()->delete();
            $quiz->flashcards()->delete();

            foreach ($generatedQuiz['mcqs'] as $question) {
                $quiz->questions()->create([
                    'type' => QuizQuestion::TYPE_MCQ,
                    'question' => $question['question'],
                    'choices' => $question['choices'],
                    'correct_answer' => $question['answer'],
                    'explanation' => $question['explanation'],
                ]);
            }

            foreach ($generatedQuiz['flashcards'] as $flashcard) {
                $quiz->flashcards()->create([
                    'front' => $flashcard['front'],
                    'back' => $flashcard['back'],
                ]);
            }

            $quiz->update([
                'extracted_text' => $extractedText,
                'status' => Quiz::STATUS_READY,
                'generation_error' => null,
                'rate_limited_until' => null,
                'ai_confidence' => $generatedQuiz['confidence'],
                'generated_at' => now(),
            ]);
        });

        Log::info('AI quiz generated successfully.', [
            'quiz_id' => $quiz->id,
            'question_count' => count($generatedQuiz['mcqs']),
            'flashcard_count' => count($generatedQuiz['flashcards']),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $quiz = Quiz::query()->find($this->quizId);
            if ($quiz === null) {
                Log::error('Quiz generation failed after its quiz record was removed.', [
                    'quiz_id' => $this->quizId,
                    'exception' => $exception::class,
                ]);

                return;
            }

            $quiz->questions()->delete();
            $quiz->flashcards()->delete();
            $quiz->update([
                'status' => Quiz::STATUS_FAILED,
                'generation_error' => $this->studentSafeError($exception),
                'rate_limited_until' => $exception instanceof OpenAIRateLimitException
                    ? now()->addSeconds($exception->retryAfter)
                    : null,
                'extracted_text' => null,
                'ai_confidence' => null,
                'generated_at' => null,
            ]);
        });

        Log::error('AI quiz generation job failed.', [
            'quiz_id' => $this->quizId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    private function studentSafeError(Throwable $exception): string
    {
        if ($exception instanceof OpenAIRateLimitException) {
            return 'The AI service is temporarily at capacity. Please try again in about '
                .$exception->retryAfter.' seconds.';
        }

        if ($exception instanceof OpenAIQuotaExceededException) {
            return 'The AI service quota has been exhausted. Please contact the administrator.';
        }

        if ($exception instanceof OpenAIRequestException) {
            return 'The quiz-generation service could not be reached. Please try again later.';
        }

        if (str_contains($exception->getMessage(), 'not contain enough readable text')) {
            return 'The uploaded material did not contain enough readable text. Please upload a clearer or more complete document.';
        }

        if (str_contains($exception->getMessage(), 'PDF could not be read reliably')
            || str_contains($exception->getMessage(), 'secured or password-protected')) {
            return 'The uploaded PDF could not be read reliably. Please upload an unlocked PDF, or convert the material to DOCX or TXT.';
        }

        if (str_contains($exception->getMessage(), 'No valid document-grounded questions')) {
            return 'No valid document-grounded questions could be generated from the uploaded material.';
        }

        return 'A reliable quiz could not be verified against the uploaded material. Please try a clearer or more complete document.';
    }
}
