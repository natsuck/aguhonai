# Aguhon

Aguhon is a Laravel web application for student academic guidance. Students complete a
versioned, 150-item assessment and receive a score profile with deterministic, profile-based
program matches and a separate AI-generated explanation. Historical legacy assessment results
remain distinguishable. The app also generates practice quizzes and flashcards from uploaded
learning materials.

**Current program matches are advisory calculations using initial proposed profiles—not
validated placement, admission, or academic-success decisions.** The current instrument has 90
RIASEC interest items and 60 aptitude questions. The application scores those responses; it does
not train a local machine-learning model or evaluate student grades, learning styles, or existing
academic records. For this current instrument, AI provides a separate natural-language profile
explanation and does not calculate suitability scores or program rankings.

## Features

### Student assessment

- 90 RIASEC career-interest items and 60 multiple-choice aptitude items (verbal, quantitative,
  and analytical)
- Server-side scoring, required responses, per-attempt history, and response-validity flags
- Deterministic program suitability rankings and a separate AI-generated profile explanation
- Privacy-consent notice before the assessment

### Quiz generation and study

- Upload PDF, DOCX, or TXT learning materials (up to 10 MB)
- Background generation of 15 source-grounded multiple-choice questions and 10 separate
  flashcards
- Server-side quiz timing, answer completeness checks, and MCQ choice validation
- Students complete a quiz before question review; answer keys and explanations are available
  after the first attempt
- Retakes randomize question order; flashcards are a separate, ungraded study mode

### Admin panel

Administrators can manage resource listings and accounts from the admin panel. Account
management supports profile updates, password-reset links, email verification, and confirmed
permanent deletion. Deleting an account also removes its associated records, queued quiz jobs,
sessions, and uploaded quiz materials; administrators cannot delete their own account.

The admin-only `/admin/analytics` dashboard summarizes assessment totals, unique students,
primary program matches, AI output availability, and response flags. Any legacy model-reported
confidence is not calibrated and is not supplied for current instrument mappings.

## How AI is used

- For the current 150-item assessment, the application computes deterministic program suitability
  rankings from initial program profiles. The configured AI provider explains the profile
  separately and does not determine those scores or rankings. Historical legacy recommendations
  remain separate.
- Quiz generation uses extracted text from the uploaded learning material. Identification answers
  may be evaluated semantically by the configured AI provider; MCQs are graded against their
  answer key.
- AI output is used only for the natural-language profile explanation; it is not treated as a
  psychometrically validated mapping.
- Assessment profiles and uploaded learning material may be sent to the configured AI provider
  for processing. Do not send real student data until the school has approved the privacy notice,
  provider terms, and deployment arrangements.

## Technology

- PHP 8.2+ and Laravel 12
- Blade, Vite, Tailwind CSS, and Alpine.js
- MySQL (configured default; supported for local development and Laravel Cloud)
- OpenAI-compatible API for AI features
- `smalot/pdfparser` and `phpoffice/phpword` for document extraction

## Local setup

Requirements: PHP 8.2+, Composer, Node.js 20+, npm, and MySQL.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Configure the database and AI settings in `.env`, then run:

```bash
php artisan migrate --seed
npm install
npm run build
composer dev
```

`composer dev` starts the local web server, Vite, and a queue listener for the `ai` and `default`
queues. Quiz generation requires the queue listener to be running. Assessment scoring runs during
the web request.

The general database seeder creates demo accounts with the password `password`:

| Role | Email |
|---|---|
| Student | `test@example.com` |
| Guidance admin | `admin@realitycheck.test` |

These accounts are for local development only. **Never run `php artisan db:seed` or
`php artisan migrate --seed` in production.** Production setup is described below.

## Laravel Cloud deployment

The application is **not yet ready for production deployment to Laravel Cloud without the
prerequisites below**. Cloud application filesystems are ephemeral, so quiz uploads must use
durable shared storage accessible to both web and queue-worker processes. The current locked
Laravel version also does not meet Laravel Cloud's documented managed-queue minimum.

### Required before production

1. **Configure durable quiz uploads.** Attach a private Laravel Cloud Object Storage bucket and
   set `FILESYSTEM_DISK` to its configured name (for example, `private`) in both the web and
   queue-worker environments. Quiz uploads, downloads, deletion, and text extraction all use
   Laravel's configured default filesystem disk; extraction streams the object to a temporary local
   file for parsing and removes that file when finished. Do not make student-upload buckets public.
   Local development defaults to the local disk and does not require the cloud bucket.
2. **Choose and configure queue processing.**
   - For Laravel Cloud managed queues, upgrade Laravel to a Cloud-supported version (currently
     Laravel 12.63.0 or later) and add the required AWS SDK dependency. Configure a managed queue
     named `ai`, since quiz-generation jobs are dispatched to that queue. Check the selected queue
     compute class against the job's 300-second timeout; Cloud Flex queues have a 90-second job
     runtime limit.
   - Alternatively, retain the database queue and configure a Cloud worker cluster to run a
     worker for the `ai` and `default` queues with a timeout and retry policy compatible with the
     job.
3. **Use a persistent database.** Attach a Laravel Cloud MySQL or PostgreSQL resource in the same
   region as the app. This repository defaults to MySQL; do not change database engines without
   running the migration and test suite against the target engine.
4. **Create production users safely.** Run schema migrations and seed only the assessment
   instrument if needed (for example, `php artisan db:seed --class=QuestionSeeder`). Create the
   real admin account through a secure, approved process. Do not create or deploy the demo
   accounts.
5. **Obtain privacy/security approval.** Confirm the Cloud region, private file-storage location,
   AI-provider processing terms, access controls, retention/deletion behavior, and consent
   language with the school before processing identifiable student data.

### Laravel Cloud environment configuration

After completing the prerequisites, a typical Laravel Cloud configuration is:

- **Runtime:** supported PHP runtime compatible with the application
- **Build command:** `composer install --no-dev && npm ci && npm run build`
- **Deploy command:** `php artisan migrate --force`
- **Persistent data:** attached Cloud database and private object-storage bucket
- **Secrets/settings:** production `APP_ENV`, `APP_DEBUG=false`, stable `APP_KEY`, production
  `APP_URL`, and `OPENAI_API_KEY`; use the Cloud-injected database and storage credentials
- **Queue:** one of the queue configurations described above; verify quiz generation and failure
  handling in staging before launch

Never commit `.env`, API keys, database credentials, or application keys. Set production secrets
in the Laravel Cloud environment settings, not in this README or source control.

### Gmail SMTP for verification and password-reset emails

The application sends Laravel's built-in email-verification and password-reset notifications.
For a personal Gmail sender, enable 2-Step Verification on the Google account and create a Google
App Password. Use the App Password, not the account's normal password. Add these settings to the
Laravel Cloud environment (never commit them or share them in chat):

- `MAIL_MAILER=smtp`
- `MAIL_SCHEME=smtp`
- `MAIL_HOST=smtp.gmail.com`
- `MAIL_PORT=587`
- `MAIL_USERNAME` — the full Gmail address
- `MAIL_PASSWORD` — the Google App Password
- `MAIL_FROM_ADDRESS` — the same Gmail address
- `MAIL_FROM_NAME` — the name recipients should see

Set `APP_URL` to the deployed HTTPS URL for links in password-reset emails. Email verification
uses a six-digit code that expires after 10 minutes; users have five attempts before they need to
request another code. Deploy after updating environment settings so the verification-code database
migration runs, then test registration, code verification, resend, and password reset using an
address you control. Gmail may impose sending limits; use a transactional email provider and a
domain you control for sustained or production mail.

See the official [Laravel Cloud Laravel deployment guide](https://laravel.com/cloud/docs/deploy-guides/laravel.md),
[managed queues documentation](https://laravel.com/cloud/docs/queues.md), and
[Object Storage documentation](https://laravel.com/cloud/docs/resources/object-storage.md) for
current platform requirements.

## Configuration

AI settings are configured using `OPENAI_*` variables in `.env` (see `.env.example`):

- `OPENAI_API_KEY` — API secret
- `OPENAI_ORGANIZATION`, `OPENAI_BASE_URL` — optional organization or compatible gateway
- `OPENAI_MODEL_DEFAULT`, `OPENAI_MODEL_ASSESSMENT`, `OPENAI_MODEL_QUIZ`,
  `OPENAI_MODEL_ANSWER` — per-task model selection
- `OPENAI_TIMEOUT`, `OPENAI_RETRIES`, `OPENAI_TEMPERATURE` — request behavior
- `OPENAI_ASSESSMENT_MAX_TOKENS`, `OPENAI_QUIZ_MAX_TOKENS` — response budgets
- `OPENAI_QUIZ_CACHE_TTL` — cache duration for validated generated quizzes
- `OPENAI_MAX_DOCUMENT_CHARS` — maximum extracted source text sent to quiz generation (default: 12,000 characters)
- `OPENAI_QUIZ_JOB_TIMEOUT` — quiz queue-job timeout; keep the worker timeout aligned and the queue `retry_after` higher (defaults: 1,800 and 1,900 seconds)
- `OPENAI_QUIZ_JSON_RETRIES` — retries for invalid or incomplete AI responses

Each quiz is generated from the uploaded material as exactly 15 document-grounded multiple-choice
questions and 10 flashcards. The document text is capped at the configured character limit before
it is sent to the AI.

## Tests and quality checks

```bash
composer test
vendor/bin/pint
npm run build
```

The test suite covers assessment consent and scoring, per-attempt response persistence, quiz
generation and validation, timing and answer checks, answer review, authorization, and AI
normalization/fallback behavior.

## Key paths

| Area | Path |
|---|---|
| Controllers | `app/Http/Controllers` |
| Assessment scoring | `app/Services/AssessmentScoringService.php` |
| AI services | `app/Services/OpenAIService.php`, `QuizGeneratorService.php`, `AnswerEvaluationService.php` |
| Document extraction | `app/Services/TextExtractionService.php` |
| Quiz generation job | `app/Jobs/GenerateQuizFromMaterial.php` |
| Database migrations and seeders | `database/migrations`, `database/seeders` |
| Assessment methodology | `docs/assessment-methodology.md` |
| Quiz prompt design | `docs/ai-quiz-generator-prompt-engineering.md` |
