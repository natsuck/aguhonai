# Admin Analytics Flowchart

```mermaid
flowchart TD
    START([Administrator opens Admin Analytics])
    AUTH{User is admin?}
    DENY["403 Forbidden"]
    QUERY["Query aggregates from assessment_results, users, quizzes, quiz_attempts"]
    KPI["Compute KPIs: assessment totals, student counts, AI vs fallback counts, flagged responses"]
    DIST["Compute program distribution from assessment results"]
    RECENT["Get recent submissions"]
    RENDER["Render analytics dashboard"]
    END([End])

    START --> AUTH
    AUTH -- "No" --> DENY --> END
    AUTH -- "Yes" --> QUERY --> KPI --> DIST --> RECENT --> RENDER --> END
```

**Notes:** Analytics are generated from stored data. Metrics include assessment totals, student counts, program distribution, AI-generated vs fallback counts, flagged-response counts, and recent submissions.
