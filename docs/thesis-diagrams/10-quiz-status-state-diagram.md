# Quiz Status State Diagram

```mermaid
stateDiagram-v2
    [*] --> queued: quiz created on upload
    queued --> processing: queue worker starts
    processing --> ready: topic coverage completed and generated items inserted
    processing --> failed: text too short /<br/>invalid batch after configured repairs / exception
    failed --> queued: student clicks "Retry" (re-dispatch)
    ready --> ready: student takes or retakes quiz
    ready --> [*]
    queued --> failed: job exhausted / transfer failed
```

- `queued` → `processing` → `ready` is the happy path; generation is fully asynchronous.
- A `failed` quiz retains the stored error message and can be retried, re-dispatching the
  job without re-uploading the file.