# AI Quiz Generator Flowchart

```mermaid
flowchart TD
    START([Student uploads<br/>learning material])
    VAL{Valid file?<br/>pdf · docx · txt<br/>≤ 10 MB}
    ERR[Reject with validation error]
    STORE["Store file to private storage<br/>(local / uploads)"]
    CREATE["Create quiz record<br/>status = 'queued'"]
    DISP["Dispatch GenerateQuizFromMaterial<br/>on the 'ai' queue"]
    PROC["Queue worker picks job<br/>status = 'processing'"]
    EXTR["TextExtractionService<br/>PDF / DOCX / TXT → complete cleaned text<br/>(configurable safety ceiling)"]
    READ{Readable text ≥ 100 chars?}
    ANALYZE["Analyze every source chunk<br/>identify topics and coverage targets"]
    PLAN["Merge topic plan<br/>apply configurable safety ceilings"]
    PROMPT["QuizGeneratorService<br/>allocate 15 MCQs across topics<br/>generate per-topic batches"]
    GEN["POST OpenAI chat completions<br/>(strict structured JSON)"]
    SCH{Batch valid?<br/>grounded quote · distinct items ·<br/>4 choices per MCQ}
    RTRY{Targeted repair attempts left?}
    REPAIR["Request only missing or<br/>invalid items for this topic"]
    COVER{All planned topic targets met?}
    INS["Transactional insert<br/>questions + flashcards → status = 'ready'"]
    FOLL["Browser polls /status<br/>every 2 s until ready"]
    TAKER["Student takes quiz<br/>server starts timer; refresh preserves remaining time"]
    SUBMIT["Student submits answers"]
    VALIDATE{"Server checks started attempt,<br/>all answers, and allowed MCQ choices"}
    RETRY["Show validation errors;<br/>attempt remains open"]
    TIMEOUT["Time limit expired;<br/>allow unanswered items"]
    GRADE["Grade and save completed attempt<br/>using server-recorded start time"]
    REVIEW["Review questions, correct answers,<br/>and explanations after attempt 1"]
    STUDY["Flashcards available<br/>in separate ungraded study mode"]
    FAIL["status = 'failed'<br/>generation_error recorded"]
    END([End])

    START --> VAL
    VAL -- "No" --> ERR --> END
    VAL -- "Yes" --> STORE
    STORE --> CREATE --> DISP --> PROC --> EXTR
    EXTR --> READ
    READ -- "No" --> FAIL --> END
    READ -- "Yes" --> ANALYZE --> PLAN --> PROMPT --> GEN --> SCH
    SCH -- "Yes" --> COVER
    SCH -- "No" --> RTRY
    RTRY -- "Yes" --> REPAIR --> GEN
    RTRY -- "No" --> FAIL
    COVER -- "No" --> PROMPT
    COVER -- "Yes" --> INS
    INS --> FOLL --> TAKER --> SUBMIT --> VALIDATE
    VALIDATE -- "Complete and valid" --> GRADE
    VALIDATE -- "Incomplete before timeout" --> RETRY --> TAKER
    VALIDATE -- "Expired" --> TIMEOUT --> GRADE
    GRADE --> REVIEW --> END
    FOLL --> STUDY --> END
```

- The upload endpoint responds immediately; generation runs asynchronously on the queue
  worker so the UI is not blocked.
- Every document chunk is analyzed, then topic-specific batches are generated and validated.
  Missing coverage triggers targeted fill-in batches;   MCQs total 15 by default; flashcard counts remain content-driven. Configurable maxima
  are technical safety ceilings.
- Students must complete an attempt before generated questions, answer keys, and explanations are
  available for review.
- The server enforces the attempt start time, duration, answer completeness before timeout, and
  that MCQ responses match the generated choices. After the server-measured time expires, unanswered
  items may be submitted and are scored as unanswered.
- Answer keys and explanations are revealed after the first completed attempt.