#!/usr/bin/env python3
"""Outbound-only private intelligence worker using only the Python standard library."""

from __future__ import annotations

import argparse
import hashlib
import hmac
import http.client
import json
import os
import pathlib
import ssl
import socket
import subprocess
import shutil
import sys
import tempfile
import threading
import time
import urllib.error
import urllib.request
import urllib.parse
import zipfile
from typing import Any
from xml.etree import ElementTree
from document_extractors import extract_docx, extract_image, extract_pdf, extract_xlsx


def canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), sort_keys=True)


def request_signature(secret: str, method: str, path: str, timestamp: int, nonce: str, body: bytes) -> str:
    body_hash = hashlib.sha256(body).hexdigest()
    canonical = "\n".join((method.upper(), "/" + path.lstrip("/"), str(timestamp), nonce, body_hash))
    return hmac.new(secret.encode(), canonical.encode(), hashlib.sha256).hexdigest()


def runtime_manifest() -> dict[str, Any]:
    tools: dict[str, str] = {}
    for tool in ("tesseract", "pdftotext", "pdfinfo", "pdftoppm"):
        path = shutil.which(tool)
        if not path:
            continue
        completed = subprocess.run(
            [path, "--version"], capture_output=True, text=True, encoding="utf-8", timeout=10, check=False
        )
        output = (completed.stdout or completed.stderr).strip().splitlines()
        tools[tool] = output[0][:120] if output else "available"
    languages = sorted(set(split_env("AI_WORKER_OCR_LANGUAGE", "ara+eng")))
    if len(languages) == 1 and "+" in languages[0]:
        languages = sorted(part for part in languages[0].split("+") if part)
    return {
        "python": ".".join(map(str, sys.version_info[:3])),
        "tools": tools,
        "ocr_languages": languages,
    }


class ProtocolError(RuntimeError):
    pass


class LeaseHeartbeat:
    def __init__(self, send: Any, interval: float = 45.0) -> None:
        self.send = send
        self.interval = max(0.01, interval)
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None
        self._error: Exception | None = None

    def start(self) -> None:
        self._thread = threading.Thread(target=self._run, name="worker-lease-heartbeat", daemon=True)
        self._thread.start()

    def stop(self) -> None:
        self._stop.set()
        if self._thread is not None:
            self._thread.join(timeout=max(1.0, self.interval + 1.0))

    def raise_if_failed(self) -> None:
        if self._error is not None:
            raise ProtocolError(f"lease heartbeat failed: {self._error}") from self._error

    def _run(self) -> None:
        progress = 10
        while not self._stop.wait(self.interval):
            try:
                self.send(progress)
                progress = min(90, progress + 10)
            except Exception as error:
                self._error = error
                self._stop.set()


class Worker:
    def __init__(self) -> None:
        self.base_url = required("AI_WORKER_SERVER_URL").rstrip("/")
        self.worker_id = required("AI_WORKER_ID")
        self.secret = required("AI_WORKER_SECRET")
        self.capabilities = split_env("AI_WORKER_CAPABILITIES", "deterministic_echo")
        self.version = os.getenv("AI_WORKER_VERSION", "python-stdlib-1")
        self.ollama_url = os.getenv("AI_WORKER_OLLAMA_URL", "http://127.0.0.1:11434").rstrip("/")
        self.ollama_model = os.getenv("AI_WORKER_OLLAMA_MODEL", "qwen2.5:7b")
        self.llm_max_tokens = max(64, min(2048, int(os.getenv("AI_WORKER_LLM_MAX_TOKENS", "768"))))
        self.timeout = int(os.getenv("AI_WORKER_HTTP_TIMEOUT", "120"))
        self.http_host = os.getenv("AI_WORKER_HTTP_HOST", "").strip()
        self.tls_check_hostname = os.getenv("AI_WORKER_TLS_CHECK_HOSTNAME", "true").lower() not in {
            "0", "false", "no", "off"
        }
        self.tls_server_name = os.getenv("AI_WORKER_TLS_SERVER_NAME", "").strip()
        self.ssl_context: ssl.SSLContext | None = None
        if self.base_url.startswith("https://") and not self.tls_check_hostname:
            self.ssl_context = ssl.create_default_context()
            self.ssl_context.check_hostname = False

    def signed_request(
        self,
        method: str,
        path: str,
        payload: dict[str, Any] | None = None,
        extra_headers: dict[str, str] | None = None,
    ) -> tuple[int, bytes, dict[str, str]]:
        body = canonical_json(payload).encode() if payload is not None else b""
        timestamp = int(time.time())
        nonce = os.urandom(24).hex()
        headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-Worker-Id": self.worker_id,
            "X-Worker-Timestamp": str(timestamp),
            "X-Worker-Nonce": nonce,
            "X-Worker-Signature": request_signature(
                self.secret, method, "/api/v1/private-worker" + path, timestamp, nonce, body
            ),
            "X-Worker-Version": self.version,
        }
        headers.update(extra_headers or {})
        if self.http_host:
            headers["Host"] = self.http_host
        request = urllib.request.Request(
            self.base_url + "/api/v1/private-worker" + path,
            data=body if method.upper() != "GET" else None,
            headers=headers,
            method=method.upper(),
        )
        if self.tls_server_name:
            return self.tunneled_https_request(request, body)
        try:
            with urllib.request.urlopen(request, timeout=self.timeout, context=self.ssl_context) as response:
                return response.status, response.read(), dict(response.headers.items())
        except urllib.error.HTTPError as error:
            content = error.read()
            raise ProtocolError(f"server rejected request with HTTP {error.code}: {safe_error(content)}") from error

    def tunneled_https_request(
        self, request: urllib.request.Request, body: bytes
    ) -> tuple[int, bytes, dict[str, str]]:
        target = urllib.parse.urlsplit(self.base_url)
        if target.scheme != "https" or not target.hostname or not target.port:
            raise ProtocolError("TLS server name override requires an explicit HTTPS tunnel port")
        context = ssl.create_default_context()
        connection = http.client.HTTPSConnection(
            self.tls_server_name, 443, timeout=self.timeout, context=context
        )
        connection._create_connection = lambda _address, timeout, source_address=None: socket.create_connection(  # type: ignore[method-assign]
            (target.hostname, target.port), timeout, source_address
        )
        try:
            connection.request(
                request.get_method(),
                request.selector,
                body=body if request.get_method() != "GET" else None,
                headers=dict(request.header_items()),
            )
            response = connection.getresponse()
            content = response.read()
            if response.status >= 400:
                raise ProtocolError(
                    f"server rejected request with HTTP {response.status}: {safe_error(content)}"
                )
            return response.status, content, dict(response.getheaders())
        finally:
            connection.close()

    def once(self) -> bool:
        status, body, _ = self.signed_request(
            "POST",
            "/lease",
            {"capabilities": self.capabilities, "version": self.version, "runtime": runtime_manifest()},
        )
        if status == 204:
            return False
        envelope = json.loads(body)["data"]
        job = envelope["job"]
        expected = hmac.new(
            self.secret.encode(), canonical_json(job).encode(), hashlib.sha256
        ).hexdigest()
        if not hmac.compare_digest(expected, envelope["job_signature"]):
            raise ProtocolError("job envelope signature is invalid")

        lease_token = envelope["lease_token"]
        public_id = job["public_id"]
        print(f"leased job={public_id} type={job['type']} attempt={job['attempt']}", flush=True)
        try:
            self.signed_request(
                "POST", f"/jobs/{public_id}/heartbeat", {"lease_token": lease_token, "progress": 5}
            )
            heartbeat = LeaseHeartbeat(
                lambda progress: self.signed_request(
                    "POST",
                    f"/jobs/{public_id}/heartbeat",
                    {"lease_token": lease_token, "progress": progress},
                ),
                interval=float(os.getenv("AI_WORKER_HEARTBEAT_SECONDS", "45")),
            )
            heartbeat.start()
            try:
                result = self.execute(job, lease_token)
            finally:
                heartbeat.stop()
            heartbeat.raise_if_failed()
            self.signed_request(
                "POST",
                f"/jobs/{public_id}/complete",
                {
                    "lease_token": lease_token,
                    "result": result,
                    "model_name": result.pop("_model_name", None),
                    "model_version": result.pop("_model_version", None),
                },
            )
            print(f"completed job={public_id}", flush=True)
        except Exception as error:
            self.signed_request(
                "POST",
                f"/jobs/{public_id}/fail",
                {
                    "lease_token": lease_token,
                    "error_code": error_code(error),
                    "message": str(error)[:800],
                },
            )
            print(f"failed job={public_id} code={error_code(error)}", flush=True)
        return True

    def execute(self, job: dict[str, Any], lease_token: str) -> dict[str, Any]:
        job_type = job["type"]
        if job_type == "deterministic_echo":
            return {"echo": job.get("payload", {}), "worker_version": self.version}
        if job_type == "local_llm":
            return self.local_llm(job.get("payload", {}))
        if job_type == "embeddings":
            return self.embeddings(job.get("payload", {}))
        if job_type in {"ocr", "document_extract"}:
            return self.extract_document(job, lease_token)
        raise RuntimeError(f"unsupported capability: {job_type}")

    def embeddings(self, payload: dict[str, Any]) -> dict[str, Any]:
        items = payload.get("items")
        model = str(payload.get("model_name", "")).strip()
        version = str(payload.get("model_version", "")).strip()
        if not model or not version or not isinstance(items, list) or not 1 <= len(items) <= 64:
            raise RuntimeError("embedding job contract is invalid")
        texts: list[str] = []
        for item in items:
            if not isinstance(item, dict) or not isinstance(item.get("text"), str) or not item["text"].strip():
                raise RuntimeError("embedding item is invalid")
            texts.append(item["text"])
        request_body = canonical_json({"model": model, "input": texts, "truncate": True}).encode()
        request = urllib.request.Request(
            self.ollama_url + "/api/embed",
            data=request_body,
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        with urllib.request.urlopen(request, timeout=self.timeout) as response:
            decoded = json.loads(response.read())
        vectors = decoded.get("embeddings")
        if not isinstance(vectors, list) or len(vectors) != len(items):
            raise RuntimeError("local embedding model returned an invalid batch")
        output_vectors = []
        for item, vector in zip(items, vectors, strict=True):
            identity = {key: value for key, value in item.items() if key != "text"}
            identity["vector"] = vector
            output_vectors.append(identity)
        return {
            "model_name": model,
            "model_version": version,
            "vectors": output_vectors,
        }

    def local_llm(self, payload: dict[str, Any]) -> dict[str, Any]:
        prompt = str(payload.get("prompt", "")).strip()
        if not prompt:
            raise RuntimeError("local model prompt is empty")
        request_body = canonical_json(
            {
                "model": self.ollama_model,
                "stream": False,
                "format": "json",
                "think": False,
                "options": {"num_predict": self.llm_max_tokens},
                "prompt": prompt,
            }
        ).encode()
        request = urllib.request.Request(
            self.ollama_url + "/api/generate",
            data=request_body,
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        with urllib.request.urlopen(request, timeout=self.timeout) as response:
            decoded = json.loads(response.read())
        text = str(decoded.get("response", "")).strip()
        if not text:
            raise RuntimeError("local model returned an empty response")
        try:
            structured = json.loads(text)
        except json.JSONDecodeError:
            structured = {"text": text}
        structured["_model_name"] = self.ollama_model
        structured["_model_version"] = str(decoded.get("model", self.ollama_model))
        return structured

    def extract_document(self, job: dict[str, Any], lease_token: str) -> dict[str, Any]:
        public_id = job["public_id"]
        status, content, headers = self.signed_request(
            "GET",
            f"/jobs/{public_id}/input",
            extra_headers={"X-Worker-Lease-Token": lease_token},
        )
        if status != 200:
            raise RuntimeError("input download failed")
        expected_sha256 = str(job.get("payload", {}).get("expected_sha256", "")).strip()
        if expected_sha256 and not hmac.compare_digest(expected_sha256, hashlib.sha256(content).hexdigest()):
            raise RuntimeError("downloaded input hash does not match its job contract")
        mime = str(job.get("payload", {}).get("mime_type", headers.get("Content-Type", ""))).split(";")[0]
        contract = job.get("payload", {}).get("extraction_contract", {})
        if not isinstance(contract, dict) or contract.get("version") != "v2":
            raise RuntimeError("document extraction contract is invalid")
        suffix = pathlib.Path(str(job.get("payload", {}).get("original_name", "input.bin"))).suffix
        with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as handle:
            handle.write(content)
            temporary = pathlib.Path(handle.name)
        try:
            if mime.endswith("wordprocessingml.document"):
                return extract_docx(temporary, contract)
            if mime.endswith("spreadsheetml.sheet"):
                return extract_xlsx(temporary, contract)
            if mime == "application/pdf":
                return extract_pdf(temporary, contract)
            if mime.startswith("image/"):
                return extract_image(temporary, contract)
            text = extract_text(temporary, mime)
        finally:
            temporary.unlink(missing_ok=True)
        if not text.strip():
            raise RuntimeError("document extraction returned no text")
        return {"text": text.strip(), "language": os.getenv("AI_WORKER_OCR_LANGUAGE", "ara+eng")}


def extract_text(path: pathlib.Path, mime: str) -> str:
    if mime == "application/pdf":
        return command_text(["pdftotext", "-layout", str(path), "-"])
    if mime.startswith("image/"):
        return command_text(
            ["tesseract", str(path), "stdout", "-l", os.getenv("AI_WORKER_OCR_LANGUAGE", "ara+eng")]
        )
    if mime.endswith("wordprocessingml.document"):
        return docx_text(path)
    if mime.endswith("spreadsheetml.sheet"):
        return xlsx_text(path)
    return path.read_text(encoding="utf-8")


def command_text(command: list[str]) -> str:
    completed = subprocess.run(
        command,
        capture_output=True,
        text=True,
        encoding="utf-8",
        timeout=int(os.getenv("AI_WORKER_COMMAND_TIMEOUT", "180")),
        check=False,
    )
    if completed.returncode != 0:
        raise RuntimeError(f"local extractor exited with code {completed.returncode}")
    return completed.stdout


def docx_text(path: pathlib.Path) -> str:
    with zipfile.ZipFile(path) as archive:
        root = ElementTree.fromstring(archive.read("word/document.xml"))
    return "\n".join(text for text in root.itertext() if text.strip())


def xlsx_text(path: pathlib.Path) -> str:
    rows: list[str] = []
    with zipfile.ZipFile(path) as archive:
        shared: list[str] = []
        if "xl/sharedStrings.xml" in archive.namelist():
            root = ElementTree.fromstring(archive.read("xl/sharedStrings.xml"))
            shared = ["".join(node.itertext()) for node in root]
        for name in sorted(n for n in archive.namelist() if n.startswith("xl/worksheets/sheet") and n.endswith(".xml")):
            root = ElementTree.fromstring(archive.read(name))
            values: list[str] = []
            for cell in root.iter():
                if not cell.tag.endswith("}c"):
                    continue
                value_node = next((node for node in cell if node.tag.endswith("}v")), None)
                if value_node is None or value_node.text is None:
                    continue
                value = value_node.text
                if cell.attrib.get("t") == "s" and value.isdigit() and int(value) < len(shared):
                    value = shared[int(value)]
                values.append(value)
            if values:
                rows.append(" | ".join(values))
    return "\n".join(rows)


def required(name: str) -> str:
    value = os.getenv(name, "").strip()
    if not value:
        raise SystemExit(f"missing required environment variable: {name}")
    return value


def split_env(name: str, default: str) -> list[str]:
    return [item.strip() for item in os.getenv(name, default).split(",") if item.strip()]


def safe_error(content: bytes) -> str:
    try:
        payload = json.loads(content)
        return str(payload.get("code", "SERVER_ERROR"))
    except Exception:
        return "SERVER_ERROR"


def error_code(error: Exception) -> str:
    if isinstance(error, subprocess.TimeoutExpired):
        return "LOCAL_COMMAND_TIMEOUT"
    if isinstance(error, FileNotFoundError):
        return "LOCAL_TOOL_MISSING"
    if isinstance(error, urllib.error.URLError):
        return "LOCAL_SERVICE_UNAVAILABLE"
    return "WORKER_EXECUTION_FAILED"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--once", action="store_true", help="Poll once and exit")
    parser.add_argument("--poll-seconds", type=int, default=int(os.getenv("AI_WORKER_POLL_SECONDS", "10")))
    args = parser.parse_args()
    worker = Worker()
    if args.once:
        worker.once()
        return 0
    while True:
        try:
            had_job = worker.once()
        except Exception as error:
            print(f"poll failed code={error_code(error)}", flush=True)
            had_job = False
        time.sleep(1 if had_job else max(2, args.poll_seconds))


if __name__ == "__main__":
    raise SystemExit(main())
