# Assessment Module Flowchart

```mermaid
flowchart TD
    START([Student selects Program Suitability Assessment])
    CON{Privacy consent recorded?}
    CONSENT["Show RA 10173 consent notice"]
    GIVE{User accepts consent?}
    STAMP["Save privacy_consented_at"]
    LOAD["Load current 150-item instrument (90 RIASEC + 60 aptitude)"]
    FORM["Present assessment form"]
    SUBMIT[Submit all 150 responses]
    VALID{All 150 responses valid?}
    SCORE["Calculate RIASEC scores (15 per dimension) and aptitude correct-answer rates (20 per section)"]
    MATCH["Deterministic program matching against configured program profiles"]
    NARR["Generate AI explanation of computed profile"]
    AICHECK{AI explanation successful?}
    FALLBACK["Use score-based explanatory narrative"]
    STORE["Store assessment result and program match"]
    RESULT["Display student profile, deterministic program match, and narrative"]
    END([End])

    START --> CON
    CON -- "No" --> CONSENT --> GIVE
    GIVE -- "No" --> END
    GIVE -- "Yes" --> STAMP --> LOAD
    CON -- "Yes" --> LOAD
    LOAD --> FORM --> SUBMIT --> VALID
    VALID -- "No" --> FORM
    VALID -- "Yes" --> SCORE --> MATCH --> NARR --> AICHECK
    AICHECK -- "Yes" --> STORE
    AICHECK -- "No" --> FALLBACK --> STORE
    STORE --> RESULT --> END
```

**Notes:** Assessment requires exactly 150 responses. Program matching is deterministic; AI is only used to explain the computed profile with a fallback to a score-based summary if the AI call fails.
