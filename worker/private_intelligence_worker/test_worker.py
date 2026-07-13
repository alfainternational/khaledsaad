import hashlib
import hmac
import json
import os
import unittest
import time
from unittest.mock import MagicMock, patch

from worker import LeaseHeartbeat, Worker, canonical_json, request_signature


class ProtocolTest(unittest.TestCase):
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


if __name__ == "__main__":
    unittest.main()
