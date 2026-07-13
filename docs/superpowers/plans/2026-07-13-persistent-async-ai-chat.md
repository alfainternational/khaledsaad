# Persistent Asynchronous AI Chat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a production-safe asynchronous local assistant with complete user-private chat history available from the Laravel web UI and Flutter application.

**Architecture:** Persist conversations and messages before dispatching a tenant-scoped `local_llm` intelligence job. Return HTTP 202 immediately, reconcile the pending assistant message when the private worker completes or fails, and let both clients poll durable message state while separately paging the complete history.

**Tech Stack:** Laravel 13, MySQL, Sanctum/session authentication, existing HMAC private worker, Blade/Vite JavaScript, Flutter/GetX/Dio, PHPUnit, Flutter test.

---

## File Map

- `database/migrations/2026_07_13_010000_create_ai_chat_history_tables.php`: durable conversation/message schema.
- `app/Domain/AI/Chat/Models/AiChatConversation.php`: conversation ownership, relations, and casts.
- `app/Domain/AI/Chat/Models/AiChatMessage.php`: durable user/assistant message state.
- `app/Domain/AI/Chat/ChatAnswerExtractor.php`: safely convert worker JSON to natural answer text.
- `app/Domain/AI/Chat/ChatPromptBuilder.php`: bounded 20-message prompt plus scoped knowledge context.
- `app/Domain/AI/Chat/AsyncChatService.php`: transactional send, idempotency, worker dispatch, and retry.
- `app/Domain/AI/Chat/ChatMessageLifecycle.php`: complete/fail/reconcile pending assistant messages exactly once.
- `app/Http/Controllers/Api/AiConversationController.php`: shared session-authenticated web contract.
- `app/Http/Controllers/Api/V1/AiConversationController.php`: Sanctum adapter using the same service and serializers.
- `app/Http/Resources/AiChatConversationResource.php`: stable conversation response shape.
- `app/Http/Resources/AiChatMessageResource.php`: stable status/poll response shape.
- `app/Domain/AI/Worker/WorkerResultApplier.php`: route `purpose=user_chat` results into chat lifecycle.
- `app/Domain/AI/Worker/WorkerJobLifecycle.php`: route worker failures into chat lifecycle.
- `routes/web.php`, `routes/api.php`: conversation and message routes.
- `resources/views/layouts/app.blade.php`, `resources/js/app.js`, `resources/css/app.css`: web history, new-chat, durable pending row, and polling.
- `mobile/lib/data/models/ai_chat_model.dart`: conversation/message/status models.
- `mobile/lib/data/repositories/ai_assist_repository.dart`: history and asynchronous message API.
- `mobile/lib/features/tool_runner/widgets/ai_chat_sheet.dart`: persisted history and polling UI.
- `mobile/lib/core/config/api_endpoints.dart`: conversation endpoint builders.

### Task 1: Durable Tenant-Scoped Chat Schema

**Files:**
- Create: `database/migrations/2026_07_13_010000_create_ai_chat_history_tables.php`
- Create: `app/Domain/AI/Chat/Models/AiChatConversation.php`
- Create: `app/Domain/AI/Chat/Models/AiChatMessage.php`
- Create: `tests/Feature/AI/Chat/AiChatHistorySchemaTest.php`

- [ ] **Step 1: Write the failing schema and relationship test**

```php
public function test_chat_history_is_owned_by_user_inside_workspace(): void
{
    [$user, $workspace] = $this->workspaceOwner();
    $conversation = AiChatConversation::query()->create([
        'public_id' => (string) Str::uuid(),
        'account_id' => $workspace->account_id,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'title' => 'اختبار السجل',
        'tool_key' => 'general',
        'last_message_at' => now(),
    ]);
    $message = $conversation->messages()->create([
        'public_id' => (string) Str::uuid(),
        'role' => 'user',
        'content' => 'رسالة محفوظة',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $this->assertTrue($message->conversation->is($conversation));
    $this->assertSame($user->id, $conversation->user_id);
}
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php artisan test tests/Feature/AI/Chat/AiChatHistorySchemaTest.php`

Expected: FAIL because the models/tables do not exist.

- [ ] **Step 3: Add the migration and models**

Create both tables with public UUIDs, account/workspace/user/project foreign keys, indexed `last_message_at`, message role/status checks represented by bounded strings, nullable job linkage, a unique `(conversation_id, client_request_id)`, JSON metadata, completion timestamps, and cascade deletion only from an explicitly deleted conversation. Add model fillables, casts, and relations.

- [ ] **Step 4: Run the schema test and verify GREEN**

Run: `php artisan test tests/Feature/AI/Chat/AiChatHistorySchemaTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_13_010000_create_ai_chat_history_tables.php app/Domain/AI/Chat/Models tests/Feature/AI/Chat/AiChatHistorySchemaTest.php
git commit -m "feat: persist private assistant conversations"
```

### Task 2: Answer Extraction And Worker Completion

**Files:**
- Create: `app/Domain/AI/Chat/ChatAnswerExtractor.php`
- Create: `app/Domain/AI/Chat/ChatMessageLifecycle.php`
- Modify: `app/Domain/AI/Worker/WorkerResultApplier.php`
- Modify: `app/Domain/AI/Worker/WorkerJobLifecycle.php`
- Create: `tests/Unit/AI/Chat/ChatAnswerExtractorTest.php`
- Create: `tests/Feature/AI/Chat/ChatWorkerLifecycleTest.php`

- [ ] **Step 1: Write failing extractor tests**

```php
#[DataProvider('answers')]
public function test_extracts_natural_answer(array $result, string $expected): void
{
    $this->assertSame($expected, app(ChatAnswerExtractor::class)->extract($result));
}

public static function answers(): array
{
    return [
        'answer' => [['answer' => 'الإجابة الطبيعية', '_model_name' => 'qwen3:1.7b'], 'الإجابة الطبيعية'],
        'text' => [['text' => 'نص بديل'], 'نص بديل'],
    ];
}
```

- [ ] **Step 2: Run extractor tests and verify RED**

Run: `php artisan test tests/Unit/AI/Chat/ChatAnswerExtractorTest.php`

Expected: FAIL because the extractor does not exist.

- [ ] **Step 3: Implement bounded extraction**

Accept only `answer`, `response`, or `text`, require valid UTF-8 non-empty text, trim it, cap it at the configured chat answer limit, and reject metadata-only or malformed results with `WORKER_RESULT_CHAT_INVALID`.

- [ ] **Step 4: Write failing completion, tenant mismatch, duplicate completion, and worker failure tests**

Build a queued assistant message linked to a `purpose=user_chat` job. Assert successful worker completion stores only the natural answer and model metadata, duplicate completion does not charge twice, tenant mismatch throws `WORKER_RESULT_TARGET_INVALID`, and worker failure changes the message to `failed`.

- [ ] **Step 5: Run lifecycle tests and verify RED**

Run: `php artisan test tests/Feature/AI/Chat/ChatWorkerLifecycleTest.php`

Expected: FAIL because chat jobs are ignored by the result applier.

- [ ] **Step 6: Implement lifecycle integration**

Inject `ChatMessageLifecycle` into `WorkerResultApplier` and `WorkerJobLifecycle`. Apply only `local_llm` jobs with `purpose=user_chat`, validate all scope IDs and target public ID, complete once inside a transaction, and map worker failures to a safe Arabic error while preserving internal diagnostics only on the intelligence job.

- [ ] **Step 7: Run focused tests and verify GREEN**

Run: `php artisan test tests/Unit/AI/Chat/ChatAnswerExtractorTest.php tests/Feature/AI/Chat/ChatWorkerLifecycleTest.php`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domain/AI/Chat app/Domain/AI/Worker tests/Unit/AI/Chat tests/Feature/AI/Chat/ChatWorkerLifecycleTest.php
git commit -m "feat: complete assistant messages from local worker"
```

### Task 3: Transactional Async Send And Prompt Window

**Files:**
- Create: `app/Domain/AI/Chat/ChatPromptBuilder.php`
- Create: `app/Domain/AI/Chat/AsyncChatService.php`
- Create: `tests/Feature/AI/Chat/AsyncChatServiceTest.php`

- [ ] **Step 1: Write failing service tests**

Cover: immediate durable user and queued assistant messages; `local_llm` job with account/workspace/project scope; exactly 20 previous completed messages in the prompt; deterministic title; duplicate `client_request_id` returns the existing pair; unavailable worker writes nothing; project outside workspace is rejected.

- [ ] **Step 2: Run service tests and verify RED**

Run: `php artisan test tests/Feature/AI/Chat/AsyncChatServiceTest.php`

Expected: FAIL because `AsyncChatService` does not exist.

- [ ] **Step 3: Implement prompt builder**

Build the server-owned system prompt from `WorkspaceGenerationContextBuilder`, append only the latest 20 completed user/assistant messages plus the current user message, and require a JSON object with one `answer` string. Never trust or persist client `system` messages.

- [ ] **Step 4: Implement transactional dispatcher**

Inside one database transaction, lock the conversation, enforce ownership, resolve optional project scope, handle idempotency, create both messages, create the intelligence job with `purpose=user_chat`, link the assistant message, update `last_message_at`, and return the pair. Use the configured interactive model and 256-token budget.

- [ ] **Step 5: Run service tests and verify GREEN**

Run: `php artisan test tests/Feature/AI/Chat/AsyncChatServiceTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/AI/Chat tests/Feature/AI/Chat/AsyncChatServiceTest.php
git commit -m "feat: queue assistant replies without blocking requests"
```

### Task 4: Authorized Web And Mobile API Contracts

**Files:**
- Create: `app/Http/Controllers/Api/AiConversationController.php`
- Create: `app/Http/Controllers/Api/V1/AiConversationController.php`
- Create: `app/Http/Resources/AiChatConversationResource.php`
- Create: `app/Http/Resources/AiChatMessageResource.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/AI/Chat/AiConversationWebApiTest.php`
- Create: `tests/Feature/AI/Chat/AiConversationMobileApiTest.php`

- [ ] **Step 1: Write failing API tests**

Assert list/create/show/send/poll contracts, HTTP 202 with `poll_after_ms`, newest-first conversation pagination, oldest-to-newest message rendering, cross-user 404 inside the same workspace, cross-workspace denial, and terminal job reconciliation during poll.

- [ ] **Step 2: Run API tests and verify RED**

Run: `php artisan test tests/Feature/AI/Chat/AiConversationWebApiTest.php tests/Feature/AI/Chat/AiConversationMobileApiTest.php`

Expected: FAIL with missing routes.

- [ ] **Step 3: Implement shared resources and controllers**

Use route-model lookup by `public_id` constrained by current workspace and authenticated user. Return only public IDs, role, content, status, safe error, timestamps, project/tool context, and pagination links. Add `Retry-After: 2` to queued poll responses.

- [ ] **Step 4: Register routes**

Keep the legacy synchronous `/ai/chat` routes. Add the five conversation routes under the existing `ai-assist` throttle in both the session group and `/api/v1/workspaces/{workspace_public_id}` Sanctum group.

- [ ] **Step 5: Run API tests and verify GREEN**

Run: `php artisan test tests/Feature/AI/Chat/AiConversationWebApiTest.php tests/Feature/AI/Chat/AiConversationMobileApiTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api app/Http/Resources routes tests/Feature/AI/Chat/AiConversation*ApiTest.php
git commit -m "feat: expose private assistant conversation APIs"
```

### Task 5: Web History And Polling Experience

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/css/app.css`
- Create: `tests/Feature/AI/Chat/AiChatWebWidgetTest.php`

- [ ] **Step 1: Write a failing rendered-widget contract test**

Assert authenticated layout output includes conversation index/create URL data, history and new-conversation controls, stable message-list and pending-row hooks, while guest output contains none of the private chat controls.

- [ ] **Step 2: Run the widget test and verify RED**

Run: `php artisan test tests/Feature/AI/Chat/AiChatWebWidgetTest.php`

Expected: FAIL because the new hooks do not exist.

- [ ] **Step 3: Implement compact history controls and states**

Add icon controls with accessible labels/tooltips, an unframed compact conversation list, a stable pending assistant row, retry state, and a new-conversation action. Keep dimensions fixed so loading state does not resize the panel.

- [ ] **Step 4: Replace synchronous send with 202 plus polling**

Load latest history on open, send one message with a generated `client_request_id`, render the returned durable messages, poll every two seconds with capped network backoff, replace by public message ID, stop at completed/failed, and reload the conversation safely after reopening.

- [ ] **Step 5: Build assets and run the widget test**

Run: `npm run build && php artisan test tests/Feature/AI/Chat/AiChatWebWidgetTest.php`

Expected: Vite build succeeds and test passes.

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/js/app.js resources/css/app.css tests/Feature/AI/Chat/AiChatWebWidgetTest.php public/build
git commit -m "feat: add durable assistant history to web"
```

### Task 6: Flutter History And Polling Experience

**Files:**
- Create: `mobile/lib/data/models/ai_chat_model.dart`
- Modify: `mobile/lib/data/repositories/ai_assist_repository.dart`
- Modify: `mobile/lib/core/config/api_endpoints.dart`
- Modify: `mobile/lib/features/tool_runner/widgets/ai_chat_sheet.dart`
- Create: `mobile/test/ai_chat_model_test.dart`
- Create: `mobile/test/ai_chat_sheet_test.dart`

- [ ] **Step 1: Write failing model/repository contract tests**

Parse conversation pagination and queued/completed/failed messages, and assert endpoint builders produce workspace-scoped conversation URLs.

- [ ] **Step 2: Run focused Flutter tests and verify RED**

Run: `flutter test test/ai_chat_model_test.dart test/ai_chat_sheet_test.dart`

Expected: FAIL because the models and async methods do not exist.

- [ ] **Step 3: Implement models and repository methods**

Add list/create/show/send/poll methods. Preserve existing synchronous methods for compatibility, but route the chat sheet only through the asynchronous methods.

- [ ] **Step 4: Implement sheet lifecycle**

Load the latest persisted conversation, add history and new controls, render messages by public ID, poll pending replies with a cancellable timer, cancel on dispose/conversation switch, and resume pending polling when reopening.

- [ ] **Step 5: Run Flutter tests and analyzer**

Run: `flutter test test/ai_chat_model_test.dart test/ai_chat_sheet_test.dart && flutter analyze`

Expected: all tests pass and analyzer reports no issues.

- [ ] **Step 6: Commit**

```bash
git add mobile/lib mobile/test/ai_chat_model_test.dart mobile/test/ai_chat_sheet_test.dart
git commit -m "feat: add persistent assistant history to mobile"
```

### Task 7: Regression, Review, GitHub Merge, And Production Rollout

**Files:**
- Modify: `docs/platform/KNOWLEDGE_FOUNDATION_RUNBOOK.md`
- Modify: PR 6 and `main` through GitHub after all checks pass.

- [ ] **Step 1: Run focused PHP regression tests**

Run: `php artisan test tests/Feature/AI/Chat tests/Feature/AI/Worker tests/Unit/AI/Chat`

Expected: PASS.

- [ ] **Step 2: Run the full PHP and worker suites**

Run: `php artisan test`

Run: `D:\Python\python.exe -m pytest worker/private_intelligence_worker/tests -q`

Expected: all applicable tests pass; only documented database-specific skips are allowed.

- [ ] **Step 3: Run full Flutter verification and release build**

Run from `mobile`: `flutter test && flutter analyze && flutter build apk --release`

Expected: all tests/analyzer pass and `mobile/build/app/outputs/flutter-apk/app-release.apk` exists.

- [ ] **Step 4: Review diff and update runbook**

Document asynchronous chat health checks, pending/failed message queries, safe retry behavior, migration, cache clearing, and production canary cleanup. Run `git diff --check` and inspect every changed file.

- [ ] **Step 5: Commit final documentation and push PR 6 branch**

```bash
git add docs/platform/KNOWLEDGE_FOUNDATION_RUNBOOK.md
git commit -m "docs: operate persistent assistant chat"
git push origin feature/knowledge-foundation
```

- [ ] **Step 6: Merge GitHub PR 6 into `main`**

Confirm PR head equals the verified local SHA, mark ready for review, merge it, and fetch GitHub's resulting `main` SHA. Do not call the rollout complete while PR 6 remains open or draft.

- [ ] **Step 7: Deploy the exact merged `main` SHA**

Deploy using the existing cPanel process, run `php artisan migrate --force`, clear route/config/view caches, refresh LiteSpeed PHP workers, and verify `php artisan knowledge:health --json` reports an online worker and no failed jobs.

- [ ] **Step 8: Run authenticated production canary**

Create a temporary user-scoped conversation, assert send returns HTTP 202 within five seconds, poll to `completed`, verify natural Arabic content, reopen full history through web and mobile API contracts, assert another user receives 404, and clean only the canary records/tokens/accounts.

- [ ] **Step 9: Verify source parity**

Confirm GitHub `main`, deployed release metadata, and the production code SHA are identical. Confirm the public site returns HTTP 200 and the Windows worker remains running.

