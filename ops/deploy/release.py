#!/usr/bin/env python3
"""Release validation and isolated deployment rehearsal. Production execution is locked."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import subprocess
import tarfile
import tempfile
import time
import sys

ROOT = Path(__file__).resolve().parents[2]
POLICY = ROOT / 'ops/deploy/policy.json'
sys.path.insert(0, str(ROOT / 'ops/autopilot'))
from security_check import forbidden_path, publication_files, scan


def sha(path):
    digest = hashlib.sha256()
    with Path(path).open('rb') as stream:
        for data in iter(lambda: stream.read(1024**2), b''):
            digest.update(data)
    return digest.hexdigest()


def validate_build(repo):
    build = repo / 'public/build'
    manifest = json.loads((build / 'manifest.json').read_text())
    for entry in ('resources/css/app.css', 'resources/js/app.js', 'resources/js/inertia.tsx'):
        if entry not in manifest:
            raise ValueError('Missing Vite entry: ' + entry)
    for item in manifest.values():
        for name in [item['file'], *item.get('css', []), *item.get('assets', [])]:
            path = (build / name).resolve()
            if not path.is_relative_to(build.resolve()) or not path.is_file():
                raise ValueError('Missing or unsafe build file')
    return {'entries': len(manifest), 'manifest_sha256': sha(build / 'manifest.json')}


def safe_name(name):
    path = PurePosixPath(name)
    return not forbidden_path(name) and not path.is_absolute() and '..' not in path.parts and not any(
        part.startswith('.env') or part in {'.git', '.ssh', '.codex', 'auth.json', 'storage',
                                            'skuysocial-app.zip', 'database.sqlite', 'node_modules'}
        for part in path.parts)


def pack(repo, output):
    dirty = subprocess.check_output(['git', '-C', str(repo), 'status', '--porcelain', '--untracked-files=normal'], timeout=30)
    if dirty.strip():
        raise ValueError('Release source must be a clean reviewed commit; dirty bundle would misstate provenance')
    if scan(repo, publication_files(repo))['findings']:
        raise ValueError('Publication safety scan failed; artifact creation blocked')
    evidence = validate_build(repo)
    output.mkdir(parents=True, exist_ok=True)
    paths = subprocess.check_output(['git', '-C', str(repo), 'ls-files', '-z'], timeout=30).decode().split('\0')
    files = [repo / name for name in paths if name and safe_name(name) and not name.startswith('public/build/')]
    files += [path for path in (repo / 'public/build').rglob('*') if path.is_file()]
    # Release source bundle excludes persistent state and dependencies; target provisioning remains required.
    archive = output / 'release.tar.gz'
    with tarfile.open(archive, 'w:gz') as tar:
        for path in sorted(files):
            if path.is_symlink() or not path.is_file():
                raise ValueError('Unsafe release input')
            tar.add(path, arcname=str(path.relative_to(repo)), recursive=False)
    manifest = {'commit': subprocess.check_output(['git', '-C', str(repo), 'rev-parse', 'HEAD'], timeout=30).decode().strip(),
                'sha256': sha(archive), 'build': evidence, 'type': 'source-and-assets',
                'production_deployable': False, 'database_migrations': 'manual-review-required'}
    (output / 'release.json').write_text(json.dumps(manifest, indent=2) + '\n')
    verify(archive, output / 'release.json')
    return manifest


def verify(archive, manifest):
    data = json.loads(manifest.read_text())
    if sha(archive) != data['sha256']:
        raise ValueError('Release checksum mismatch')
    seen = set()
    with tarfile.open(archive) as tar:
        for member in tar:
            if not safe_name(member.name) or not member.isfile() or member.name in seen:
                raise ValueError('Unsafe archive member')
            seen.add(member.name)
    if not {'composer.lock', 'package-lock.json', 'public/build/manifest.json'} <= seen:
        raise ValueError('Incomplete release artifact')
    return data


def atomic_link(root, target):
    temporary = root / 'next'
    temporary.unlink(missing_ok=True)
    temporary.symlink_to(target, target_is_directory=True)
    temporary.replace(root / 'current')


def rehearsal(fail_health=False):
    # This harness cannot take a target/server argument and only touches its own mkdtemp.
    with tempfile.TemporaryDirectory(prefix='skuy-deploy-rehearsal-') as directory:
        root = Path(directory)
        shared = root / 'shared'
        shared.mkdir()
        (shared / 'uploads').mkdir()
        (shared / 'uploads/user.txt').write_text('synthetic upload')
        import sqlite3
        db = sqlite3.connect(shared / 'database.sqlite')
        db.execute('CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT)')
        db.execute("INSERT INTO users(name) VALUES ('synthetic user')")
        db.commit()
        with sqlite3.connect(root / 'backup.sqlite') as backup:
            db.backup(backup)
            if backup.execute('PRAGMA integrity_check').fetchone()[0] != 'ok':
                raise ValueError('Backup integrity failed')
            if backup.execute('SELECT COUNT(*) FROM users').fetchone()[0] != 1:
                raise ValueError('Backup restore verification failed')
        db.close()
        upload_before, db_before = sha(shared / 'uploads/user.txt'), sha(shared / 'database.sqlite')
        releases = root / 'releases'
        releases.mkdir()
        for version in ('old', 'candidate'):
            release = releases / version
            release.mkdir()
            (release / 'storage').symlink_to(shared / 'uploads', target_is_directory=True)
        atomic_link(root, releases / 'old')
        previous = (root / 'current').resolve()
        for _ in range(2):  # Replaying activation selects the same immutable release.
            atomic_link(root, releases / 'candidate')
        healthy = not fail_health
        if not healthy:
            atomic_link(root, previous)
        assert sha(shared / 'uploads/user.txt') == upload_before
        assert sha(shared / 'database.sqlite') == db_before
        assert (root / 'current').resolve().name == ('old' if fail_health else 'candidate')
        return {'mode': 'isolated-simulation', 'backup_integrity': 'ok', 'backup_restore_rows': 1,
                'atomic_activation': True, 'idempotent_activation': True, 'health_pass': healthy,
                'code_rollback': fail_health, 'persistent_data_unchanged': True,
                'production_accessed': False}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='command', required=True)
    for name in ('validate-build', 'pack'):
        cmd = commands.add_parser(name)
        cmd.add_argument('--repo', type=Path, default=ROOT)
        if name == 'pack':
            cmd.add_argument('--output', type=Path, required=True)
    verify_cmd = commands.add_parser('verify')
    verify_cmd.add_argument('--archive', type=Path, required=True)
    verify_cmd.add_argument('--manifest', type=Path, required=True)
    commands.add_parser('rehearse').add_argument('--fail-health', action='store_true')
    commands.add_parser('deploy')
    args = parser.parse_args()
    if args.command == 'validate-build':
        result = validate_build(args.repo.resolve())
    elif args.command == 'pack':
        result = pack(args.repo.resolve(), args.output.resolve())
    elif args.command == 'verify':
        result = verify(args.archive, args.manifest)
    elif args.command == 'rehearse':
        result = rehearsal(args.fail_health)
    else:
        raise SystemExit('PRODUCTION AUTO-DEPLOY: DISABLED. Target audit, restore drill, authorized identity, and owner activation are required.')
    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
