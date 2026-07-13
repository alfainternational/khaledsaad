# Persistent Asynchronous AI Chat Design

## Objective

Make the intelligent assistant reliably answer in production without keeping an HTTP request open for local-model generation, while preserving the complete private chat history for each user and making that history accessible from both the web application and the Flutter application.

## Confirmed Production Problem

- The configured local gateway and `qwen3:1.7b` worker complete generation successfully.
- A real authenticated production chat request returned HTTP 200 but took 101.6 seconds end to end.
- The Flutter client has a 30-second receive timeout, so it always abandons slower successful responses.
- The browser does not impose a JavaScript timeout, but the long unresolved request appears broken and remains vulnerable to hosting or proxy request limits.
- The local worker returns structured JSON. The current chat path exposes that JSON string instead of consistently extracting a natural-language answer.
- Historical failures before the local rollout came from external provider rate limits and oversized requests. The new design must not reintroduce an external generation dependency.

## Architecture

Chat generation becomes an asynchronous, durable workflow:

1. The client creates or opens a conversation owned by the authenticated user in the active workspace.
2. Sending a message stores the user message and a pending assistant message in one database transaction.
3. The transaction creates a tenant-scoped `local_llm` intelligence job whose payload identifies the pending assistant message.
4. The API immediately returns HTTP 202 with the conversation and pending message identifiers.
5. The web and Flutter clients poll the pending message endpoint with bounded backoff.
6. The private worker completes the job. `WorkerResultApplier` validates the tenant and message target, extracts the answer text, and marks the assistant message completed.
7. Worker failure marks the pending message failed with a stable, user-safe error code. A retry creates a new pending assistant message without deleting history.

The existing synchronous endpoint remains available for older clients during transition. New web and mobile code uses the asynchronous conversation endpoints.

## Persistence Model

### `ai_chat_conversations`

- `public_id`: public UUID used in API routes.
- `account_id`, `workspace_id`, `user_id`: mandatory ownership scope.
- `project_id`: optional project context, validated inside the same workspace.
- `tool_key`: optional tool context.
- `title`: deterministic title derived from the first user message; no extra model call.
- `last_message_at`: indexed conversation ordering.
- timestamps.

### `ai_chat_messages`

- `public_id`: public UUID.
- `conversation_id`: parent conversation.
- `role`: `user` or `assistant`.
- `content`: full original text; nullable only while an assistant response is pending.
- `status`: `completed`, `queued`, `processing`, or `failed`.
- `intelligence_job_id`: nullable link to the worker job.
- `client_request_id`: optional idempotency key unique within a conversation.
- `error_code`, `error_message`: stable failure state without exposing internal diagnostics.
- `meta_json`: bounded model and completion metadata.
- `completed_at` and timestamps.

There is no automatic chat-history deletion or pruning. Worker jobs may be maintained independently after the completed answer and model metadata have been copied to the chat message.

## Privacy And Authorization

- Every query must match both the active `workspace_id` and authenticated `user_id`.
- A workspace member cannot read another member's conversations, even in the same workspace.
- Project context is accepted only when the project belongs to the same active workspace.
- Worker result application verifies the job account, workspace, project, conversation, and target message before writing content.
- Public IDs are used in URLs; sequential database IDs are never exposed.
- Stored history is treated as private workspace data and is not included in global knowledge ingestion.

## Prompt And Context Policy

- The full chat remains stored and retrievable.
- Generation uses only the latest bounded conversation window, initially 20 completed messages, plus the scoped workspace/project knowledge context.
- The current user message is always included.
- System and knowledge instructions remain server-controlled; client-supplied `system` messages are not persisted or trusted.
- The worker remains locked to the local allowlisted fast model for interactive chat.
- The result extractor accepts bounded structured keys such as `answer`, `response`, and `text`, removes internal model metadata, and stores only the natural-language answer.

## API Contract

Equivalent session-authenticated web routes and Sanctum workspace routes expose:

- `GET /ai/conversations`: paginated conversations for the current user.
- `POST /ai/conversations`: create a new conversation.
- `GET /ai/conversations/{conversation}`: conversation metadata and paginated messages.
- `POST /ai/conversations/{conversation}/messages`: store and queue a message; returns HTTP 202.
- `GET /ai/conversations/{conversation}/messages/{message}`: poll status and retrieve completed content.

The message creation response includes `poll_after_ms`. Poll responses use stable statuses and do not expose worker leases, secrets, prompts, or raw result JSON.

## Web Experience

- The existing assistant panel gains compact history and new-conversation controls.
- Opening the panel loads the latest conversation and its recent messages.
- Sending shows the user message and a stable pending assistant row immediately.
- Polling updates that row in place; it does not duplicate messages or shift the panel layout.
- The history view lists the user's conversations ordered by latest activity and supports loading older conversations.
- Failed responses show a clear retry action while preserving both the original question and failed attempt.

## Flutter Experience

- The chat sheet loads the latest persisted conversation instead of starting with an empty local array.
- A history action opens the user's conversations, and a new action starts a fresh conversation.
- Sending returns immediately and polls by message public ID, so the global 30-second Dio timeout is no longer relevant to model generation.
- Polling stops when the sheet is disposed and resumes safely when the conversation is reopened.
- Models and repository methods mirror the API statuses and pagination contract.

## Failure Handling

- No online local worker: message creation fails before storing a false pending response, with `AI_WORKER_UNAVAILABLE`.
- Worker rejects or fails a job: assistant message becomes `failed` with a safe Arabic message.
- Poll network failure: the pending message remains durable and can be recovered by reopening the conversation.
- Duplicate send: `client_request_id` returns the existing user/pending assistant pair.
- Stale queued job: scheduled worker maintenance maps terminal job state to the chat message; polling also reconciles terminal state defensively.
- Credit consumption occurs once, after a completed assistant message, and is guarded against duplicate worker completion callbacks.

## Verification

- Feature tests cover creation, listing, pagination, ownership isolation, project scope, idempotency, enqueue response, completion, failure, retry, and credit charging.
- Worker protocol tests cover tenant mismatch, malformed result JSON, natural-text extraction, and duplicate completion.
- JavaScript tests or focused DOM tests cover pending-to-completed replacement and history loading where the repository supports them.
- Flutter unit/widget tests cover repository parsing, polling completion/failure, conversation switching, and disposal.
- Production verification creates a temporary authenticated conversation, confirms a fast HTTP 202, waits for a completed local answer, reopens the conversation from both API contracts, verifies cross-user denial, and removes only the canary records afterward.

## Rollout And Source Of Truth

1. Implement and verify on `feature/knowledge-foundation` because that branch contains the deployed private-worker foundation.
2. Push the verified commit to GitHub PR 6.
3. Mark PR 6 ready, merge it into `main`, and verify the resulting `main` SHA.
4. Deploy that exact `main` SHA to production, run migrations, clear route/config/view caches, and refresh PHP workers.
5. Run the authenticated production canary and confirm the public site remains healthy.
6. Build the updated Flutter release artifact from the merged `main` source and verify its chat workflow against production.
