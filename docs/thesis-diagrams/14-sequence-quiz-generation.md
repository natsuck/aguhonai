# Sequence Diagram — AI Quiz Generation

```mermaid
sequenceDiagram
    autonumber
    participant B as Browser (Student)
    participant QC as QuizController
    participant Q as MySQL (quizzes · jobs)
    participant W as Queue Worker (GenerateQuizFromMaterial)
    participant TE as TextExtractionService
    participant QG as QuizGeneratorService
    participant OL as OpenAIService

    B->>QC: POST /quizzes (file ≤ 10 MB, title)
    QC->>QC: validate pdf/docx/txt and size
    QC->>Q: store file and insert quiz (status = queued)
    QC->>Q: dispatch GenerateQuizFromMaterial on the 'ai' queue
    QC-->>B: redirect /quizzes/{quiz} (status polling begins)
    Q->>W: job claimed
    W->>Q: status = processing
    W->>TE: extract(source_file)
    TE->>TE: PDF / DOCX / TXT → complete cleaned text (configured safety ceiling)
    TE-->>W: readable text (≥ 100 chars)
    W->>QG: generate(extractedText)
    loop each source chunk
        QG->>OL: Analyze topics and coverage targets
        OL-->>QG: JSON topic plan
    end
    loop topic-specific batches and targeted repairs
        QG->>OL: Generate grounded MCQ or flashcard batch
        OL-->>QG: Strict structured JSON
        QG->>QG: Validate item structure, source quotes, duplicates, coverage
    end
    QG-->>W: coverage-complete adaptive quiz
    W->>Q: transactional insert quiz_questions and status = ready
    loop browser polling every 2 s
        B->>QC: GET /quizzes/{quiz}/status
        QC-->>B: { status: "ready" }
    end
    B->>QC: GET /quizzes/{quiz}
    QC-->>B: quiz page with question browser
```

**Key behaviors:** the upload endpoint returns immediately and all heavy work runs on the
queue worker; every source chunk is analyzed, then 15 MCQs are distributed across topics,
validated in batches, and missing items are requested without regenerating valid items.
Flashcard count remains based on document coverage.