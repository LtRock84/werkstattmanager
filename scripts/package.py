#!/usr/bin/env python3
"""Build an internal, unsigned app package. Never includes development dependencies or secrets."""
from pathlib import Path
import argparse, hashlib, tarfile, gzip, io, json, subprocess, xml.etree.ElementTree as ET
root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser()
parser.add_argument('--support-url',help='Real support URL approved by the operator')
parser.add_argument('--candidate',action='store_true',help='Explicitly produce an unvalidated internal review candidate')
parser.add_argument('--schema',type=Path,help='Official info.xsd; required for non-candidate packages')
args=parser.parse_args()
xml=root/'appinfo/info.xml'
tree=ET.parse(xml)
if args.support_url:
    from urllib.parse import urlparse
    if urlparse(args.support_url).scheme not in ('https','http') or not urlparse(args.support_url).netloc: parser.error('Support URL must be HTTP(S)')
    element=tree.getroot().find('bugs')
    if element is None:
        element=ET.Element('bugs'); children=list(tree.getroot()); position=next(i for i,c in enumerate(children) if c.tag=='dependencies'); tree.getroot().insert(position,element)
    element.text=args.support_url
    ET.indent(tree,space=' ')
    tree.write(xml,encoding='utf-8',xml_declaration=True)
if tree.getroot().find('bugs') is None and not args.candidate: parser.error('A real support URL is required. Use --support-url or explicitly --candidate for an unfinished internal review artifact.')
if not args.candidate and not args.schema: parser.error('Provide --schema with the official App Store XSD')
if args.schema:
    subprocess.run(['xmllint','--nonet','--noout','--schema',str(args.schema),str(xml)],check=True)
for required in ['js/werkstattmanager-main.js','js/third-party-notices.txt','css/main.css','LICENSE','README.md','docs/test-report.md']:
    if not (root/required).is_file(): parser.error(f'Missing {required}; build and complete release files first')
version=tree.getroot().findtext('version')
package=json.loads((root/'package.json').read_text())
lock=json.loads((root/'package-lock.json').read_text())
if any(v != version for v in [package['version'],lock['version'],lock['packages']['']['version']]): parser.error('App and npm versions differ')
output=root/'dist';output.mkdir(exist_ok=True)
name=f'werkstattmanager-{version}'+('-candidate' if args.candidate else '')+'.tar.gz'
files=[]
for directory in ['appinfo','lib','templates','js','css','img','l10n','licenses']:
    files.extend(p for p in (root/directory).rglob('*') if p.is_file() and not p.name.startswith('.'))
files.extend(root/p for p in ['LICENSE','README.md','CHANGELOG.md','composer.json'])
if (root/'composer.lock').exists(): files.append(root/'composer.lock')
import importlib.util
spec=importlib.util.spec_from_file_location('package_boundaries', root/'scripts/check-package.py')
boundaries=importlib.util.module_from_spec(spec)
spec.loader.exec_module(boundaries)
files.extend(root/'docs'/name for name in sorted(boundaries.PUBLIC_DOCS))
for p in files:
    if p.is_symlink() or not p.resolve().is_relative_to(root.resolve()): parser.error(f'Unsafe release path: {p.relative_to(root)}')
with open(output/name,'wb') as target:
    with gzip.GzipFile(fileobj=target,mode='wb',mtime=0,filename='') as gz:
        with tarfile.open(fileobj=gz,mode='w') as archive:
            for p in sorted(set(files)):
                content=p.read_bytes();info=tarfile.TarInfo('werkstattmanager/'+str(p.relative_to(root)));info.size=len(content);info.mode=0o644;info.mtime=0;info.uid=0;info.gid=0
                archive.addfile(info,io.BytesIO(content))
digest=hashlib.sha256((output/name).read_bytes()).hexdigest()
(output/(name+'.sha256')).write_text(f'{digest}  {name}\n')
subprocess.run(['python3',str(root/'scripts/check-package.py'),str(output/name)],check=True)
print(f'{output/name}\nSHA-256 {digest}\nUnsigned internal '+('candidate, not production approved.' if args.candidate else 'package; check test report before deployment.'))
