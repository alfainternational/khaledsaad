# Web Research Verification And Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a shared-hosting-safe web research pipeline that discovers, fetches, verifies, cites, stores, and refreshes public evidence without depending on an external AI service.

**Architecture:** Laravel remains the control plane and source of truth. Search discovery, bounded page fetching, evidence extraction, source trust, cross-source verification, and refresh scheduling are separate services; only bounded network work runs per request or cron invocation, while local Ollama jobs handle optional synthesis asynchronously. Every accepted page becomes a versioned global knowledge source/document with provenance, freshness, and verification metadata.

**Tech Stack:** Laravel 11, MariaDB/MySQL, Laravel HTTP client, DOMDocument, existing `RemoteUrlGuard`, structured knowledge models, private-worker protocol, PHPUnit/Pest.

---

### Task 1: Research persistence and rollout controls

**Files:**
- Create: `database/migrations/2026_07_13_000000_create_web_research_tables.php`
- Create: `app/Domain/AI/Web/Models/WebResearchRun.php`
- Create: `app/Domain/AI/Web/Models/WebResearchResult.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Feature/AI/Web/WebResearchSchemaTest.php`

- [x] Write a failing schema test proving runs/results retain normalized URL, content hash, fetch state, trust tier, freshness, verification state, and source/document links.
- [x] Run `php artisan test tests/Feature/AI/Web/WebResearchSchemaTest.php` and confirm the tables are missing.
- [x] Add the migration, models, casts, indexes, bounded status enums, and disabled-by-default rollout settings.
- [x] Re-run the schema test and commit `feat: add durable web research records`.

### Task 2: Safe bounded page extraction

**Files:**
- Create: `app/Domain/AI/Web/WebPageExtractor.php`
- Modify: `app/Support/Intelligence/RemotePageFetcher.php`
- Modify: `app/Support/Intelligence/RemoteUrlGuard.php`
- Test: `tests/Unit/AI/Web/WebPageExtractorTest.php`
- Test: `tests/Unit/RemotePageFetcherTest.php`

- [ ] Write failing tests for HTML title, canonical URL, visible text, language, publication date, oversized responses, disallowed content types, redirects, and DNS-rebinding-safe target validation.
- [ ] Run both focused test files and confirm failures describe the missing limits and extractor.
- [ ] Implement DOM-based extraction and enforce configured byte, redirect, timeout, content-type, per-host, and DNS checks before accepting content.
- [ ] Re-run tests and commit `feat: extract bounded web evidence safely`.

### Task 3: Search resilience and result normalization

**Files:**
- Create: `app/Domain/AI/Web/CompositeWebSearchGateway.php`
- Create: `app/Domain/AI/Web/SearxngSearchGateway.php`
- Create: `app/Domain/AI/Web/WebSearchResultNormalizer.php`
- Modify: `app/Domain/AI/Web/DuckDuckGoSearchGateway.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/AI/Web/CompositeWebSearchGatewayTest.php`

- [ ] Write failing tests proving deterministic deduplication, canonical URLs, provider fallback, per-domain diversity, and graceful partial failure.
- [ ] Run the focused test and confirm the composite gateway is absent.
- [ ] Implement the composite contract with DuckDuckGo enabled by default and optional private SearXNG, never requiring either for stored-knowledge retrieval.
- [ ] Re-run tests and commit `feat: add resilient web search discovery`.

### Task 4: Trust, freshness, and multi-source verification

**Files:**
- Create: `app/Domain/AI/Web/WebSourcePolicy.php`
- Create: `app/Domain/AI/Web/WebEvidenceVerifier.php`
- Create: `app/Domain/AI/Web/VerifiedWebFinding.php`
- Test: `tests/Unit/AI/Web/WebSourcePolicyTest.php`
- Test: `tests/Unit/AI/Web/WebEvidenceVerifierTest.php`

- [ ] Write failing tests for first-party/official trust, independent-domain agreement, stale evidence, conflicting claims, and required abstention when evidence is insufficient.
- [ ] Run the focused tests and confirm the policy/verifier classes are absent.
- [ ] Implement deterministic trust and freshness scoring, claim-key grouping, independent-domain corroboration, explicit conflicts, and no unsupported winner selection.
- [ ] Re-run tests and commit `feat: verify web evidence across sources`.

### Task 5: Versioned knowledge ingestion and citations

**Files:**
- Create: `app/Domain/AI/Web/WebKnowledgeIngestor.php`
- Modify: `app/Domain/AI/Knowledge/StructuredKnowledgeRepository.php`
- Modify: `app/Domain/AI/Web/WebResearchService.php`
- Modify: `app/Domain/AI/Kernel/Skills/WebResearchSkill.php`
- Test: `tests/Feature/AI/Web/WebKnowledgeIngestorTest.php`
- Test: `tests/Feature/AI/Web/WebResearchServiceTest.php`

- [ ] Write failing tests proving accepted pages create global versioned sources/documents/chunks, unchanged content is idempotent, changed content supersedes old documents, citations retain URL/title/fetch date, and unverified claims remain labeled.
- [ ] Run focused tests and confirm current link-only `KnowledgeStore` behavior fails them.
- [ ] Implement ingestion through structured knowledge, dispatch embeddings, return evidence-backed findings, and preserve lexical fallback when the worker is offline.
- [ ] Re-run tests and commit `feat: ingest cited web evidence into knowledge`.

### Task 6: Shared-hosting research and refresh commands

**Files:**
- Create: `app/Console/Commands/ResearchWebKnowledgeCommand.php`
- Create: `app/Console/Commands/RefreshWebKnowledgeCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/AI/Web/WebResearchCommandsTest.php`
- Test: `tests/Feature/Intelligence/MonitoringScheduleTest.php`

- [ ] Write failing tests for bounded batches, resumable runs, host rate limits, due-only refresh, retry backoff, stale marking, and disabled rollout behavior.
- [ ] Run tests and confirm commands/schedules are absent.
- [ ] Implement cron-safe commands with locks, limits, deadlines, checkpoints, and schedules that fit shared hosting.
- [ ] Re-run tests and commit `feat: schedule bounded web knowledge refresh`.

### Task 7: Evaluation, health, rollout, and production proof

**Files:**
- Modify: `app/Console/Commands/KnowledgeHealthCommand.php`
- Create: `tests/Feature/AI/Web/WebResearchEvaluationTest.php`
- Modify: `deploy/cpanel-push.sh`
- Modify: `docs/runbooks/private-worker-rollout.md`

- [ ] Add failing evaluation tests for source diversity, citation completeness, stale/conflict labeling, SSRF rejection, and correct abstention.
- [ ] Implement health counters and rollout documentation, then run all AI knowledge/web/worker tests plus Python worker tests.
- [ ] Deploy migrations and code with web research disabled, run a signed production canary against two public sources, inspect stored provenance and citations, then enable scheduled refresh only after the gate passes.
- [ ] Update PR #6 with measured production evidence and commit `docs: verify web research rollout`.
