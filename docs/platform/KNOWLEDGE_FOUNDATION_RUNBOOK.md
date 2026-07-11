# Knowledge Foundation Production Runbook

This runbook deploys the structured knowledge foundation to shared cPanel hosting without replacing the existing JSON memory until every gate passes.

## Preconditions

- Keep PHP memory at or below the hosting limit; commands use chunks and count queries.
- Confirm the scheduler runs once per minute: `php artisan schedule:run`.
- Keep these flags disabled for the initial deployment:

```dotenv
AI_KNOWLEDGE_STRUCTURED_STORE=false
AI_KNOWLEDGE_DUAL_WRITE=false
AI_KNOWLEDGE_PROJECT_SYNC=false
```

- Record the current commit, database size, failed-job count, and `/up` status.
- Back up the database and `storage/app/ai-knowledge` before migration.

## 1. Deploy With Flags Disabled

Upload the release using `deploy/cpanel-push.sh`, including migrations, commands, domain classes, configuration, routes, tests excluded from production if desired, and updated documentation. Then run on the server:

```bash
php artisan down --retry=60
php artisan config:clear
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
php artisan knowledge:health --json
php artisan queue:failed
```

Gate: `/up` returns HTTP 200, JSON health parses, `failed_jobs` does not increase, and the existing JSON intelligence remains functional. If migration fails, restore the database backup and the deployment backup before bringing traffic back.

## 2. Import Existing JSON Memory

Run the import twice:

```bash
php artisan knowledge:import-legacy
php artisan knowledge:import-legacy
php artisan knowledge:health --json
```

Gate: the second run reports only unchanged valid memories, counts remain stable, `pending_reconciliations` is zero, and no credentials appear in stored chunks or logs.

## 3. Enable Structured Storage

Set only:

```dotenv
AI_KNOWLEDGE_STRUCTURED_STORE=true
AI_KNOWLEDGE_DUAL_WRITE=false
AI_KNOWLEDGE_PROJECT_SYNC=false
```

Then run:

```bash
php artisan config:cache
php artisan knowledge:health --json
php artisan queue:failed
```

Gate: no failed jobs, no stale documents, no pending reconciliation, memory stays within the hosting limit, and `/up` returns 200. Roll this flag back to `false` on any regression.

## 4. Enable Dual Write

Set `AI_KNOWLEDGE_DUAL_WRITE=true`, rebuild config cache, perform one harmless knowledge-producing workflow, and run:

```bash
php artisan config:cache
php artisan knowledge:health --json
php artisan queue:failed
```

Compare the JSON file with its structured source. The source must have one active document and the same tenant scope. `pending_reconciliations` must return to zero. If it does not, disable dual write; JSON remains authoritative.

## 5. Synchronize One Real Project

Choose a low-risk real project ID and keep the schedule disabled:

```bash
php artisan knowledge:sync-projects --project=PROJECT_ID
php artisan knowledge:sync-projects --project=PROJECT_ID
php artisan knowledge:health --json
```

Gate: the second run is unchanged, one project-scoped source is active, its content is searchable only inside the same account/workspace/project, Arabic text is intact, and no credential or signed URL value is stored.

## 6. Enable Daily Project Synchronization

Set `AI_KNOWLEDGE_PROJECT_SYNC=true` and run:

```bash
php artisan config:cache
php artisan schedule:list
php artisan knowledge:health --json
```

Confirm `knowledge-project-sync` appears at `03:15` and is protected by `withoutOverlapping`. Monitor logs, memory, execution time, failed jobs, stale documents, and pending reconciliation for 24 hours.

## Health Interpretation

- `sources`, `documents`, `chunks`: inventory counts; unexpected drops or spikes require investigation.
- `candidate_claims`: claims awaiting review; growth is expected only when claim extraction is enabled.
- `failed_jobs`: failed records in `intelligence_jobs`; must not increase during rollout.
- `pending_reconciliations`: authoritative JSON writes not yet reflected in structured storage; should return to zero.
- `stale_documents`: active documents past `valid_until`; must be zero before enabling the next flag.

## Rollback

Disable flags in reverse order and rebuild config cache:

```dotenv
AI_KNOWLEDGE_PROJECT_SYNC=false
AI_KNOWLEDGE_DUAL_WRITE=false
AI_KNOWLEDGE_STRUCTURED_STORE=false
```

```bash
php artisan config:cache
php artisan knowledge:health --json
php artisan queue:failed
```

Disabling all flags restores the original JSON-based runtime path. Do not delete structured tables during an incident; preserve them for diagnosis. Restore the database only for migration corruption or confirmed data damage.

## APP_KEY Rotation

Before changing `APP_KEY`, put the previous key in `AI_KNOWLEDGE_MAPPING_PREVIOUS_KEYS`, rotate the key, rebuild config cache, and verify tenant deletion mappings. Remove the previous key only after all legacy mappings have been rewritten under the new key.
## Text Upload And Retrieval Rollout

Keep these flags disabled while the upload migration is applied:

    AI_KNOWLEDGE_UPLOAD_PROCESSING=false
    AI_KNOWLEDGE_RETRIEVAL=false

Run the migration, then verify knowledge:health reports zero stored, failed, and unlinked uploads. Enable upload processing first and confirm knowledge:process-uploads exits successfully. Upload one small UTF-8 text file to a canary project through the project knowledge API and verify it becomes indexed.

Enable retrieval only after the canary query returns the uploaded marker with a KB citation. Confirm that the same query in another project does not return it. Retrieval can be disabled immediately without removing uploads or indexed documents.

Do not enable config caching on hosting environments that replace database environment values with placeholders. Use config:clear after each flag change.

## Private Worker Control Plane

Deploy the worker migration and routes with AI_PRIVATE_WORKER_ENABLED=false. Verify knowledge:health reports zero active and online workers before provisioning.

Provision a worker:

    php artisan private-worker:provision "Owner Laptop" \
      --capability=deterministic_echo \
      --capability=ocr \
      --capability=document_extract \
      --capability=local_llm --json

The command prints the secret once. Store it only on the private machine. The database contains Laravel-encrypted ciphertext, and HTTP logs must never contain the secret.

Enable the control plane with AI_PRIVATE_WORKER_ENABLED=true and clear configuration. Run the Python worker with --once for a deterministic canary. Confirm:

- the request is accepted only with a fresh timestamp, nonce, and HMAC;
- one queued job becomes completed;
- replaying the signed request is rejected;
- knowledge:health reports one online worker;
- private-worker:maintain is scheduled every five minutes.

Disable a compromised or retired worker immediately:

    php artisan private-worker:disable WORKER_ID

This releases active leases. Rotate by provisioning a new worker; never reuse the old secret. Set AI_PRIVATE_WORKER_ENABLED=false for a global shutdown. The existing deterministic and external fallbacks remain available.

For OCR, install Tesseract Arabic language data and pdftotext on the private machine. DOCX and XLSX extraction use the Python standard library. Ollama remains bound to localhost and is never exposed to the public network.
