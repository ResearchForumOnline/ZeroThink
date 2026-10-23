"""Retrieve explicitly selected public reference ranges to local storage only.

Adapted from the September public-reference retriever. Host-specific SSH transport
is removed. Neither this tool nor its manifest is intended for personal DNA input.
"""
import argparse
import datetime
import hashlib
import json
from pathlib import Path
import urllib.parse
import urllib.request


def destination(root, relative):
    root = root.resolve()
    target = (root / relative).resolve()
    if target == root or root not in target.parents:
        raise ValueError('Reference row path must stay inside the selected data directory')
    return target


def run(root, selection_name='regional_selection.json', manifest_name='regional_range_manifest.json'):
    root = root.resolve()
    selection = json.loads(destination(root, selection_name).read_text())
    url = selection['retrieval_url']
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != 'https' or parsed.hostname != 'dataverse.harvard.edu' or parsed.username or parsed.password:
        raise ValueError('Expected the official public Harvard Dataverse HTTPS source')
    row_bytes = int(selection['row_bytes'])
    total_bytes = int(selection['genotype_file']['filesize'])
    if not 0 < row_bytes <= 10_000_000:
        raise ValueError('Invalid bounded reference row length')
    manifest_path = destination(root, manifest_name)
    if manifest_path.exists():
        raise FileExistsError('Preserve the existing retrieval manifest before another run')
    records = []
    targets = set()
    plans = []
    for sample in selection['samples']:
        target = destination(root, sample['row_filename'])
        start, end = int(sample['byte_start']), int(sample['byte_end'])
        if target in targets or target.exists():
            raise FileExistsError('Duplicate or pre-existing reference row target')
        if not 0 <= start <= end < total_bytes or end - start + 1 != row_bytes:
            raise ValueError('Reference range does not match the declared row length')
        targets.add(target)
        plans.append((sample, target, start, end))
    for sample, target, start, end in plans:
        expected = f'bytes {start}-{end}/{total_bytes}'
        request = urllib.request.Request(url, headers={'Range': f'bytes={start}-{end}', 'User-Agent': 'ZeroThink-public-reference/1.0'})
        with urllib.request.urlopen(request, timeout=45) as response:
            if response.status != 206 or response.headers.get('Content-Range') != expected:
                raise ValueError('Public reference Content-Range mismatch')
            data = response.read(row_bytes + 1)
            if len(data) != row_bytes:
                raise ValueError('Public reference range length mismatch')
        target.parent.mkdir(parents=True, exist_ok=True)
        with target.open('xb') as output:
            output.write(data)
        records.append(dict(sample_id=sample['sample_id'], file=sample['row_filename'],
                            byte_start=start, byte_end=end, bytes=len(data),
                            sha256=hashlib.sha256(data).hexdigest(),
                            retrieved_at_utc=datetime.datetime.now(datetime.timezone.utc).isoformat(),
                            status=206, content_range=expected))
    manifest = dict(source_url=url, source_release='AADR current explicitly selected public reference',
                    source_full_file_expected_md5=selection['genotype_file']['checksum']['value'],
                    full_file_hash_verified=False,
                    verification_note='Official HTTPS 206 Content-Range and each row length checked; each retrieved row SHA-256 hashed. Full source checksum is provenance, not a locally verified full-file digest.',
                    rows=records)
    with manifest_path.open('x', encoding='utf-8') as output:
        output.write(json.dumps(manifest, indent=2) + '\n')
    return manifest


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--data-root', type=Path, required=True)
    parser.add_argument('--selection', default='regional_selection.json')
    parser.add_argument('--manifest', default='regional_range_manifest.json')
    args = parser.parse_args()
    result = run(args.data_root, args.selection, args.manifest)
    print(json.dumps(dict(status='retrieved_public_reference', rows=len(result['rows']),
                          bytes=sum(r['bytes'] for r in result['rows']))))
