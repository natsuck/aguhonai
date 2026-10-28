# Data Flow Diagram - Level 1

```mermaid
flowchart TD
    S(["STUDENT"])
    C(["ADMINISTRATOR"])
    AI(["OpenAI-compatible API"])

    P1(("1.0<br/>User Management<br/>and Authentication"))
    P2(("2.0<br/>Assessment<br/>Processing"))
    P3(("3.0<br/>Program Matching<br/>and AI Explanation"))
    P4(("4.0<br/>Quiz Generation<br/>from Learning Material"))
    P5(("5.0<br/>Quiz Attempt<br/>and Grading"))
    P6(("6.0<br/>Admin Analytics"))

    D1[("D1 - users")]
    D2[("D2 - questions")]
    D3[("D3 - answers")]
    D4[("D4 - assessment_results")]
    D5[("D5 - program_profiles")]
    D6[("D6 - quizzes")]
    D7[("D7 - quiz_questions")]
    D8[("D8 - quiz_flashcards")]
    D9[("D9 - quiz_attempts")]
    D10[("D10 - quiz_answers")]
    D11[("D11 - jobs")]

    S <--> P1
    P1 --> D1

    S -- "privacy consent, 150 assessment responses" --> P2
    D2 -- "150-item instrument" --> P2
    P2 -- "raw answers" --> D3
    P2 -- "scored profile" --> P3

    P3 -- "load program profiles" --> D5
    D5 -- "profiles" --> P3
    P3 -- "deterministic match" --> P3
    P3 -- "narrative request" --> AI
    AI -- "explanation" --> P3
    P3 -- "result with match and narrative" --> D4

    S -- "upload PDF/DOCX/TXT (max 10 MB)" --> P4
    P4 -- "dispatch queued job" --> D11
    P4 -- "quiz record (queued)" --> D6
    P4 -- "extracted text" --> P4
    P4 -- "generate request" --> AI
    AI -- "15 MCQs + 10 flashcards" --> P4
    P4 -- "store questions/flashcards" --> D7
    P4 -- "store flashcards" --> D8

    S -- "attempt answers" --> P5
    P5 -- "grade MCQs deterministically" --> P5
    P5 -- "attempt/result" --> D9
    P5 -- "answer records" --> D10

    C -- "analytics query" --> P6
    P6 -- "read aggregates" --> D1
    P6 -- "read results" --> D4
    P6 -- "read attempts" --> D9
    P6 -- "analytics dashboard" --> C
```

**Notes:** Program matching is deterministic using configured program profiles. AI is only used for generating a cautious explanatory narrative of the computed profile, with score-based fallback if the call fails. Quiz generation is asynchronous via Laravel database-backed queue and produces exactly 15 MCQs and 10 flashcards. MCQs are graded deterministically.
