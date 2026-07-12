# Private Intelligence Worker

This worker makes outbound HTTPS requests only. The server never connects to the owner machine.

## Requirements

- Python 3.11 or newer.
- Ollama for local_llm and embeddings jobs. Pull the server-configured embedding model first (default: `nomic-embed-text`).
- Tesseract with Arabic data for image OCR.
- pdftotext for PDF extraction.
- No Python packages are required.

Provision credentials on the Laravel server with:

    php artisan private-worker:provision "Owner Laptop" \
      --capability=deterministic_echo \
      --capability=ocr \
      --capability=document_extract \
      --capability=embeddings \
      --capability=local_llm --json

Store the returned ID and one-time secret in local environment variables based on .env.example. Never upload the populated file.

Run one scheduler-friendly poll:

    python worker.py --once

Run continuously on a private machine:

    python worker.py

On Windows, `run_windows_worker.ps1` reads a DPAPI-encrypted credential file and performs one bounded poll. It is suitable for a one-minute Task Scheduler entry; the worker secret is never stored as plaintext or passed on the task command line.

The worker verifies every leased job signature before execution. Logs contain job IDs and error codes only, not prompts, file contents, secrets, or server error bodies.
