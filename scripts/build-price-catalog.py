#!/usr/bin/env python3
"""Prepare price observations without publishing supplier names or source links.

An optional private --input JSON may contain the research provenance. Never commit
that file. Stable seed keys preserve upgrades; this script never invents prices.
"""
import argparse
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--input', type=Path, default=ROOT / 'lib/Data/prices.json')
args = parser.parse_args()
data = json.loads(args.input.read_text())
observations = data['observations']
identities = {}
for row in observations:
    name = row['provider']
    identity = name if re.fullmatch(r'Preisgeber [a-f0-9]{12}', name) else 'Preisgeber ' + hashlib.sha256(name.encode()).hexdigest()[:12]
    identities.setdefault(identity, name)
    if identities[identity] != name:
        raise ValueError('Provider identity collision')
    row['provider'], row['source'] = identity, ''
    row['scope'] = row['scope'].replace('FSP-HU', 'HU bei derselben Prüfstelle').replace('MGS5+', 'Aktionsinspektion')
if len({o['seed_key'] for o in observations}) != len(observations):
    raise ValueError('Duplicate observation seed key')
data['version'] = '2026-10-16.1'
data['method'] = 'Anonymised public offers; provider identities and links are not published; qualified scope groups, not representative paid-invoice statistics'
(ROOT / 'lib/Data/prices.json').write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')
coverage = json.loads((ROOT / 'lib/Data/price-coverage.json').read_text())
coverage['providers_anonymised'] = True
(ROOT / 'lib/Data/price-coverage.json').write_text(json.dumps(coverage, ensure_ascii=False, indent=2) + '\n')
print(len(observations), 'observations;', len(identities), 'neutral provider identities; no source URLs')
