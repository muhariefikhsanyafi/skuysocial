#!/usr/bin/env python3
"""Self-contained, hash-pinned Development provisioning. Default mode is read-only."""
from __future__ import annotations

import argparse
import grp
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import pwd
import re
import secrets
import stat
import subprocess
import sys
import tempfile

WORKSPACE = Path('/home/skuysocialdev/projects/skuysocial')
PACKAGE = WORKSPACE / '.autopilot/provisioning/reviewed'
ACCOUNT = 'skuysocial-autopilot'
SOURCE = Path('/opt/skuysocial-autopilot/source')
CONFIG = Path('/etc/skuysocial-autopilot')
STATE = Path('/var/lib/skuysocial-autopilot')
STATUS = Path('/var/lib/skuysocial-autopilot-status')
MAIN = 'skuysocial-autopilot-system.service'
VALIDATION = 'skuysocial-autopilot-validation.service'
ENV = {'PATH': '/usr/sbin:/usr/bin:/sbin:/bin', 'LANG': 'C.UTF-8', 'HOME': '/root'}
UNIT_DIGESTS = {'skuysocial-autopilot-system.service': '0b25a3a139b3ca4f25f3a0adf25489cf73c98de6785e51e0ed85ef26fdb2780b', 'skuysocial-autopilot-validation.service': '721a90170eec46d3f3c64dd0334187f868a37a556339b641e31521a9e35e053d'}
CONFIG_DIGEST = '94d5b29e7cf9574d471e866ea53de85cf2a94f431a81ba19635f00550e88c74b'


def digest(data):
    return hashlib.sha256(data).hexdigest()


def safe_path(name):
    p = PurePosixPath(name)
    if not name or str(p) != name or p.is_absolute() or '..' in p.parts:
        raise ValueError('Non-canonical package path')
    if p.parts[0] not in {'source', 'units', 'config'}:
        raise ValueError('Package path outside permitted payload roots')
    return p


def open_beneath(root_fd, name):
    """Pin each directory and refuse symlinks, including ancestor substitutions."""
    parts = PurePosixPath(name).parts
    fd = os.dup(root_fd)
    try:
        for part in parts[:-1]:
            following = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=fd)
            os.close(fd)
            fd = following
        return os.open(parts[-1], os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=fd)
    finally:
        os.close(fd)


def read_regular(root_fd, name, limit):
    fd = open_beneath(root_fd, name)
    with os.fdopen(fd, 'rb') as stream:
        info = os.fstat(stream.fileno())
        if not stat.S_ISREG(info.st_mode) or info.st_size > limit:
            raise ValueError('Unexpected package file type or size')
        data = stream.read(limit + 1)
    if len(data) > limit:
        raise ValueError('Package file exceeded size limit')
    return data


def validate_manifest(data):
    if data.get('format') != 1 or data.get('scope') != 'akamai-development' or data.get('production_enabled') is not False:
        raise ValueError('Package scope is not the disabled-Production Development scope')
    if not re.fullmatch('[a-f0-9]{64}', data.get('host_machine_id_sha256', '')):
        raise ValueError('Missing Development host binding')
    records = data.get('files', [])
    if not records or len(records) > 100000:
        raise ValueError('Invalid file count')
    seen, total = set(), 0
    for item in records:
        name = item['path']; p = safe_path(name)
        if p.parts[0] == 'units' and name not in {'units/' + MAIN, 'units/' + VALIDATION}:
            raise ValueError('Unexpected system unit in package')
        if p.parts[0] == 'config' and name != 'config/runner.toml':
            raise ValueError('Unexpected administrator config in package')
        if name in seen:
            raise ValueError('Duplicate payload path')
        seen.add(name)
        if any(part.startswith('.env') and part != '.env.example' for part in p.parts):
            raise ValueError('Environment file prohibited')
        if any(part in {'.autopilot', '.ssh', '.aws', '.codex', 'app.pre-final'} for part in p.parts) or p.name == 'skuysocial-app.zip':
            raise ValueError('Private runtime payload prohibited')
        if p.suffix in {'.key', '.pem', '.sqlite', '.sqlite3', '.db', '.log', '.zip'} or '.sqlite' in p.name:
            raise ValueError('Private key/database/log/archive payload prohibited')
        if p.name in {'auth.json', 'id_rsa', 'id_ed25519', '.netrc', 'credentials'}:
            raise ValueError('Credential payload prohibited')
        if p.parts[:2] == ('source', 'storage') and p.name != '.gitignore':
            raise ValueError('Persistent storage payload prohibited')
        if name in {'source/docs/autopilot/READINESS.md', 'source/docs/autopilot/QA_BACKLOG.md', 'source/docs/autopilot/UNBLOCK.md'} or p.parts[:3] == ('source', '.git', 'hooks'):
            raise ValueError('Private report or executable Git hook prohibited')
        if item.get('kind') == 'link':
            target = item['target']
            if p.parts[:2] != ('source', 'node_modules') or PurePosixPath(target).is_absolute():
                raise ValueError('Only internal node dependency links are allowed')
            combined = os.path.normpath(str(p.parent / target))
            if not combined.startswith('source/node_modules/'):
                raise ValueError('Dependency link escapes its locked tree')
        elif item.get('kind') == 'file':
            if not re.fullmatch('[a-f0-9]{64}', item.get('sha256', '')) or type(item.get('executable')) is not bool:
                raise ValueError('Invalid file metadata')
            size = item.get('size')
            if type(size) is not int or not 0 <= size <= 256 * 1024**2:
                raise ValueError('Invalid file size')
            total += size
        else:
            raise ValueError('Unsupported file type')
    if total > 2 * 1024**3:
        raise ValueError('Payload exceeds 2 GiB')
    required = {'source/ops/autopilot/runner.py', 'source/ops/autopilot/service_guard.py',
                'source/ops/autopilot/validate_service.py', 'source/composer.lock', 'source/package-lock.json',
                'source/.git/HEAD', 'units/' + MAIN, 'units/' + VALIDATION, 'config/runner.toml'}
    if not required <= seen:
        raise ValueError('Incomplete provisioning payload')
    for item in records:
        if item['kind'] == 'link':
            target = os.path.normpath(str(PurePosixPath(item['path']).parent / item['target']))
            if target not in seen or next(row for row in records if row['path'] == target)['kind'] != 'file':
                raise ValueError('Dependency link does not resolve to a pinned regular file')
    return records


def load_package(package, expected):
    fd = os.open(package, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        raw = read_regular(fd, 'manifest.json', 32 * 1024**2)
        if digest(raw) != expected:
            raise ValueError('Manifest SHA-256 mismatch')
        data = json.loads(raw)
        records = validate_manifest(data)
        for item in records:
            if item['kind'] == 'link':
                # It is data, never followed; ancestor directories are checked when files are opened.
                continue
            raw_file = read_regular(fd, item['path'], item['size'])
            if len(raw_file) != item['size'] or digest(raw_file) != item['sha256']:
                raise ValueError('Payload integrity mismatch: ' + item['path'])
        check_unit(read_regular(fd, 'units/' + MAIN, 65536), validation=False)
        check_unit(read_regular(fd, 'units/' + VALIDATION, 65536), validation=True)
        config = read_regular(fd, 'config/runner.toml', 65536).decode()
        if digest(config.encode()) != CONFIG_DIGEST:
            raise ValueError('Reviewed config digest changed')
        import tomllib
        parsed = tomllib.loads(config)
        if parsed.get('repo') != str(SOURCE) or parsed.get('state') != str(STATE / 'queue') or parsed.get('github', {}).get('enabled') is not False:
            raise ValueError('Unexpected service config or enabled external integration')
        return fd, data, records
    except BaseException:
        os.close(fd)
        raise


def check_unit(raw, validation):
    if digest(raw) != UNIT_DIGESTS[VALIDATION if validation else MAIN]:
        raise ValueError('Exact reviewed unit digest changed')
    text = raw.decode()
    expected = {'User': ACCOUNT, 'Group': ACCOUNT, 'ProtectSystem': 'strict', 'ProtectHome': 'true',
                'NoNewPrivileges': 'true', 'PrivateUsers': 'false', 'PrivateTmp': 'true',
                'PrivateDevices': 'true', 'ProtectProc': 'invisible', 'CapabilityBoundingSet': '',
                'AmbientCapabilities': '', 'MemoryMax': '4G', 'CPUQuota': '200%', 'TasksMax': '128'}
    values = {}
    executions = []
    section = ''
    for line in text.splitlines():
        if line.startswith('['): section = line
        if '=' in line and not line.startswith('#'):
            key, value = line.split('=', 1)
            if key.startswith('Exec'):
                executions.append((key, value))
            if key in {'EnvironmentFile', 'RootDirectory', 'RootImage', 'BindPaths', 'BindReadOnlyPaths', 'SupplementaryGroups'} or line.endswith('\\'):
                raise ValueError('Unreviewed unit execution/input control')
            if key in expected and section == '[Service]':
                if key in values: raise ValueError('Repeated security control')
                values[key] = value
    if values != expected or any(word in text for word in ('sudo', 'AppArmorProfile=-', 'PermissionsStartOnly=', 'ExecStart=+')):
        raise ValueError('Unit security contract changed')
    command = '/usr/bin/python3 -I -S ' + str(SOURCE / 'ops/autopilot') + '/'
    target = 'validate_service.py' if validation else 'runner.py --config ' + str(CONFIG / 'runner.toml') + ' worker'
    if 'ExecStart=' + command + target + '\n' not in text:
        raise ValueError('Unexpected service executable')
    allowed = [('ExecStart', command + target)]
    if not validation: allowed.insert(0, ('ExecStartPre', command + 'service_guard.py'))
    if executions != allowed:
        raise ValueError('Unreviewed executable or privileged command prefix')
    if not validation and ('ExecStartPre=' + command + 'service_guard.py\n' not in text or
                           'ConditionPathExists=' + str(STATE / 'validation/passed.json') not in text):
        raise ValueError('Worker activation gate missing')


def plan(manifest_hash, file_count):
    return {'scope': 'akamai-development-only', 'manifest_sha256': manifest_hash, 'payload_entries': file_count,
            'account': {'name': ACCOUNT, 'kind': 'system non-login', 'sudo': False},
            'managed_roots': ['/opt/skuysocial-autopilot', str(CONFIG), str(STATE), str(STATUS)],
            'units': [MAIN, VALIDATION], 'worker_start': 'only after non-root validation succeeds',
            'preserves': ['live checkout and its ownership', 'old queue/artifacts', 'existing service auth/state'],
            'network_or_package_install_as_root': False, 'production_auto_deploy': 'DISABLED'}


def existing_account():
    try: user = pwd.getpwnam(ACCOUNT)
    except KeyError: user = None
    try: group = grp.getgrnam(ACCOUNT)
    except KeyError: group = None
    if group and not 0 < group.gr_gid < 1000:
        raise ValueError('Service group must be a dedicated non-root system group')
    if user and (not group or not 0 < user.pw_uid < 1000 or user.pw_gid != group.gr_gid or
                 user.pw_dir != str(STATE) or user.pw_shell != '/usr/sbin/nologin' or
                 set(os.getgrouplist(ACCOUNT, user.pw_gid)) != {user.pw_gid}):
        raise ValueError('Existing account has incompatible identity/privileges; no mutation allowed')
    # Do not accept a reused UID/GID or another principal with this service group.
    if user and any(entry.pw_uid == user.pw_uid and entry.pw_name != ACCOUNT for entry in pwd.getpwall()):
        raise ValueError('Service UID is shared with another account')
    if group and any(entry.gr_gid == group.gr_gid and entry.gr_name != ACCOUNT for entry in grp.getgrall()):
        raise ValueError('Service GID is shared with another group')
    if group and (set(group.gr_mem) - {ACCOUNT} or any(
            entry.pw_gid == group.gr_gid and entry.pw_name != ACCOUNT for entry in pwd.getpwall())):
        raise ValueError('Service group is used by another account')
    return user, group


def host_check(package, expected, machine_id):
    # Metadata-only host checks, never credentials or application environment contents.
    os_release = Path('/etc/os-release').read_text()
    if 'VERSION_ID="24.04"' not in os_release or 'ID=ubuntu\n' not in os_release or not Path('/run/systemd/system').is_dir():
        raise ValueError('This package is restricted to the audited Ubuntu 24.04 systemd Development host')
    if digest(Path('/etc/machine-id').read_bytes().strip()) != machine_id:
        raise ValueError('Package is pinned to a different machine')
    if not WORKSPACE.is_dir() or Path(package) != PACKAGE:
        raise ValueError('Unexpected workspace or provisioning package path')
    for anchor in (Path('/opt'), Path('/etc'), Path('/var/lib'), Path('/etc/systemd/system')):
        info = anchor.lstat()
        if not stat.S_ISDIR(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022:
            raise ValueError('Administrator-owned destination anchor required')
    receipt = CONFIG / 'provisioning.json'
    if receipt.exists():
        info = receipt.lstat()
        if not stat.S_ISREG(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022 or json.loads(receipt.read_text()).get('manifest_sha256') != expected:
            raise ValueError('Different or unsafe installed package; automatic upgrade prohibited')
    existing_account()
    for path in (Path('/opt/skuysocial-autopilot'), SOURCE, CONFIG, STATE, STATUS,
                 Path('/etc/systemd/system') / MAIN, Path('/etc/systemd/system') / VALIDATION):
        if path.is_symlink(): raise ValueError('Managed destination symlink prohibited')
    return True


def command(argv, timeout=30):
    subprocess.run(argv, env=ENV, stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL,
                   stderr=subprocess.DEVNULL, check=True, timeout=timeout)


def dir_fd(path):
    """Open each ancestor from / with O_NOFOLLOW, refusing swapped symlink parents."""
    path = Path(path)
    if not path.is_absolute():
        raise ValueError('Managed directory must be absolute')
    flags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC
    fd = os.open('/', flags)
    try:
        for name in path.parts[1:]:
            child = os.open(name, flags, dir_fd=fd)
            os.close(fd)
            fd = child
        return fd
    except BaseException:
        os.close(fd)
        raise


def directory(path, uid, gid, mode):
    """Pin directory/parent inodes before altering ownership or permissions."""
    path = Path(path)
    parent = dir_fd(path.parent)
    try:
        created = False
        try:
            os.mkdir(path.name, mode=mode, dir_fd=parent)
            created = True
        except FileExistsError:
            pass
        child = os.open(path.name, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC,
                        dir_fd=parent)
        try:
            info = os.fstat(child)
            if created:
                os.fchown(child, uid, gid)
                info = os.fstat(child)
            if not stat.S_ISDIR(info.st_mode) or info.st_uid != uid or info.st_gid != gid:
                raise ValueError('Existing directory ownership mismatch: ' + str(path))
            os.fchmod(child, mode)
        finally:
            os.close(child)
    finally:
        os.close(parent)


def install_file(fd, item, target, gid):
    data = read_regular(fd, item['path'], item['size'])
    if digest(data) != item['sha256']:
        raise ValueError('Payload changed after verification')
    mode = 0o750 if item['executable'] else 0o640
    parent = dir_fd(target.parent)
    try:
        try:
            existing = os.open(target.name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK,
                               dir_fd=parent)
        except FileNotFoundError:
            existing = None
        if existing is not None:
            try:
                info = os.fstat(existing)
                if not stat.S_ISREG(info.st_mode) or info.st_uid != 0:
                    raise ValueError('Existing managed file is not regular and root-owned')
                with os.fdopen(os.dup(existing), 'rb') as stream:
                    if digest(stream.read()) != item['sha256']:
                        raise ValueError('Refusing to overwrite a different existing managed file')
                os.fchown(existing, 0, gid)
                os.fchmod(existing, mode)
            finally:
                os.close(existing)
            return
        name = '.provision-' + secrets.token_hex(16)
        temp = os.open(name, os.O_CREAT | os.O_EXCL | os.O_WRONLY | os.O_NOFOLLOW, 0o600,
                       dir_fd=parent)
        try:
            with os.fdopen(temp, 'wb') as stream:
                stream.write(data)
                os.fchown(stream.fileno(), 0, gid)
                os.fchmod(stream.fileno(), mode)
                stream.flush()
                os.fsync(stream.fileno())
            # Hard-link atomically without overwriting a destination introduced in a race.
            os.link(name, target.name, src_dir_fd=parent, dst_dir_fd=parent,
                    follow_symlinks=False)
            os.fsync(parent)
        finally:
            os.unlink(name, dir_fd=parent)
    finally:
        os.close(parent)



def check_directory(path, uid, gid, mode):
    """Read-only metadata verification anchored with O_NOFOLLOW at every ancestor."""
    fd = dir_fd(path)
    try:
        info = os.fstat(fd)
        if info.st_uid != uid or info.st_gid != gid or stat.S_IMODE(info.st_mode) != mode:
            raise ValueError('Installed managed directory metadata differs from reviewed contract')
    finally:
        os.close(fd)


def verify_layout(records, uid, gid):
    reader_gid = pwd.getpwnam('skuysocialdev').pw_gid
    for path, owner, group, mode in (
            (SOURCE.parent, 0, gid, 0o750), (SOURCE, 0, gid, 0o750),
            (CONFIG, 0, gid, 0o750), (STATE, uid, gid, 0o700),
            (STATUS, uid, reader_gid, 0o2750)):
        check_directory(path, owner, group, mode)
    for name in ('codex', 'queue', 'validation'):
        check_directory(STATE / name, uid, gid, 0o700)
    directories = set()
    for item in records:
        p = PurePosixPath(item['path'])
        if p.parts[0] == 'source':
            target = SOURCE.joinpath(*p.parts[1:])
            parent = target.parent
            while parent != SOURCE:
                directories.add(parent)
                parent = parent.parent
        elif p.parts[0] == 'units':
            target = Path('/etc/systemd/system') / p.name
        else:
            target = CONFIG / p.name
        info = target.lstat()
        if p.parts[0] == 'units': expected_gid = 0
        else: expected_gid = gid
        if info.st_uid != 0 or info.st_gid != expected_gid:
            raise ValueError('Installed managed file ownership differs from reviewed contract')
        if item['kind'] == 'link':
            if not target.is_symlink() or os.readlink(target) != item['target']:
                raise ValueError('Installed dependency link changed')
        else:
            expected_mode = 0o750 if item['executable'] else 0o640
            if not stat.S_ISREG(info.st_mode) or stat.S_IMODE(info.st_mode) != expected_mode:
                raise ValueError('Installed managed file permissions differ from reviewed contract')
            if digest(target.read_bytes()) != item['sha256']:
                raise ValueError('Installed managed file content differs from reviewed contract')
    for path in directories:
        check_directory(path, 0, gid, 0o750)
    receipt = CONFIG / 'provisioning.json'
    info = receipt.lstat()
    if not stat.S_ISREG(info.st_mode) or info.st_uid != 0 or info.st_gid != gid or stat.S_IMODE(info.st_mode) != 0o640:
        raise ValueError('Provisioning receipt metadata differs from reviewed contract')


def apply(fd, data, records, expected):
    if os.geteuid() != 0: raise ValueError('Administrator execution is required; no changes made')
    if not sys.flags.isolated or not sys.flags.no_site:
        raise ValueError('Administrator Python requires isolated -I -S startup')
    # The reviewed root security audit found no safe rollback/journal for partial installs.
    # Keep privileged apply permanently fail-closed until a separately reviewed transaction
    # and recovery implementation replaces this gate. No existing package is authorized.
    raise ValueError('BLOCKED: root provisioning lacks reviewed rollback and recovery')
    # Run metadata conflict checks before the first privileged change.
    host_check(PACKAGE, expected, data['host_machine_id_sha256'])
    if (CONFIG / 'provisioning.json').exists():
        # Read-only complete contract verification; never silently repair drift or restart.
        user, group = existing_account()
        if not user or not group:
            raise ValueError('Provisioned service account is missing')
        verify_layout(records, user.pw_uid, group.gr_gid)
        completed = json.loads((CONFIG / 'provisioning.json').read_text()).get('phase') == 'provisioned'
        if not completed:
            raise ValueError('Prior installation stopped before completion; inspect without automatic replay')
        return {'provisioned': True, 'unchanged': True, 'services_restarted': False,
                'production_auto_deploy': 'DISABLED'}
    for name in (MAIN, VALIDATION):
        if subprocess.run(['/usr/bin/systemctl', 'is-active', '--quiet', name], env=ENV).returncode == 0:
            raise ValueError('Service is active; do not reprovision it')
    user, group = existing_account()
    if not group: command(['/usr/sbin/groupadd', '--system', ACCOUNT])
    if not user:
        command(['/usr/sbin/useradd', '--system', '--gid', ACCOUNT, '--home-dir', str(STATE),
                 '--no-create-home', '--shell', '/usr/sbin/nologin', '--no-log-init', ACCOUNT])
    user, group = existing_account(); uid, gid = user.pw_uid, group.gr_gid
    reader_gid = pwd.getpwnam('skuysocialdev').pw_gid
    directory(Path('/opt/skuysocial-autopilot'), 0, gid, 0o750)
    directory(SOURCE, 0, gid, 0o750)
    directory(CONFIG, 0, gid, 0o750)
    # mkdir creates a new directory owned by root; change only these exact new private roots.
    for path, owner, groupid, mode in ((STATE, uid, gid, 0o700), (STATUS, uid, reader_gid, 0o2750)):
        directory(path, owner, groupid, mode)
    for name in ('codex', 'queue', 'validation'):
        path = STATE / name
        directory(path, uid, gid, 0o700)
    for item in records:
        p = PurePosixPath(item['path'])
        if p.parts[0] == 'source': target = SOURCE.joinpath(*p.parts[1:])
        elif p.parts[0] == 'units': target = Path('/etc/systemd/system') / p.name
        else: target = CONFIG / p.name
        # All parents stay under fixed managed roots; no recursive chown or live checkout writes.
        parent_root = SOURCE if p.parts[0] == 'source' else target.parent
        for parent in reversed(list(target.parent.parents)):
            if parent.is_relative_to(parent_root): directory(parent, 0, gid, 0o750)
        directory(target.parent, 0, gid, 0o750) if target.parent == SOURCE or target.parent.is_relative_to(SOURCE) else None
        if item['kind'] == 'link':
            if target.is_symlink():
                if os.readlink(target) != item['target']: raise ValueError('Existing dependency link mismatch')
            elif target.exists(): raise ValueError('Dependency link destination is occupied')
            else: target.symlink_to(item['target'])
            os.lchown(target, 0, gid)
        else: install_file(fd, item, target, gid if p.parts[0] != 'units' else 0)
    receipt = {'manifest_sha256': expected, 'scope': 'akamai-development', 'production_enabled': False, 'phase': 'installed'}
    path = CONFIG / 'provisioning.json'
    if not path.exists():
        path.write_text(json.dumps(receipt, indent=2)+'\n'); os.chown(path, 0, gid); os.chmod(path, 0o640)
    command(['/usr/bin/systemd-analyze', 'verify', str(Path('/etc/systemd/system') / MAIN), str(Path('/etc/systemd/system') / VALIDATION)])
    command(['/usr/bin/systemctl', 'daemon-reload'])
    # Enable only the guarded worker. Its condition and ExecStartPre prevent premature startup.
    command(['/usr/bin/systemctl', 'enable', MAIN])
    # Fixed service executes reviewed code as the dedicated non-root account, never root.
    command(['/usr/bin/systemctl', 'start', '--no-block', VALIDATION])
    receipt['phase'] = 'provisioned'
    temporary = CONFIG / '.provisioning.json.tmp'
    temporary.write_text(json.dumps(receipt, indent=2)+'\n')
    os.chown(temporary, 0, gid); os.chmod(temporary, 0o640)
    os.replace(temporary, path)
    return {'provisioned': True, 'validation': 'scheduled under dedicated non-root identity',
            'authentication': 'dedicated Codex login required; no credentials copied',
            'production_auto_deploy': 'DISABLED'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--package', type=Path, default=PACKAGE)
    parser.add_argument('--manifest-sha256', required=True)
    args = parser.parse_args()
    os.umask(0o077)
    fd, data, records = load_package(args.package, args.manifest_sha256)
    try:
        host_check(args.package, args.manifest_sha256, data['host_machine_id_sha256'])
        if args.apply:
            result = apply(fd, data, records, args.manifest_sha256)
        else:
            result = {'mode': 'READ-ONLY', 'verified': True, **plan(args.manifest_sha256, len(records))}
        print(json.dumps(result, indent=2))
    finally: os.close(fd)


if __name__ == '__main__':
    try: main()
    except (OSError, ValueError, subprocess.SubprocessError, KeyError):
        print('Provisioning stopped safely. Check package integrity, fixed host paths, account ownership and administrator authorization; no secrets are printed.', file=sys.stderr)
        sys.exit(1)
