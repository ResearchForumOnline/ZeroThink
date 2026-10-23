"""Seal only immutable aggregate/runtime files; never include individual evaluation inputs."""
import hashlib,json,os,urllib.request
from pathlib import Path
BASE=Path('/mnt/data/zerothink/dna-country-20260905/science-bigsnpr');B=BASE/'bundle-v2'
os.umask(0o077)
# Avoid a symlink through the root-only construction venv after installation.
# This changes only our candidate's link, never the system interpreter content.
interpreter=B/'runtime/bin/python'
assert interpreter.is_symlink() and interpreter.resolve()==Path('/usr/bin/python3.9')
if os.readlink(interpreter)!='/usr/bin/python3.9':
    replacement=interpreter.with_name('python-direct-system-link')
    assert not replacement.exists() and not replacement.is_symlink()
    replacement.symlink_to('/usr/bin/python3.9');os.replace(replacement,interpreter)
license_url='https://raw.githubusercontent.com/spdx/license-list-data/main/text/GPL-3.0-or-later.txt'
with urllib.request.urlopen(license_url,timeout=30) as r:license_text=r.read()
assert b'GNU GENERAL PUBLIC LICENSE' in license_text and len(license_text)>30000
(B/'COPYING-GPL-3.0.txt').write_bytes(license_text)
notice='''ZeroThink isolated composition integration, 2026-09-05.

Reference data: Florian Prive, Reference set of allele frequencies in 21 worldwide
ancestry groups, Figshare version3, doi:10.6084/m9.figshare.16858534.v3.
Data license: Creative Commons Attribution4.0 (https://creativecommons.org/licenses/by/4.0/).
Derived assets are filtered/reordered subsets of the published aggregate reference
frequencies and principal-component loadings; no participant-level UK Biobank data.

Method: Prive2022, Using the UK Biobank as a global reference of worldwide populations,
Bioinformatics38:3477-3480, doi:10.1093/bioinformatics/btac348.
Source package bigsnpr revision4bc7f817640d393c7c3997c1a675509c2ea78b6c is GPL-3.
The private worker is a documented Python adaptation of the published equations.
nearPD adaptation: Martin Maechler2007-2019; Jens Oehlschlaegel2007, Matrix::nearPD,
GPL2-or-later; the worker provides the GPL3 notice and retains their attribution.

The coordinate bridge additionally uses the pinned MIT-licensed GrafAnc author
GRCh37/GRCh38 marker mapping. That mapping does not supply any mixture frequencies.
GrafAnc copyright2025 University of Pennsylvania; full notice is retained separately.
NumPy and quadprog dependency licenses are retained inside runtime/site-packages.
No warranty. This is a reference-model estimate, not a verified country or identity test.
'''
(B/'NOTICE.txt').write_text(notice)
graf=Path('/mnt/data/zerothink/dna-customer-20260905/grafanc-prototype/bundle/licenses/GrafAnc-MIT.txt')
(B/'GrafAnc-MIT.txt').write_bytes(graf.read_bytes())
files={}
for p in sorted(B.rglob('*')):
    if not p.is_file() or p.name=='manifest.json' or p.suffix=='.pyc' or '__pycache__' in p.parts:continue
    rel=str(p.relative_to(B));data=p.read_bytes();files[rel]=dict(bytes=len(data),sha256=hashlib.sha256(data).hexdigest())
manifest=dict(schema_version='bigsnpr-runtime-v1',data_version='10.6084/m9.figshare.16858534.v3',bigsnpr_revision='4bc7f817640d393c7c3997c1a675509c2ea78b6c',runtime=dict(python='3.9',numpy='1.26.4',quadprog='0.1.13'),files=files)
(B/'manifest.json').write_text(json.dumps(manifest,indent=2,sort_keys=True)+'\n')
print(json.dumps(dict(bundle=str(B),manifest_sha256=hashlib.sha256((B/'manifest.json').read_bytes()).hexdigest(),file_count=len(files),file_bytes=sum(x['bytes'] for x in files.values()),core_files={k:v for k,v in files.items() if '/' not in k})),flush=True)

