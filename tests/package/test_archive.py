import importlib.util
import io
from pathlib import Path
import tarfile
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('package_check', Path(__file__).resolve().parents[2] / 'scripts/check-package.py')
checker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(checker)


class ArchiveBoundaries(unittest.TestCase):
    def run_archive(self, extra=None, omit=None):
        with tempfile.TemporaryDirectory() as d:
            path = Path(d) / 'test.tar.gz'
            with tarfile.open(path, 'w:gz') as out:
                for name in sorted(checker.REQUIRED - {omit}):
                    body = b'<info><id>werkstattmanager</id><version>0.1.5</version></info>' if name == 'appinfo/info.xml' else b'test'
                    member = tarfile.TarInfo('werkstattmanager/' + name)
                    member.mode, member.size = 0o644, len(body)
                    out.addfile(member, io.BytesIO(body))
                if extra:
                    extra.mode = 0o644
                    out.addfile(extra, io.BytesIO(b''))
            return checker.check(path)

    def test_complete_runtime(self):
        self.assertEqual(self.run_archive()[0], '0.1.5')

    def test_traversal_and_absolute(self):
        for path in ['../outside', '/tmp/outside', 'werkstattmanager/../../outside']:
            with self.subTest(path=path), self.assertRaises(ValueError):
                self.run_archive(tarfile.TarInfo(path))

    def test_private_and_development_paths(self):
        for path in ['.env', 'appinfo/signing.key', 'docs/deployment/private.md', 'docs/unapproved.md',
                     'docs/internal-report.md', 'node_modules/a.js', 'js/main.js.map']:
            with self.subTest(path=path), self.assertRaises(ValueError):
                self.run_archive(tarfile.TarInfo('werkstattmanager/' + path))

    def test_approved_documentation(self):
        self.assertEqual(self.run_archive(tarfile.TarInfo('werkstattmanager/docs/operations.md'))[0], '0.1.5')

    def test_symlinks_and_duplicate(self):
        link = tarfile.TarInfo('werkstattmanager/lib/link.php')
        link.type, link.linkname = tarfile.SYMTYPE, '/etc/passwd'
        with self.assertRaises(ValueError):
            self.run_archive(link)
        with self.assertRaises(ValueError):
            self.run_archive(tarfile.TarInfo('werkstattmanager/LICENSE'))

    def test_missing_runtime(self):
        with self.assertRaises(ValueError):
            self.run_archive(omit='js/werkstattmanager-main.js')
