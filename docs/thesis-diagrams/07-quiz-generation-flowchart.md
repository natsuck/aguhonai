# AI Quiz Generation Flowchart

```mermaid
flowchart TD
    START([Student uploads learning material])
    VAL{Valid file?<br/>PDF, DOCX, TXT<br/>max 10 MB}
    ERR[Reject with validation error]
    STORE["Store file in storage"]
    CREATE["Create quiz record (status = queued)"]
    DISP["Dispatch GenerateQuizFromMaterial to database queue (ai)"]
    PROC["Queue worker picks job (status = processing)"]
    EXTRACT["TextExtractionService: extract and clean text"]
    LIMIT["Apply character limit (OPENAI_MAX_DOCUMENT_CHARS, default 12000)"]
    READYTEXT{Enough readable text?}
    GEN["QuizGeneratorService: send to OpenAI-compatible API"]
    RESP["Receive AI response"]
    VALIDATE{Valid structure?<br/>Exactly 15 MCQs + 10 flashcards}
    FAIL["Set status = failed, record generation_error"]
    SAVE["Transaction: save questions and flashcards"]
    READY["Set status = ready, record generated_at"]
    POLL["Client polls status until ready"]
    END([End])

    START --> VAL
    VAL -- "No" --> ERR --> END
    VAL -- "Yes" --> STORE --> CREATE --> DISP --> PROC --> EXTRACT --> LIMIT --> READYTEXT
    READYTEXT -- "No" --> FAIL --> END
    READYTEXT -- "Yes" --> GEN --> RESP --> VALIDATE
    VALIDATE -- "No" --> FAIL --> END
    VALIDATE -- "Yes" --> SAVE --> READY --> POLL --> END
```

**Notes:** Quiz generation is asynchronous via Laravel database-backed queue. The AI generates exactly 15 MCQs and 10 flashcards; the structure is validated. Text is capped by OPENAI_MAX_DOCUMENT_CHARS (default ~12000). The system does not guarantee topic-by-topic coverage of the entire document. MCQs are graded deterministically.
