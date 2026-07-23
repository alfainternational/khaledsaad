@echo off
REM ============================================================
REM  Queue worker - keep this window OPEN while using the app.
REM
REM  Without it every analysis stays stuck at "processing",
REM  because Laravel only writes the job into the `jobs` table
REM  and waits for a separate process to run it.
REM
REM  Uses `queue:listen` (NOT `queue:work`): it reboots the
REM  framework before every job, so it picks up PHP code changes
REM  automatically - models, services, config/*.php files, jobs.
REM  No manual `php artisan queue:restart` after editing code.
REM
REM  ONE exception: .env values are read into the process
REM  environment when this window starts, so changing .env still
REM  needs closing and reopening THIS window (or Ctrl+C then run
REM  again). Code edits do NOT.
REM
REM  The per-job boot cost (~100ms) is negligible next to a
REM  multi-minute AI analysis, and worth it for zero-restart dev.
REM
REM  --timeout=900 matches DB_QUEUE_RETRY_AFTER in .env and must
REM  stay larger than the longest run (about five minutes).
REM
REM  NOTE: keep this file ASCII-only. cmd.exe misparses Arabic
REM  characters in .bat files and breaks the commands.
REM ============================================================

cd /d "%~dp0"

title KhaledSaad Queue Worker (auto-reload code)

echo.
echo   Queue worker running. Leave this window open.
echo   Code changes are picked up automatically (no restart).
echo   Only .env changes need reopening this window.
echo   Stop with: Ctrl+C
echo.

:loop
php artisan queue:listen --tries=1 --timeout=900 --sleep=3
echo.
echo   Worker stopped unexpectedly. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
