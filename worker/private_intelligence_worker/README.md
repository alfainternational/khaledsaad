# Private Intelligence Worker

This worker makes outbound HTTPS requests only. The server never connects to the owner machine.

## Requirements

- Python 3.11 or newer.
- Ollama for local_llm jobs.
- Tesseract with Arabic data for image OCR.
- pdftotext for PDF extraction.
- No Python packages are required.

Provision credentials on the Laravel server with:

    php artisan private-worker:provision "Owner Laptop" \
      --capability=deterministic_echo \
      --capability=ocr \
      --capability=document_extract \
      --capability=local_llm --json

Store the returned ID and one-time secret in local environment variables based on .env.example. Never upload the populated file.

Run one scheduler-friendly poll:

    python worker.py --once

Run continuously on a private machine:

    python worker.py

The worker verifies every leased job signature before execution. Logs contain job IDs and error codes only, not prompts, file contents, secrets, or server error bodies.
