# AI Quiz Generator Prompt Engineering Notes

## Module

AI Quiz Generator from Uploaded Learning Materials

## Core AI Controls

- The quiz generator uses the uploaded document text as the only permitted knowledge source.
- The prompt explicitly rejects outside knowledge and unsupported questions.
- OpenAI structured JSON output is requested through the `OpenAIService` abstraction.
- Malformed JSON batches are rejected and retried for the affected topic.
- The decoded JSON is validated before database insertion.
- Invalid JSON, missing fields, malformed choices, unsupported source quotes, duplicates, or MCQ answers that do not match choices trigger targeted batch retries.
- Quiz generation runs through a queue job so upload requests stay responsive and failures can be retried.
- The analyzer processes every bounded source chunk and builds a topic coverage plan before question generation.
- The graded quiz contains 15 MCQs, allocated across analyzed topics and generated in small batches.
- Flashcards remain content-driven and bounded by configuration.

## Quiz Generation Contract

For each generation batch, the AI returns:

- the requested number of multiple choice questions or flashcards for one planned topic
- a short source-grounded explanation for every MCQ answer
- a generation confidence score from `0` to `100`

The batch JSON object contains either `mcqs` or `flashcards`, plus `confidence`. The final
question and flashcard counts come from the document coverage plan and remain within configured
safety ceilings. Short sources are not padded to a fixed count.

```json
{
  "confidence": 0,
  "mcqs": [
    {
      "question": "",
      "choices": ["", "", "", ""],
      "answer": "",
      "explanation": "",
      "source_quote": ""
    }
  ]
}
```

The final graded quiz has 15 MCQs, distributed across the document's planned topics; the
15-item total is not returned as one large response. Flashcard batches use a `flashcards`
array with `front`, `back`, and `source_quote` fields instead of `mcqs`, with their total
determined by the document content.

Students take the quiz before reviewing generated questions. Answer keys and explanations are
revealed after the first completed attempt. Flashcards are studied separately and are not included
in graded attempts. New generations do not include difficulty labels or identification questions;
existing identification questions remain supported for historical quizzes.

## Hallucination Prevention

- Prompt rule: questions and answers must come only from the uploaded document.
- Prompt rule: unsupported questions must not be generated.
- Strict JSON validation prevents storing vague prose responses.
- MCQ answer validation requires the answer key to exactly match one of four distinct choices.
- MCQ explanations and flashcard backs must be non-empty and grounded in the uploaded material.
- Extracted text is cleaned and processed in chunks. Exceeding the configured document safety
  ceiling fails explicitly; later source sections are not silently truncated.
- Each item includes a source quote that must match the uploaded document, and its answer/back
  must appear in that quote.

## Semantic Answer Evaluation

Existing identification answers are evaluated by the answer-checking service with a second strict
JSON prompt:

```json
{
  "is_correct": true,
  "confidence": 0,
  "explanation": ""
}
```

The evaluator accepts semantically equivalent answers, abbreviations, harmless word order
differences, and minor grammar differences. It rejects vague, contradictory, or incomplete
answers. New quiz generations use MCQs instead; this evaluation remains for older quizzes.

## Deployment Variables

Configure adaptive quiz generation in `.env`:

```env
OPENAI_API_KEY=
OPENAI_MODEL_QUIZ=gpt-4o-mini
OPENAI_QUIZ_MAX_DOCUMENT_CHARS=300000
OPENAI_QUIZ_ANALYSIS_CHUNK_CHARS=10000
OPENAI_QUIZ_QUESTION_COUNT=15
OPENAI_QUIZ_MAX_QUESTIONS=120
OPENAI_QUIZ_MAX_FLASHCARDS=80
OPENAI_QUIZ_GENERATION_BATCH_SIZE=5
OPENAI_QUIZ_JSON_RETRIES=3
OPENAI_QUIZ_JOB_TIMEOUT=1800
```

MCQ generation defaults to exactly 15 questions. The configured maximum is a technical
safety ceiling; flashcard counts remain document-driven. Configure the
queue retry-after/visibility timeout above `OPENAI_QUIZ_JOB_TIMEOUT`. Run a worker listening
on the `ai` queue:

```bash
php artisan queue:work --queue=ai,default --tries=3
```

The `composer dev` script already starts a worker listening on `ai,default`.

## Generation Speed Tuning (CPU)

On CPU-only machines, generation time is dominated by the number of tokens the
model produces. The app applies these optimizations:

- **Validated-result caching** — `QuizGeneratorService` caches validated quizzes
  keyed by model, generation settings, and document text (`OPENAI_QUIZ_CACHE_TTL`,
  default 7 days, `0` disables). Re-uploading identical material returns instantly.
- **Brevity-constrained prompts** — questions, choices, explanations, and flashcard content are
  kept concise so fewer tokens are generated.
- **Queue priority** — the worker consumes `ai` before `default`, and the dev
  script targets both queues.
- **Topic-specific batches** — smaller requests keep output bounded and allow missing
  questions to be filled without regenerating valid items.

Optional Ollama server-side tuning applies only when using an Ollama-compatible gateway:

```bash
# Use physical cores only; hyperthreads usually slow token generation
OLLAMA_NUM_THREAD=8 ollama serve

# Avoid splitting compute across parallel slots when serving one user
OLLAMA_NUM_PARALLEL=1 ollama serve

# Optional: flash attention / quantized KV cache (smaller memory footprint)
OLLAMA_FLASH_ATTENTION=1 OLLAMA_KV_CACHE_TYPE=q8_0 ollama serve
```

Verify what is active with `ollama ps`. If quality allows, switching
`OLLAMA_MODEL` to `llama3.2:1b` roughly doubles or triples tokens/sec on CPU.

## Program Recommendation Output

The assessment module now requests structured JSON from Ollama instead of plain text. This makes the recommendation easier to audit and display in the UI.

Expected recommendation object:

```json
{
  "recommended_program": {
    "code": "BSIT",
    "name": "Bachelor of Science in Information Technology"
  },
  "confidence": 0,
  "fit_level": "Strong Fit",
  "reason": "",
  "strengths": ["", "", ""],
  "development_areas": ["", ""],
  "feedback": "",
  "evidence": ["", "", ""],
  "next_steps": ["", "", ""],
  "alternative_programs": [
    {
      "code": "BSCS",
      "name": "Bachelor of Science in Computer Science",
      "reason": ""
    }
  ]
}
```

If Ollama is unavailable or returns malformed JSON, the app falls back to a deterministic assessment-pattern recommendation. The fallback is clearly marked in the data as `source: fallback`, so panelists can distinguish true AI output from continuity-safe output during demos.
