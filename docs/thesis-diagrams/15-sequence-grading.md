# Sequence Diagram - Answer Grading

```mermaid
sequenceDiagram
    autonumber
    participant S as Student (Browser)
    participant QAC as QuizAttemptController
    participant DB as MySQL

    S->>QAC: POST /quizzes/{quiz}/attempts (answers)
    QAC->>QAC: Validate submission
    QAC->>DB: Load quiz questions
    DB-->>QAC: Questions with correct answers
    QAC->>QAC: Grade MCQs deterministically (compare with correct_answer)
    alt Other question types (if any)
        QAC->>QAC: Apply appropriate grading logic
    end
    QAC->>QAC: Calculate score and counts
    QAC->>DB: Transaction - create QuizAttempt and QuizAnswers
    QAC-->>S: Redirect to attempt result/review
    S->>QAC: GET /quiz-attempts/{quizAttempt}
    QAC->>DB: Load attempt and answers
    DB-->>QAC: Attempt data
    QAC-->>S: Display score and review
```

**Notes:** MCQs are graded deterministically by comparing student answers with stored correct answers. The system retains compatibility for older identification questions, but the current quiz generator produces only MCQs and flashcards.
