#!/usr/bin/env python3
"""Check release boundaries before an archive may be extracted or distributed."""
import sys
import tarfile
from pathlib import PurePosixPath
import xml.etree.ElementTree as ET

ALLOWED_ROOTS = {'appinfo', 'lib', 'templates', 'js', 'css', 'img', 'l10n', 'licenses', 'docs'}
ALLOWED_FILES = {'LICENSE', 'README.md', 'CHANGELOG.md', 'composer.json', 'composer.lock'}
PUBLIC_DOCS = {'architecture.md', 'operations.md', 'pilot-checklist.md', 'pilot-rollout.md',
               'product-spec.md', 'service-prices.md', 'workshop-functions.md', 'release.md', 'store-listing.md', 'test-report.md'}
REQUIRED = {'appinfo/info.xml', 'js/werkstattmanager-main.js', 'css/main.css',
            'js/third-party-notices.txt', 'LICENSE', 'README.md', 'docs/test-report.md'}


def check(path):
    with tarfile.open(path, 'r:gz') as archive:
        names = set()
        for member in archive:
            p = PurePosixPath(member.name)
            if p.is_absolute() or '..' in p.parts or len(p.parts) < 2 or p.parts[0] != 'werkstattmanager':
                raise ValueError('Archive contains a path outside the app')
            relative = '/'.join(p.parts[1:])
            if not member.isfile() or relative in names:
                raise ValueError('Archive contains links, directories or duplicate files')
            if any(part.startswith('.') for part in p.parts) or p.suffix.lower() in {'.key', '.pem', '.p12', '.pfx', '.map', '.log'}:
                raise ValueError('Archive contains a private/development file')
            if p.parts[1] not in ALLOWED_ROOTS and relative not in ALLOWED_FILES:
                raise ValueError('Archive contains an unexpected release path')
            if p.parts[1] == 'docs' and (len(p.parts) != 3 or p.name not in PUBLIC_DOCS):
                raise ValueError('Private deployment evidence must not be packaged')
            if member.mode != 0o644:
                raise ValueError('Unexpected file permissions')
            names.add(relative)
        if not REQUIRED <= names:
            raise ValueError('Required runtime or license files are missing')
        info = ET.fromstring(archive.extractfile('werkstattmanager/appinfo/info.xml').read())
        if info.findtext('id') != 'werkstattmanager' or not info.findtext('version'):
            raise ValueError('Invalid app identity')
        return info.findtext('version'), len(names)


if __name__ == '__main__':
    if len(sys.argv) != 2:
        raise SystemExit('Usage: python3 scripts/check-package.py package.tar.gz')
    version, count = check(sys.argv[1])
    with tarfile.open(sys.argv[1], 'r:gz') as archive:
        signature = 'signature metadata present; cryptographic verification separate' if 'werkstattmanager/appinfo/signature.json' in archive.getnames() else 'unsigned'
    print(f'PASS package boundaries: version {version}, {count} regular files; {signature}')
