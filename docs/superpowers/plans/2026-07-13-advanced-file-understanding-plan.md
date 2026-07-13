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

- [ ] Write failing tests around mocked Poppler/Tesseract output for per-page text, scanned-page OCR fallback, image block coordinates, confidence, language, timeouts, and missing tools.
- [ ] Implement page-wise `pdftotext`; rasterize only pages with insufficient text and run Tesseract TSV locally.
- [ ] Implement direct image OCR with normalized bounding boxes and confidence summaries.
- [ ] Install verified Windows tools and Arabic/English language data outside the repository, then record versions in worker metadata.
- [ ] Re-run tests and commit `feat: add page aware local OCR`.

### Task 4: Server-side validation, injection flags, and indexing

**Files:**
- Modify: `app/Domain/AI/Worker/WorkerResultApplier.php`
- Create: `app/Domain/AI/Knowledge/Uploads/UntrustedInstructionScanner.php`
- Modify: `app/Domain/AI/Knowledge/EmbeddingJobDispatcher.php`
- Test: `tests/Feature/AI/Worker/StructuredDocumentWorkerResultTest.php`
- Test: `tests/Unit/AI/Knowledge/UntrustedInstructionScannerTest.php`

- [ ] Write failing tests for tenant binding, content hash, locator validation, page/sheet bounds, duplicate positions, text limits, OCR confidence, and prompt-injection phrases.
- [ ] Reject malformed or cross-tenant results atomically; store valid chunks with their locators and extraction provenance.
- [ ] Flag instruction-like text in chunk/document metadata without deleting evidence or following it.
- [ ] Queue embeddings only after a valid active document is committed.
- [ ] Re-run tests and commit `feat: validate and index structured file evidence`.

### Task 5: Retry, chunked-upload readiness, and health

**Files:**
- Modify: `app/Http/Controllers/Api/V1/KnowledgeUploadController.php`
- Modify: `app/Domain/AI/Worker/KnowledgeUploadJobDispatcher.php`
- Modify: `app/Console/Commands/ProcessKnowledgeUploadsCommand.php`
- Modify: `app/Console/Commands/KnowledgeHealthCommand.php`
- Test: `tests/Feature/Api/V1/KnowledgeUploadApiTest.php`
- Test: `tests/Feature/AI/Knowledge/ProcessKnowledgeUploadsCommandTest.php`

- [ ] Write failing tests proving binary retries redispatch rather than use the text extractor, duplicate active jobs are prevented, failed jobs can resume, and health reports format/OCR outcomes.
- [ ] Implement idempotent job dispatch and binary-aware retries with stable error codes.
- [ ] Add resumable 1 MB chunk assembly primitives behind a disabled flag without increasing PHP upload limits.
- [ ] Re-run tests and commit `feat: make complex file processing resumable`.

### Task 6: Evaluation, production canaries, and rollout

**Files:**
- Create: `tests/Feature/AI/Knowledge/AdvancedFileEvaluationTest.php`
- Modify: `docs/platform/KNOWLEDGE_FOUNDATION_RUNBOOK.md`
- Modify: `docs/superpowers/plans/2026-07-13-advanced-file-understanding-plan.md`

- [ ] Add Arabic/English image, text/scanned PDF, DOCX table, and XLSX formula cases with expected locator and retrieval evidence.
- [ ] Run all knowledge/worker/web tests and Python extraction tests.
- [ ] Deploy code with new extraction disabled, provision tool versions, then run one private canary per format in an isolated project.
- [ ] Verify citations, tenant isolation, embeddings, health, cleanup, and production HTTP 200 before enabling v2 extraction.
- [ ] Update PR #6 with measured evidence and commit `docs: verify advanced file understanding rollout`.
