#!/usr/bin/env python3
"""Validate against a previously downloaded official App Store schema."""
import subprocess,sys
from pathlib import Path
root=Path(__file__).resolve().parents[1]
if len(sys.argv)!=2:raise SystemExit('Usage: python3 scripts/validate-metadata.py /path/to/info.xsd')
raise SystemExit(subprocess.run(['xmllint','--nonet','--noout','--schema',sys.argv[1],str(root/'appinfo/info.xml')]).returncode)
