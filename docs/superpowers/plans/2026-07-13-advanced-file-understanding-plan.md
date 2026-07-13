# Advanced Local File Understanding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the private local worker extract structured, citable knowledge from PDF, images, DOCX, and XLSX while Laravel safely stores, retrieves, retries, and evaluates the results on shared hosting.

**Architecture:** Laravel validates, stores, authorizes, queues, and applies bounded extraction results. The outbound-only Windows worker downloads one signed private file, runs local parsers/OCR, and returns normalized chunks with page, sheet, row, cell, section, table, or image locators. Documents are always untrusted evidence; instruction-like content is flagged as data and never promoted to system instructions.

**Tech Stack:** Laravel 11, MySQL/MariaDB, Python standard library, Tesseract OCR with Arabic/English models, Poppler command-line tools, Ollama embeddings, PHPUnit/Pest, Python unittest.

---

### Task 1: Versioned extraction contract and capability probe

**Files:**
- Create: `app/Domain/AI/Worker/DocumentExtractionContract.php`
- Modify: `app/Domain/AI/Worker/KnowledgeUploadJobDispatcher.php`
- Modify: `worker/private_intelligence_worker/worker.py`
- Test: `tests/Feature/AI/Worker/DocumentExtractionContractTest.php`
- Test: `worker/private_intelligence_worker/test_worker.py`

- [x] Write failing tests for version `v2`, bounded chunks, supported locator types, tool/version metadata, and worker capability reporting.
- [x] Run focused PHP and Python tests and confirm the v2 contract is absent.
- [x] Implement a single schema shared by OCR and document extraction and include required local-tool availability in worker lease metadata.
- [x] Re-run tests and commit `feat: define structured document extraction contract`.

### Task 2: Structured DOCX and XLSX parsing

**Files:**
- Create: `worker/private_intelligence_worker/document_extractors.py`
- Modify: `worker/private_intelligence_worker/worker.py`
- Test: `worker/private_intelligence_worker/test_document_extractors.py`

- [x] Generate minimal DOCX/XLSX fixtures in tests and assert headings, paragraphs, tables, sheets, rows, cells, formulas, inline/shared strings, and exact locators.
- [x] Confirm current flattening fails the tests.
- [x] Implement bounded ZIP/XML parsing with archive path validation, expansion limits, deterministic order, merged-cell awareness, and structured chunks.
- [x] Re-run tests and commit `feat: preserve word and spreadsheet structure`.

### Task 3: Page-aware PDF and confidence-aware OCR

**Files:**
- Modify: `worker/private_intelligence_worker/document_extractors.py`
- Modify: `worker/private_intelligence_worker/worker.py`
- Test: `worker/private_intelligence_worker/test_document_extractors.py`
- Create: `worker/private_intelligence_worker/install_windows_tools.ps1`

- [x] Write failing tests around mocked Poppler/Tesseract output for per-page text, scanned-page OCR fallback, image block coordinates, confidence, language, timeouts, and missing tools.
- [x] Implement page-wise `pdftotext`; rasterize only pages with insufficient text and run Tesseract TSV locally.
- [x] Implement direct image OCR with normalized bounding boxes and confidence summaries.
- [x] Install verified Windows tools and Arabic/English language data outside the repository, then record versions in worker metadata.
- [x] Re-run tests and commit `feat: add page aware local OCR`.

### Task 4: Server-side validation, injection flags, and indexing

**Files:**
- Modify: `app/Domain/AI/Worker/WorkerResultApplier.php`
- Create: `app/Domain/AI/Knowledge/Uploads/UntrustedInstructionScanner.php`
- Modify: `app/Domain/AI/Knowledge/EmbeddingJobDispatcher.php`
- Test: `tests/Feature/AI/Worker/StructuredDocumentWorkerResultTest.php`
- Test: `tests/Unit/AI/Knowledge/UntrustedInstructionScannerTest.php`

- [x] Write failing tests for tenant binding, content hash, locator validation, page/sheet bounds, duplicate positions, text limits, OCR confidence, and prompt-injection phrases.
- [x] Reject malformed or cross-tenant results atomically; store valid chunks with their locators and extraction provenance.
- [x] Flag instruction-like text in chunk/document metadata without deleting evidence or following it.
- [x] Queue embeddings only after a valid active document is committed.
- [x] Re-run tests and commit `feat: validate and index structured file evidence`.

### Task 5: Retry, chunked-upload readiness, and health

**Files:**
- Modify: `app/Http/Controllers/Api/V1/KnowledgeUploadController.php`
- Modify: `app/Domain/AI/Worker/KnowledgeUploadJobDispatcher.php`
- Modify: `app/Console/Commands/ProcessKnowledgeUploadsCommand.php`
- Modify: `app/Console/Commands/KnowledgeHealthCommand.php`
- Test: `tests/Feature/Api/V1/KnowledgeUploadApiTest.php`
- Test: `tests/Feature/AI/Knowledge/ProcessKnowledgeUploadsCommandTest.php`

- [x] Write failing tests proving binary retries redispatch rather than use the text extractor, duplicate active jobs are prevented, failed jobs can resume, and health reports format/OCR outcomes.
- [x] Implement idempotent job dispatch and binary-aware retries with stable error codes.
- [x] Add resumable 1 MB chunk assembly primitives behind a disabled flag without increasing PHP upload limits.
- [x] Re-run tests and commit the retry and resumable-upload changes.

### Task 6: Evaluation, production canaries, and rollout

**Files:**
- Create: `tests/Feature/AI/Knowledge/AdvancedFileEvaluationTest.php`
- Modify: `docs/platform/KNOWLEDGE_FOUNDATION_RUNBOOK.md`
- Modify: `docs/superpowers/plans/2026-07-13-advanced-file-understanding-plan.md`

- [x] Add Arabic/English image, text/scanned PDF, DOCX table, and XLSX formula cases with expected locator and retrieval evidence.
- [x] Run all knowledge/worker/web tests and Python extraction tests.
- [x] Deploy code with new extraction disabled, provision tool versions, then run one private canary per format in an isolated project.
- [x] Verify citations, tenant isolation, embeddings, health, cleanup, and production HTTP 200 before enabling v2 extraction.
- [x] Update PR #6 with measured evidence and commit `docs: verify advanced file understanding rollout`.

### Rollout evidence (2026-07-13)

- Server backup: `_deploy_backups/push-20260713-050413`.
- Migration `2026_07_13_020000_create_knowledge_upload_sessions_table` completed successfully.
- PHP suite: 425 passed, 3011 assertions, one SQLite/MySQL-specific skip.
- Python worker suite: 15 passed.
- Windows scheduled worker completed with result `0`; production reports one active and online worker.
- Production health: 78 sources, 82 documents, 175 chunks, 167 active embeddings, 100% coverage, zero failed/queued/leased jobs.
- Production site returned HTTP 200; chunked-upload routes are present while `AI_KNOWLEDGE_CHUNKED_UPLOADS` remains disabled by default.
- Real isolated production canaries passed for Arabic/English image OCR, text PDF, scanned PDF OCR, DOCX table, and XLSX formula: five indexed uploads, ten structured chunks, exact locator types, KB citations, project isolation, and 100% embedding coverage.
- The authenticated resumable-upload canary assembled and SHA-256 verified a 1,114,113-byte PDF from two chunks through the public API, then produced a v2 page citation with project isolation and 100% embedding coverage.
- Both canary accounts, API token, sessions, files, jobs, sources, documents, chunks, and embeddings were removed. Production returned to 78 sources, 82 documents, 175 chunks, 167 active embeddings, zero queued/leased/failed jobs, and HTTP 200.
- `AI_KNOWLEDGE_STRUCTURED_EXTRACTION=true` and `AI_KNOWLEDGE_CHUNKED_UPLOADS=true` are enabled after both rollout gates passed.
- Final verification: PHP 430 passed, 3062 assertions, one SQLite/MySQL-specific skip; Python worker 15 passed.
