# Private Intelligence Worker Implementation Plan

**Goal:** Add an outbound-only private worker protocol so shared hosting can delegate OCR, embeddings, reranking, and local-model analysis without exposing the owner's machine or requiring an always-on process in PHP.

**Security contract:** Each worker has a random ID and encrypted secret provisioned by an Artisan command. Requests carry worker ID, Unix timestamp, nonce, body hash, and HMAC-SHA256 over method, canonical path, timestamp, nonce, and body hash. The server rejects clock drift, replayed nonces, disabled workers, unknown capabilities, expired leases, mismatched lease tokens, and duplicate completion. TLS remains mandatory.

**Job contract:** Jobs contain type, tenant IDs, a bounded JSON payload with references rather than raw private files, input hash, availability, attempt limit, and timeout. Leasing is transactional. A lease returns a random token once and a job envelope signed with the worker secret. Only the leasing worker can heartbeat, download referenced input, complete, or fail the job.

## Task 1: Schema And Models

- Create intelligence_workers with encrypted secret, capabilities, status, version, heartbeat, and audit metadata.
- Create intelligence_worker_nonces with a unique worker/nonce pair and expiry.
- Extend intelligence_jobs with account, worker, lease token hash, input/output hashes, model metadata, timeout, max attempts, and progress.
- Add strict integer/JSON/datetime casts and tenant relationships.

## Task 2: Provisioning And Authentication

- Add private-worker:provision and private-worker:disable commands.
- Add request-signature middleware with constant-time HMAC validation, five-minute drift, nonce persistence, and safe logging.
- Never persist or log the plaintext secret after provisioning.

## Task 3: Lease Lifecycle API

- POST /api/v1/private-worker/lease
- POST /api/v1/private-worker/jobs/{job}/heartbeat
- POST /api/v1/private-worker/jobs/{job}/complete
- POST /api/v1/private-worker/jobs/{job}/fail

Lease only capabilities advertised by both the worker record and request. Increment attempts atomically, recover expired leases, cap payload/result bytes, and make completion idempotent by output hash.

## Task 4: Private Input And Result Application

- GET /api/v1/private-worker/jobs/{job}/input for a leased upload reference.
- Stream only the exact private upload associated with the job and tenant.
- For extract_document results, validate text/chunks and store a new uploaded-file document through StructuredKnowledgeRepository.
- Reject result tenant IDs or source references that differ from the queued job.

## Task 5: Reference Worker

- Add a Python standard-library polling client under worker/private_intelligence_worker.
- Verify server job signatures before executing.
- Support health, deterministic echo, Ollama/OpenAI-compatible JSON generation, and optional command adapters for Tesseract/pdftotext.
- Keep credentials in a local environment file and redact all logs.

## Task 6: Operations

- Add worker and lease metrics to knowledge:health.
- Add a cron command that requeues expired leases and removes expired nonces.
- Gate all worker routes with AI_PRIVATE_WORKER_ENABLED=false.
- Deploy the control plane disabled, provision one canary worker, execute a deterministic signed job, and disable the canary until the owner installs the worker runtime.

## Acceptance

- Invalid signature, stale timestamp, replayed nonce, wrong worker, wrong lease token, wrong capability, and oversized result are rejected.
- Two workers cannot lease the same job.
- Expired jobs requeue until max attempts, then fail.
- Completion is idempotent only for the same output hash.
- Tenant files never appear in lease payloads or logs.
- The application remains fully usable with the worker disabled or offline.
