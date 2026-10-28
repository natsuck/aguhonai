# Sequence Diagram - Assessment Submission

```mermaid
sequenceDiagram
    autonumber
    participant S as Student (Browser)
    participant AC as AssessmentController
    participant AS as AssessmentScoringService
    participant PM as ProgramMatchingService
    participant OAI as OpenAIService
    participant API as OpenAI-compatible API
    participant DB as MySQL

    S->>AC: POST /assessment/submit (150 responses)
    AC->>AC: Validate consent and responses
    AC->>AS: score(responses)
    AS-->>AC: RIASEC scores and aptitude rates
    AC->>PM: match(scoreBreakdown)
    PM->>DB: Load active program profiles
    DB-->>PM: Program profiles
    PM-->>AC: Deterministic program match and Holland code
    AC->>OAI: analyzeAssessmentProfile(scoreBreakdown)
    alt AI available
        OAI->>API: POST /chat/completions (narrative request)
        API-->>OAI: Narrative response
        OAI-->>AC: AI narrative
    else AI fails
        OAI-->>AC: Exception
        AC->>AC: Generate score-based fallback narrative
    end
    AC->>DB: Create AssessmentResult with match and narrative
    AC-->>S: Redirect to results page
    S->>AC: GET /results
    AC->>DB: Load assessment result
    DB-->>AC: Result data
    AC-->>S: Display profile, deterministic match, and narrative
```

**Notes:** Program matching is deterministic and happens before AI explanation. AI only generates a cautious narrative/explanation; if it fails, a score-based fallback is used.
