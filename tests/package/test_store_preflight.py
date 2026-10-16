import importlib.util
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('store_preflight', Path(__file__).resolve().parents[2] / 'scripts/store-preflight.py')
preflight = importlib.util.module_from_spec(spec)
spec.loader.exec_module(preflight)


class StorePreflight(unittest.TestCase):
    def inspect(self, extra=None, author='Thomas Example', screenshot=''):
        with tempfile.TemporaryDirectory() as directory:
            archive = Path(directory) / 'candidate.tar.gz'
            files = {name: b'content' for name in preflight.checker.REQUIRED}
            files['appinfo/info.xml'] = f'<info><id>werkstattmanager</id><version>0.5.0</version><author>{author}</author><bugs>https://example.invalid/issues</bugs>{screenshot}</info>'.encode()
            files.update(extra or {})
            with tarfile.open(archive, 'w:gz') as tar:
                for name, content in files.items():
                    info = tarfile.TarInfo('werkstattmanager/' + name)
                    info.size, info.mode = len(content), 0o644
                    tar.addfile(info, io.BytesIO(content))
            return preflight.inspect(archive)

    def test_unsigned_candidate_never_implies_store_approval(self):
        report = self.inspect()
        self.assertEqual(report['file_integrity_signature'], 'missing')
        self.assertEqual(report['publication_status'], 'not_verified_or_authorized_by_this_offline_check')
        self.assertEqual(len(report['sha256']), 64)
        self.assertTrue(report['required_external_checks'])

    def test_forged_signature_presence_does_not_count_as_verified(self):
        report = self.inspect({'appinfo/signature.json': json.dumps({'signature': 'not a signature'}).encode()})
        self.assertEqual(report['file_integrity_signature'], 'present_not_verified')
        self.assertEqual(report['archive_signature'], 'not_verified')

    def test_private_key_content_in_an_allowed_text_path_is_rejected(self):
        with self.assertRaisesRegex(ValueError, 'Private key'):
            self.inspect({'lib/accidental.txt': b'-----BEGIN RSA PRIVATE KEY-----\nprivate\n-----END RSA PRIVATE KEY-----'})

    def test_unencrypted_screenshot_url_and_missing_publisher_are_rejected(self):
        with self.assertRaisesRegex(ValueError, 'HTTPS'):
            self.inspect(screenshot='<screenshot>http://example.invalid/image.png</screenshot>')
        with self.assertRaisesRegex(ValueError, 'Publisher'):
            self.inspect(author='')
