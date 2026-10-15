#!/usr/bin/env python3
"""Prepare pinned local data for administrator review. Never performs privileged work."""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path
import shutil
import stat
import subprocess
import sys
import tempfile

sys.path.insert(0, str(Path(__file__).resolve().parent))
import security_check

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / '.autopilot/provisioning'
PACKAGE = BASE / 'reviewed'


def sha(data):
    return hashlib.sha256(data).hexdigest()


def copy_regular(source, destination):
    info = source.lstat()
    if not stat.S_ISREG(info.st_mode):
        raise ValueError('Only ordinary source files are copied')
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, destination, follow_symlinks=False)
    destination.chmod(0o700 if info.st_mode & 0o111 else 0o600)


def build():
    if os.geteuid() == 0:
        raise ValueError('Build as the Development owner, never root')
    if PACKAGE.exists():
        raise ValueError('Reviewed package already exists; retain it and explicitly review any replacement')
    names = security_check.publication_files(ROOT)
    result = security_check.scan(ROOT, names)
    if result['findings']:
        raise ValueError('Publication scan failed; no package created')
    BASE.mkdir(parents=True, exist_ok=True, mode=0o700)
    stage = Path(tempfile.mkdtemp(prefix='.build-', dir=BASE))
    source = stage / 'source'
    env = {'PATH': '/usr/bin:/bin', 'HOME': '/nonexistent', 'LANG': 'C.UTF-8',
           'GIT_CONFIG_GLOBAL': '/dev/null', 'GIT_CONFIG_SYSTEM': '/dev/null', 'GIT_TERMINAL_PROMPT': '0'}
    # A shallow data clone avoids copying old history/config/hooks or any ignored runtime file.
    subprocess.run(['/usr/bin/git', '-c', 'core.hooksPath=/dev/null', 'clone', '--no-local', '--no-hardlinks',
                    '--depth=1', '--no-checkout', str(ROOT), str(source)], env=env, check=True,
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=60)
    subprocess.run(['/usr/bin/git', '-C', str(source), 'remote', 'remove', 'origin'], env=env, check=True, timeout=30)
    # Remove inert Git hook samples from the data payload; no hooks are used by the service.
    shutil.rmtree(source / '.git/hooks', ignore_errors=True)
    for name in names:
        if (ROOT / name).exists(): copy_regular(ROOT / name, source / name)
    for dependency in ('vendor', 'node_modules'):
        if not (ROOT / dependency).is_dir(): raise ValueError('Locked local dependencies are required')
        shutil.copytree(ROOT / dependency, source / dependency, symlinks=True)
    units = stage / 'units'; units.mkdir()
    for name in ('skuysocial-autopilot-system.service', 'skuysocial-autopilot-validation.service'):
        copy_regular(ROOT / 'ops/autopilot' / name, units / name)
    (stage / 'config').mkdir()
    copy_regular(ROOT / 'ops/autopilot/system.config.example.toml', stage / 'config/runner.toml')
    records = []
    for parent, directories, files in os.walk(stage, followlinks=False):
        for name in directories[:]:
            if (Path(parent) / name).is_symlink():
                files.append(name); directories.remove(name)
        for name in sorted(files):
            path = Path(parent) / name
            relative = path.relative_to(stage).as_posix()
            if path.is_symlink():
                target = os.readlink(path)
                if not path.resolve().is_relative_to(source / 'node_modules'):
                    raise ValueError('Dependency symlink escapes locked snapshot')
                records.append({'path': relative, 'kind': 'link', 'target': target})
            else:
                data = path.read_bytes()
                records.append({'path': relative, 'kind': 'file', 'sha256': sha(data),
                                'size': len(data), 'executable': bool(path.stat().st_mode & 0o111)})
    records.sort(key=lambda row: row['path'])
    manifest = {'format': 1, 'scope': 'akamai-development', 'production_enabled': False,
                'host_machine_id_sha256': sha(Path('/etc/machine-id').read_bytes().strip()),
                'baseline': subprocess.check_output(['/usr/bin/git', 'rev-parse', 'HEAD'], cwd=ROOT, env=env, text=True).strip(),
                'source_snapshot': 'shallow Git baseline plus reviewed uncommitted code; no commit created',
                'files': records}
    raw = (json.dumps(manifest, sort_keys=True, separators=(',', ':')) + '\n').encode()
    (stage / 'manifest.json').write_bytes(raw)
    copy_regular(ROOT / 'ops/autopilot/provision_admin.py', stage / 'provision_admin.py')
    stage.rename(PACKAGE)
    return {'package': str(PACKAGE), 'manifest_sha256': sha(raw),
            'script_sha256': sha((PACKAGE / 'provision_admin.py').read_bytes()),
            'files': len(records), 'data_bytes': sum(row.get('size', 0) for row in records)}


if __name__ == '__main__':
    print(json.dumps(build(), indent=2))
