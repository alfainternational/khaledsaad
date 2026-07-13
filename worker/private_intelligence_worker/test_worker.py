import hashlib
import hmac
import json
import os
import unittest
import time
import io
import zipfile
from unittest.mock import MagicMock, patch

from worker import LeaseHeartbeat, Worker, canonical_json, request_signature, runtime_manifest


class ProtocolTest(unittest.TestCase):
    def test_runtime_manifest_reports_local_tools_and_ocr_languages(self):
        completed = MagicMock(stdout="tesseract 5.5.0\n")
        with patch("shutil.which", return_value="D:/tools/tesseract.exe"), patch(
            "subprocess.run", return_value=completed
        ):
            manifest = runtime_manifest()

        self.assertIn("python", manifest)
        self.assertEqual("tesseract 5.5.0", manifest["tools"]["tesseract"])
        self.assertEqual(["ara", "eng"], manifest["ocr_languages"])

    def test_lease_heartbeat_renews_until_stopped(self):
        sent = []
        heartbeat = LeaseHeartbeat(lambda progress: sent.append(progress), interval=0.01)

        heartbeat.start()
        time.sleep(0.045)
        heartbeat.stop()

        self.assertGreaterEqual(len(sent), 2)
        self.assertEqual(sorted(sent), sent)
        self.assertLessEqual(sent[-1], 90)

    def test_canonical_json_is_recursive_and_deterministic(self):
        left = {"z": [{"b": 2, "a": 1}], "a": "عربي"}
        right = {"a": "عربي", "z": [{"a": 1, "b": 2}]}
        self.assertEqual(canonical_json(left), canonical_json(right))
        self.assertIn("عربي", canonical_json(left))

    def test_request_signature_matches_the_protocol_contract(self):
        secret = "private-secret"
        body = b'{"capabilities":["ocr"]}'
        signature = request_signature(secret, "post", "/api/v1/private-worker/lease", 1700000000, "nonce-1234567890", body)
        canonical = "\n".join(
            (
                "POST",
                "/api/v1/private-worker/lease",
                "1700000000",
                "nonce-1234567890",
                hashlib.sha256(body).hexdigest(),
            )
        )
        expected = hmac.new(secret.encode(), canonical.encode(), hashlib.sha256).hexdigest()
        self.assertEqual(expected, signature)

    def test_embedding_batch_preserves_server_identity_contract(self):
        os.environ.update(
            AI_WORKER_SERVER_URL="https://example.test",
            AI_WORKER_ID="wrk_test",
            AI_WORKER_SECRET="secret",
        )
        response = MagicMock()
        response.read.return_value = json.dumps({"embeddings": [[3, 4], [5, 12]]}).encode()
        response.__enter__.return_value = response
        payload = {
            "model_name": "nomic-embed-text",
            "model_version": "v1",
            "items": [
                {"chunk_id": 11, "content_hash": "a" * 64, "text": "first"},
                {"chunk_id": 12, "content_hash": "b" * 64, "text": "second"},
            ],
        }

        with patch("urllib.request.urlopen", return_value=response) as opened:
            result = Worker().embeddings(payload)

        request_body = json.loads(opened.call_args.args[0].data)
        self.assertEqual(["first", "second"], request_body["input"])
        self.assertEqual(11, result["vectors"][0]["chunk_id"])
        self.assertNotIn("text", result["vectors"][0])

    def test_local_llm_disables_thinking_and_bounds_output(self):
        os.environ.update(
            AI_WORKER_SERVER_URL="https://example.test",
            AI_WORKER_ID="wrk_test",
            AI_WORKER_SECRET="secret",
            AI_WORKER_LLM_MAX_TOKENS="512",
        )
        response = MagicMock()
        response.read.return_value = json.dumps(
            {"response": '{"claims":[]}', "model": "qwen3:4b"}
        ).encode()
        response.__enter__.return_value = response

        with patch("urllib.request.urlopen", return_value=response) as opened:
            result = Worker().local_llm({"prompt": "extract claims"})

        request_body = json.loads(opened.call_args.args[0].data)
        self.assertFalse(request_body["think"])
        self.assertEqual(512, request_body["options"]["num_predict"])
        self.assertEqual([], result["claims"])

    def test_signed_requests_support_a_verified_ssh_tunnel_origin(self):
        os.environ.update(
            AI_WORKER_SERVER_URL="https://127.0.0.1:18443",
            AI_WORKER_ID="wrk_test",
            AI_WORKER_SECRET="secret",
            AI_WORKER_HTTP_HOST="khaledsaad.net",
            AI_WORKER_TLS_CHECK_HOSTNAME="true",
            AI_WORKER_TLS_SERVER_NAME="khaledsaad.net",
        )
        response = MagicMock(status=204)
        response.read.return_value = b""
        response.getheaders.return_value = []
        connection = MagicMock()
        connection.getresponse.return_value = response

        with patch("http.client.HTTPSConnection", return_value=connection) as opened:
            Worker().signed_request("POST", "/lease", {"capabilities": ["embeddings"]})

        self.assertEqual("khaledsaad.net", opened.call_args.args[0])
        self.assertEqual("khaledsaad.net", connection.request.call_args.kwargs["headers"]["Host"])

    def test_worker_routes_docx_through_the_v2_structured_extractor(self):
        os.environ.update(
            AI_WORKER_SERVER_URL="https://example.test",
            AI_WORKER_ID="wrk_test",
            AI_WORKER_SECRET="secret",
        )
        buffer = io.BytesIO()
        with zipfile.ZipFile(buffer, "w") as archive:
            archive.writestr(
                "word/document.xml",
                '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Structured evidence</w:t></w:r></w:p></w:body></w:document>',
            )
        content = buffer.getvalue()
        worker = Worker()
        worker.signed_request = MagicMock(return_value=(
            200, content, {"Content-Type": "application/vnd.openxmlformats-officedocument.wordprocessingml.document"}
        ))
        contract = {"version": "v2", "max_chunks": 100, "max_text_chars": 350000, "max_chunk_chars": 3500}
        job = {"public_id": "job-docx", "payload": {
            "mime_type": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "original_name": "report.docx",
            "expected_sha256": hashlib.sha256(content).hexdigest(),
            "extraction_contract": contract,
        }}

        result = worker.extract_document(job, "lease-token")

        self.assertEqual("v2", result["contract_version"])
        self.assertEqual("docx_paragraph", result["chunks"][0]["locator"]["type"])


if __name__ == "__main__":
    unittest.main()
