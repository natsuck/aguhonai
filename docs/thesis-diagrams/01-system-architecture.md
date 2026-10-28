# System Architecture

```mermaid
flowchart TD
    subgraph CLIENT["Presentation Layer (Browser)"]
        UI["Blade + Tailwind CSS + Alpine.js + Vite<br/>Chart.js analytics charts"]
    end

    subgraph APP["Application Layer - Laravel 12 (PHP 8.2+)"]
        ROUTES["HTTP Routes + Middleware<br/>(auth, verified, admin, throttle)"]
        CTRL["Controllers<br/>AssessmentController, QuizController,<br/>QuizAttemptController, AdminAnalyticsController"]
        SERV["Service Layer<br/>OpenAIService, AssessmentScoringService,<br/>ProgramMatchingService, QuizGeneratorService,<br/>TextExtractionService"]
        JOB["Queue Job (Database Queue)<br/>GenerateQuizFromMaterial"]
    end

    subgraph AI["AI Layer"]
        OPENAI[("OpenAI-compatible Chat Completions API<br/>(configurable via OPENAI_BASE_URL)")]
    end

    subgraph DATA["Data Layer - MySQL"]
        DB[("Database<br/>users, questions, answers, assessment_results,<br/>program_profiles, quizzes, quiz_questions,<br/>quiz_flashcards, quiz_attempts, quiz_answers,<br/>jobs, cache, sessions")]
    end

    UI -- "HTTPS Requests" --> ROUTES
    ROUTES --> CTRL
    CTRL -- "Business Logic" --> SERV
    SERV -- "Chat Completions API Call" --> OPENAI
    OPENAI -- "Structured/Text Response" --> SERV
    CTRL -- "Dispatch Job" --> JOB
    JOB -- "Text Extraction, Validation, AI Generation" --> SERV
    SERV -- "API Request" --> OPENAI
    CTRL -- "Eloquent ORM (Read/Write)" --> DB
    SERV -- "Persist Results" --> DB
    JOB -- "Persist Generated Quiz" --> DB
```

**Layers:** Client (Browser) → Laravel application → OpenAI-compatible API and MySQL. Quiz generation runs asynchronously through Laravel's database-backed queue worker. Assessment scoring and program matching are deterministic; the AI service is used only to generate a cautious explanatory narrative of the computed profile, with a score-based fallback if the AI call fails.
