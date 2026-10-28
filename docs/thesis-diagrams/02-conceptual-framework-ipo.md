# Method Diagram - Input, Process, Output (Conceptual Framework)

```mermaid
flowchart LR
    subgraph IN["INPUT"]
        A1["Student credentials and user data"]
        A2["150-item assessment responses (90 RIASEC interest and 60 aptitude items)"]
        A3["Privacy consent (RA 10173)"]
        A4["Learning material uploads (PDF, DOCX, TXT up to 10 MB)"]
        A5["Configured program profiles"]
    end

    subgraph PR["PROCESS"]
        P1["Response validation and consent verification"]
        P2["RIASEC scoring (15 items per dimension)"]
        P3["Aptitude scoring (20 items per section)"]
        P4["Deterministic program matching against program profiles"]
        P5["AI narrative generation with fallback"]
        P6["Document text extraction, cleaning and capping"]
        P7["AI quiz generation (15 MCQs and 10 flashcards)"]
        P8["Quiz validation and deterministic MCQ grading"]
        P9["Analytics aggregation"]
    end

    subgraph OUT["OUTPUT"]
        O1["Computed student profile and deterministic program match"]
        O2["AI-generated explanatory narrative or score-based fallback"]
        O3["Generated practice quizzes (15 MCQs and 10 flashcards)"]
        O4["Quiz results and scores"]
        O5["Admin analytics dashboard"]
    end

    IN --> PR --> OUT
```
