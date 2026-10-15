#!/usr/bin/env python3
"""Scan publication candidates without opening excluded secrets, databases or archives."""
from __future__ import annotations

import argparse
import json
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys

PATTERNS = {
    'private-key-material': re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH |DSA |ENCRYPTED )?PRIVATE KEY-----'),
    'github-token': re.compile(r'\b(?:gh[pousr]_[A-Za-z0-9_]{30,}|github_pat_[A-Za-z0-9_]{40,})\b'),
    'provider-api-key': re.compile(r'\bsk-(?:proj-|svcacct-)?[A-Za-z0-9_-]{30,}\b'),
    'aws-access-id': re.compile(r'\b(?:AKIA|ASIA)[A-Z0-9]{16}\b'),
    'credential-json': re.compile(r'''["'](?:access_token|refresh_token|client_secret)["']\s*:\s*["'][A-Za-z0-9_./+\-=]{24,}["']'''),
}


def forbidden_path(name):
    path = PurePosixPath(name)
    if name in {'docs/autopilot/READINESS.md', 'docs/autopilot/QA_BACKLOG.md', 'docs/autopilot/UNBLOCK.md'}:
        return True
    if path.is_absolute() or '..' in path.parts:
        return True
    if any(part in {'.git', '.autopilot', '.ssh', '.aws', '.gnupg', '__pycache__', 'autopilot-artifacts'} for part in path.parts):
        return True
    if any(part.startswith('.env') and part != '.env.example' for part in path.parts):
        return True
    if path.name in {'auth.json', 'id_rsa', 'id_ed25519', 'id_ecdsa', 'credentials', '.netrc'}:
        return True
    if path.suffix.lower() in {'.key', '.pem', '.p12', '.pfx', '.sqlite', '.sqlite3', '.db', '.sql', '.log', '.zip', '.gz', '.tar'}:
        return True
    if '.sqlite' in path.name or path.name.endswith(('-wal', '-shm')):
        return True
    if path.parts and path.parts[0] == 'storage' and path.name != '.gitignore':
        return True
    return any(part == 'app.pre-final' for part in path.parts)


def publication_files(root):
    result = subprocess.check_output(['git', '-c', 'safe.directory=' + str(Path(root).resolve()),
                                      '-C', str(root), 'ls-files', '--cached', '--others',
                                      '--exclude-standard', '-z'], timeout=30)
    return sorted(set(name for name in result.decode().split('\0') if name))


def scan(root, names):
    root = Path(root).resolve()
    findings, scanned, binary = [], 0, 0
    for name in names:
        if forbidden_path(name):
            findings.append({'path': name, 'category': 'forbidden-publication-path'})
            continue  # Never open a secret/database/log/archive, even when it is a candidate.
        path = root / name
        if path.is_symlink() or not path.resolve().is_relative_to(root):
            findings.append({'path': name, 'category': 'symlink-or-outside-root'})
            continue
        if not path.exists():  # Deleted tracked files are absent from the publication.
            continue
        if not path.is_file() or path.stat().st_size > 2 * 1024**2:
            findings.append({'path': name, 'category': 'unsupported-file-requires-review'})
            continue
        data = path.read_bytes()
        if b'\0' in data:
            binary += 1
            continue
        scanned += 1
        for line_no, line in enumerate(data.decode(errors='replace').splitlines(), 1):
            for category, pattern in PATTERNS.items():
                if pattern.search(line):
                    findings.append({'path': name, 'line': line_no, 'category': category})
    return {'candidate_count': len(names), 'text_files_scanned': scanned,
            'binary_files_not_content_scanned': binary, 'findings': findings,
            'limitations': 'Heuristic token/private-key detection; arbitrary unknown secrets require human review. Values are never reported.'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, default=Path.cwd())
    parser.add_argument('--json', action='store_true')
    args = parser.parse_args()
    report = scan(args.repo, publication_files(args.repo))
    if args.json:
        print(json.dumps(report, indent=2))
    else:
        print(f"Publication scan: {report['candidate_count']} candidate files, "
              f"{len(report['findings'])} findings; secret values omitted.")
        for item in report['findings']:
            print(item['path'] + ':' + str(item.get('line', 0)) + ' ' + item['category'])
    return 1 if report['findings'] else 0


if __name__ == '__main__':
    sys.exit(main())
