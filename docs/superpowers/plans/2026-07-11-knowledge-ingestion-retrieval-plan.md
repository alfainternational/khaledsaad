# Knowledge Ingestion And Retrieval Implementation Plan

**Goal:** Let a workspace upload text-based project files, index them in the structured knowledge store, retrieve tenant-safe evidence, and inject cited evidence into analysis on shared hosting.

**Architecture:** Laravel handles small, bounded text extraction synchronously and stores private originals outside the public disk. Every accepted file becomes one versioned KnowledgeSource and active KnowledgeDocument; chunks retain page, row, section, or line locators. Retrieval searches project, workspace, then global scopes independently, merges results deterministically, and never broadens a missing tenant scope. A feature flag controls prompt injection while ingestion remains explicit.

**Shared-hosting constraints:** No daemon, vector database, local LLM in PHP, or unbounded document parsing. Requests enforce file count, byte, text, chunk, and execution limits. Formats requiring OCR or heavy native tools are retained with a clear needs_worker state for the later private-worker phase.

## Acceptance Contract

- Authenticated members can upload txt, md, csv, json, and text-readable html files to an accessible project.
- Files are stored privately, content-addressed, and deduplicated per project.
- MIME detection uses server-side file inspection; extensions alone are never trusted.
- Extraction rejects binaries, invalid UTF-8, oversized expanded text, and unsafe HTML.
- Every indexed chunk carries a stable locator and source metadata.
- Retrieval searches only the requested project, its workspace, and global knowledge.
- Project results rank before workspace and global ties; all ties are deterministic.
- Prompt evidence includes source title, URI, locator, trust, and a stable citation token.
- Empty or unavailable retrieval leaves current generation behavior unchanged.
- Deleting an upload deactivates its active knowledge document and removes the private original.
- All behavior is covered by isolation, authorization, idempotency, invalid-file, and prompt-injection tests.

## Task 1: Retrieval Contract

**Files:**
- Create app/Domain/AI/Knowledge/KnowledgeEvidence.php
- Create app/Domain/AI/Knowledge/KnowledgeRetriever.php
- Modify app/Domain/AI/Knowledge/StructuredKnowledgeRepository.php
- Test tests/Feature/AI/Knowledge/KnowledgeRetrieverTest.php

Implement tokenized query search with strict per-scope queries. MariaDB uses FULLTEXT where useful and a bounded literal fallback for short Arabic terms; SQLite remains a deterministic test fallback. Merge project, workspace, and global results without ever querying another tenant. Return immutable evidence records containing chunk ID, source title/kind/URI, locator, excerpt, trust, scope, and score.

## Task 2: Upload Persistence And Extraction

**Files:**
- Create migration for knowledge_uploads
- Create app/Domain/AI/Knowledge/Models/KnowledgeUpload.php
- Create app/Domain/AI/Knowledge/Uploads/TextKnowledgeExtractor.php
- Create app/Domain/AI/Knowledge/Uploads/KnowledgeUploadIndexer.php
- Test extractor and indexer

Store originals on the local private disk under a project-derived path. Persist SHA-256, detected MIME, bytes, status, error code, extraction metadata, and the linked knowledge source. Normalize line endings and Unicode, strip executable HTML content, flatten JSON deterministically, and produce bounded chunks with line/row/JSON-path locators.

## Task 3: Authorized API

**Files:**
- Create request/controller/resource classes
- Add project-scoped API routes
- Add policy or reuse project authorization
- Test upload, list, show, retry, and delete

Use multipart uploads with conservative limits suitable for shared hosting. Never expose storage paths. Return status and extraction diagnostics in Arabic-facing API fields while keeping stable machine codes.

## Task 4: Evidence Prompt Integration

**Files:**
- Create app/Domain/AI/Knowledge/KnowledgePromptContext.php
- Modify app/Support/AI/WorkspaceGenerationContextBuilder.php
- Modify config/services.php and environment examples
- Test prompt behavior and tenant isolation

Build the retrieval query from project identity, brief, current user inputs when available, and the requested operation. Inject a bounded evidence block after the analytical dossier. Require citation tokens for source-based claims and explicitly tell generators to distinguish evidence from inference. Gate with AI_KNOWLEDGE_RETRIEVAL=false.

## Task 5: Operations And Rollout

**Files:**
- Extend knowledge:health
- Add cleanup/retry command and guarded schedule
- Update deployment runbook

Expose upload counts by status, extraction failures, unlinked uploads, and searchable active chunks. Roll out schema, ingestion, canary project, retrieval flag, then all projects. Keep rollback capable of disabling retrieval without deleting indexed data.

## Verification

1. Run focused SQLite tests for extraction, indexing, retrieval, prompt integration, and authorization.
2. Run focused MySQL tests for FULLTEXT Arabic/English behavior and scope isolation.
3. Run the complete suite.
4. On production, upload one synthetic project file containing a unique marker, retrieve it only from that project, verify its citation token, delete it, and verify it is no longer retrievable.
5. Confirm health, scheduler, HTTP status, memory, and execution time remain within shared-hosting limits.
