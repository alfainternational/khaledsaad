# Hybrid Retrieval And Evaluation Implementation Plan

**Goal:** Add versioned local embeddings, bounded hybrid retrieval, and measurable retrieval quality while preserving lexical retrieval whenever the private worker is unavailable.

**Shared-hosting architecture:** MySQL stores one compact JSON vector per active chunk. PHP never scans an unbounded corpus: lexical search supplies candidates, and semantic expansion is limited by tenant scope and a configured candidate ceiling. Embedding generation is asynchronous through the outbound-only private worker. No daemon, vector extension, or external vector database is required on shared hosting.

## Acceptance Contract

- Every embedding belongs to exactly one knowledge chunk and records model, dimensions, content hash, normalized vector, and lifecycle status.
- A changed chunk creates a new content hash; stale vectors are never used.
- Embedding jobs contain bounded batches of chunk IDs and text, never cross tenant boundaries, and are idempotent.
- Worker results are rejected when IDs, dimensions, count, finite numeric values, normalization, tenant, or content hashes do not match the job.
- Retrieval remains lexical when hybrid search is disabled, a query vector is absent, or vectors are stale.
- Hybrid ranking combines lexical relevance, cosine similarity, scope priority, and source trust deterministically.
- Query embeddings use a short-lived database cache and are generated only outside request-critical paths in this phase.
- Evaluation cases define a scope, query, expected chunk/source, and minimum rank. Runs persist recall, reciprocal rank, latency, engine/model versions, and failure details.
- Health output reports embedding coverage, stale/failed embeddings, pending embedding jobs, and latest evaluation quality.
- All new behavior is controlled by `AI_KNOWLEDGE_HYBRID_RETRIEVAL` and related bounded settings.

## Task 1: Schema And Models

- Add `knowledge_embeddings` with unique `(knowledge_chunk_id, model_name, model_version)` and indexes for status/content hash.
- Add `knowledge_query_embeddings` with scoped query hash, expiry, and model identity.
- Add `intelligence_evaluation_cases`, `intelligence_evaluation_runs`, and `intelligence_evaluation_results`.
- Add Eloquent models and chunk relationships.

## Task 2: Vector Validation And Similarity

- Add a validator that accepts only finite numeric vectors within configured dimensions and normalizes them to unit length.
- Add deterministic cosine similarity with dimension checks.
- Unit test zero vectors, NaN/infinity, dimension mismatch, and stable similarity.

## Task 3: Embedding Job Dispatch And Apply

- Add a command/service that finds active chunks without a current vector and queues bounded `embeddings` jobs grouped by tenant scope.
- Extend the Python worker to call Ollama's local embedding endpoint in bounded batches.
- Extend result application to validate the complete job contract and upsert vectors transactionally.
- Add retries without duplicate vectors and cancellation for deleted or replaced chunks.

## Task 4: Hybrid Retrieval

- Keep lexical candidates as the guaranteed baseline.
- Add semantically similar candidates only from the exact allowed project/workspace/global scope chain.
- Normalize component scores and apply configured weights; use chunk ID as the final stable tie-breaker.
- Never broaden a null tenant scope and never return inactive documents.

## Task 5: Evaluation And Health

- Add `knowledge:evaluate-retrieval` for repeatable seeded or database-backed cases.
- Persist per-case rank, reciprocal rank, latency, and diagnostic metadata.
- Fail the command in strict mode when recall@k or MRR is below configured thresholds.
- Extend `knowledge:health --json` and scheduled maintenance with embedding/evaluation metrics.

## Task 6: Verification And Rollout

- Run focused schema, vector, worker lifecycle, retrieval isolation, and evaluation tests.
- Run the complete test suite and Python worker tests.
- Deploy migration and code with hybrid retrieval disabled.
- Run a signed deterministic embedding canary, verify stored vectors and tenant isolation, clean synthetic data, and leave the flag disabled until a permanent private worker is online.

## Completion Gate

This phase is complete only when production has the schema, worker embedding contract passes end to end, lexical fallback is proven, hybrid ranking improves or preserves the evaluation baseline, tenant isolation tests pass, health is clean, and all rollout flags can be disabled without changing existing behavior.
