#!/usr/bin/env python3
"""Inventory an exact candidate offline. Never signs, uploads or assumes store approval."""
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import tarfile
import xml.etree.ElementTree as ET

spec = importlib.util.spec_from_file_location('package_check', Path(__file__).with_name('check-package.py'))
checker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(checker)


def inspect(path):
    path = Path(path)
    version, count = checker.check(path)
    files = {}
    with tarfile.open(path, 'r:gz') as archive:
        for member in archive:
            if member.size > 16 * 1024 * 1024:
                raise ValueError('Unexpectedly large release file')
            content = archive.extractfile(member).read()
            if b'-----BEGIN ' in content and b'PRIVATE KEY-----' in content:
                raise ValueError('Private key content in release')
            files[member.name] = hashlib.sha256(content).hexdigest()
        metadata = ET.fromstring(archive.extractfile('werkstattmanager/appinfo/info.xml').read())
    if not metadata.findtext('author') or not metadata.findtext('bugs'):
        raise ValueError('Publisher and support address are required')
    screenshots = [node.text for node in metadata.findall('screenshot')]
    if any(not url or not url.startswith('https://') for url in screenshots):
        raise ValueError('Store screenshots must use HTTPS')
    return {
        'app_id': metadata.findtext('id'),
        'version': version,
        'publisher': metadata.findtext('author'),
        'sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
        'files': files,
        'file_count': count,
        'screenshots': screenshots,
        'file_integrity_signature': 'present_not_verified' if 'werkstattmanager/appinfo/signature.json' in files else 'missing',
        'archive_signature': 'not_verified',
        'publication_status': 'not_verified_or_authorized_by_this_offline_check',
        'required_external_checks': [
            'Owner store account and app registration',
            'Public source, support, screenshot and immutable archive URLs',
            'Nextcloud certificate and file-integrity verification',
            'Signature over the exact final archive and signed-package install/upgrade',
            'Source-data reuse rights and remaining device/operational acceptance',
        ],
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    try:
        report = inspect(args.archive)
    except (OSError, ValueError, tarfile.TarError, ET.ParseError) as error:
        parser.exit(1, f'FAIL candidate inventory: {error}\n')
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False) + '\n')
    print(f'PASS offline inventory: {report["version"]}, {report["file_count"]} files, SHA-256 {report["sha256"]}')
    print('Store access, signatures, public downloads and publication approval remain separate checks.')


if __name__ == '__main__':
    main()
