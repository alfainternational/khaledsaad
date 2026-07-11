import hashlib
import hmac
import unittest

from worker import canonical_json, request_signature


class ProtocolTest(unittest.TestCase):
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


if __name__ == "__main__":
    unittest.main()
