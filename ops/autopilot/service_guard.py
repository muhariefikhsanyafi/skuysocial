#!/usr/bin/env python3
"""Fail closed before a system worker starts; inspect metadata and synthetic probes only."""
from __future__ import annotations

import json
import os
from pathlib import Path
import pwd
import stat
import subprocess
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))

SOURCE = Path('/opt/skuysocial-autopilot/source')
STATE = Path('/var/lib/skuysocial-autopilot')
CONFIG = Path('/etc/skuysocial-autopilot/runner.toml')
ACCOUNT = 'skuysocial-autopilot'
PRIVATE_ROOTS = ('/home', '/root', '/run/user', '/var/backups', '/var/www', '/srv', '/mnt',
                 '/media', '/etc/ssh', '/etc/ssl/private', '/etc/skuysocial-deploy')


def mount_options(path, mountinfo):
    """Return options for the most specific visible mount containing path."""
    matching = []
    for line in mountinfo.splitlines():
        fields = line.split()
        if len(fields) < 6:
            continue
        mount = Path(fields[4].replace('\\040', ' ').replace('\\134', '\\'))
        if Path(path).is_relative_to(mount):
            matching.append((len(mount.parts), set(fields[5].split(','))))
    return max(matching, key=lambda item: item[0])[1] if matching else set()


def identity_errors(uid, gid, groups, account_uid, account_gid, status, environment):
    errors = []
    if uid == 0 or uid != account_uid or gid != account_gid or set(groups) - {account_gid}:
        errors.append('dedicated-service-identity-required')
    if status.get('NoNewPrivs') != '1':
        errors.append('no-new-privileges-required')
    for key in ('CapEff', 'CapPrm', 'CapAmb', 'CapBnd'):
        if int(status.get(key, '1'), 16) != 0:
            errors.append('zero-capabilities-required:' + key)
    if environment.get('HOME') != str(STATE) or environment.get('CODEX_HOME') != str(STATE / 'codex'):
        errors.append('dedicated-authentication-home-required')
    if any(key in environment for key in ('SSH_AUTH_SOCK', 'OPENAI_API_KEY', 'CODEX_API_KEY',
                                          'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'GH_TOKEN', 'GITHUB_TOKEN')):
        errors.append('inherited-credential-environment-forbidden')
    return errors


def root_owned(path):
    info = path.lstat()
    return not path.is_symlink() and info.st_uid == 0 and not info.st_mode & 0o022


def inspect(require_activation=False):
    try:
        account = pwd.getpwnam(ACCOUNT)
    except KeyError:
        return ['dedicated-service-account-not-provisioned']
    status = dict(line.split(':', 1) for line in Path('/proc/self/status').read_text().splitlines() if ':' in line)
    status = {key: value.strip() for key, value in status.items()}
    errors = identity_errors(os.getuid(), os.getgid(), os.getgroups(), account.pw_uid,
                             account.pw_gid, status, os.environ)
    if errors:
        return errors  # Never inspect private host paths when outside the reviewed service identity.
    for path in (SOURCE, SOURCE.parent, SOURCE / 'ops/autopilot/service_guard.py', CONFIG, CONFIG.parent):
        if not root_owned(path):
            errors.append('source-and-config-must-be-admin-owned')
    metadata = STATE.stat()
    if metadata.st_uid != account.pw_uid or metadata.st_mode & 0o077:
        errors.append('state-must-be-private-to-service')
    mounts = Path('/proc/self/mountinfo').read_text()
    if 'ro' not in mount_options(SOURCE, mounts):
        errors.append('source-filesystem-is-not-read-only')
    if 'rw' not in mount_options(STATE, mounts):
        errors.append('private-state-is-not-writable')
    for path in PRIVATE_ROOTS:
        if os.access(path, os.R_OK | os.X_OK):
            errors.append('private-root-accessible:' + path)
    if os.access('/proc/1/root', os.R_OK):
        errors.append('foreign-process-root-accessible')
    if 'unprivileged_userns' in Path('/proc/self/attr/current').read_text():
        errors.append('inherited-apparmor-userns-restriction')
    from security_check import forbidden_path
    # The source snapshot must exclude ignored runtime data as well as Git candidates.
    # Locked vendor/node_modules and Git object storage are provisioned separately by admin.
    for parent, directories, files in os.walk(SOURCE, followlinks=False):
        relative = Path(parent).relative_to(SOURCE)
        if relative == Path('.'):
            directories[:] = [name for name in directories if name not in {'vendor', 'node_modules', '.git'}]
        for name in files:
            if forbidden_path(str(relative / name)):
                errors.append('private-data-present-in-source-snapshot')
                break
    if errors:
        return sorted(set(errors))
    from runner import clean_env
    result = subprocess.run(['/usr/bin/bwrap', '--ro-bind', '/', '/', '--unshare-user',
                             '--unshare-pid', '--', '/usr/bin/true'],
                            env=clean_env(), capture_output=True, timeout=10)
    if result.returncode:
        errors.append('native-bubblewrap-sandbox-unavailable')
    if require_activation:
        try:
            approved = json.loads((CONFIG.parent / 'provisioning.json').read_text())
            passed = json.loads((STATE / 'validation/passed.json').read_text())
            if passed.get('manifest_sha256') != approved.get('manifest_sha256') or not all(
                    passed.get(key) is True for key in ('native_isolation', 'tests', 'real_queue')):
                errors.append('current-package-validation-required')
        except (OSError, ValueError):
            errors.append('current-package-validation-required')
    return errors


if __name__ == '__main__':
    try:
        problems = inspect(require_activation=True)
    except (OSError, ValueError, subprocess.TimeoutExpired):
        problems = ['isolation-verification-unavailable']
    print(json.dumps({'system_service_isolation_verified': not problems, 'errors': problems}))
    sys.exit(1 if problems else 0)
