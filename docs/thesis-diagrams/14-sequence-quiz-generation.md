# Sequence Diagram - Quiz Generation

```mermaid
sequenceDiagram
    autonumber
    participant S as Student (Browser)
    participant QC as QuizController
    participant DB as MySQL (quizzes, jobs)
    participant QW as Queue Worker
    participant J as GenerateQuizFromMaterial
    participant TE as TextExtractionService
    participant QG as QuizGeneratorService
    participant OAI as OpenAIService
    participant API as OpenAI-compatible API

    S->>QC: POST /quizzes (PDF/DOCX/TXT, max 10 MB)
    QC->>QC: Validate file type and size
    QC->>DB: Store file and create Quiz (status=queued)
    QC->>DB: Dispatch job to database queue (ai)
    QC-->>S: Redirect to quiz page (status polling)

    QW->>DB: Claim job
    QW->>J: handle(quizId)
    J->>DB: Update status to processing
    J->>TE: extract(source_file)
    TE-->>J: Extracted and cleaned text
    J->>QG: generate(extractedText)
    QG->>QG: Apply character cap (OPENAI_MAX_DOCUMENT_CHARS)
    QG->>OAI: generate(prompt, json/schema)
    OAI->>API: POST /chat/completions
    API-->>OAI: Generated content
    OAI-->>QG: Response
    QG->>QG: Validate structure (exactly 15 MCQs + 10 flashcards)
    alt Valid
        QG-->>J: Valid generated quiz
        J->>DB: Transaction - save questions and flashcards, status=ready
    else Invalid
        QG-->>J: Validation error
        J->>DB: Update status=failed with generation_error
    end

    loop Status polling
        S->>QC: GET /quizzes/{quiz}/status
        QC->>DB: Get quiz status
        DB-->>QC: Status
        QC-->>S: JSON status
    end
```

**Notes:** Generation is asynchronous via Laravel database-backed queue. Exactly 15 MCQs and 10 flashcards are generated and validated. Text is capped by configuration.
