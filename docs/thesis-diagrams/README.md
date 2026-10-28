# Thesis Design Package - AguhonAI - AI-Driven Guidance on Higher Education

Diagram set for the thesis *"AguhonAI - AI-Driven Guidance on Higher Education"*  (STI College Pasay-EDSA, BS Computer Science).

Diagrams are authored in **Mermaid** and rendered to **PNG/SVG**.

## Current implementation baseline

- **Application:** Laravel 12 with PHP 8.2+, Blade, Tailwind CSS, Alpine.js, and Vite.
- **AI provider:** OpenAIService calls an OpenAI-compatible Chat Completions API. Default endpoint is OpenAI; OPENAI_BASE_URL and model settings are configurable. The application does not use Ollama.
- **Database and queue:** Database settings are environment-configured. Quiz generation uses Laravel's database-backed queue and an rtisan queue worker (GenerateQuizFromMaterial).
- **Assessment:** 150 required responses (90 RIASEC interest items, 15 per dimension; 60 aptitude questions, 20 each for verbal, quantitative, and analytical reasoning). RIASEC and aptitude scores are calculated separately. Program matching is deterministic using configured program profiles. AI is only used to generate a cautious explanatory narrative of the computed profile with score-based fallback if the AI call fails.
- **Quiz generation:** Uploads PDF, DOCX, or TXT up to 10 MB. Text is extracted, cleaned, and capped by OPENAI_MAX_DOCUMENT_CHARS (default ~12,000). A queued job sends prepared content to the configured AI provider; it generates exactly 15 MCQs and 10 flashcards and validates structure. No guarantee of topic-by-topic coverage of the entire document.
