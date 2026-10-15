#!/usr/bin/env python3
"""Fail-closed recovery of *new* Development files, with an explicit local journal.

Not yet wired to privileged provisioning. No privileged application or systemd
operation is performed by this module. Recovery only removes objects that a
trusted installer explicitly recorded and that still match original inodes.
"""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import secrets
import stat

MAX_JOURNAL = 4 * 1024 * 1024
MAX_FILE = 256 * 1024 * 1024
FLAGS = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC


def anchored_directory(path):
    """Pin every ancestor from /; never traverse symlinks."""
    path = Path(path)
    if not path.is_absolute():
        raise ValueError('Absolute trusted directory required')
    fd = os.open('/', FLAGS)
    try:
        for part in path.parts[1:]:
            following = os.open(part, FLAGS, dir_fd=fd)
            os.close(fd)
            fd = following
        return fd
    except BaseException:
        os.close(fd)
        raise


def safe_parts(relative):
    if not isinstance(relative, str) or not relative or '\\' in relative or '\x00' in relative:
        raise ValueError('Invalid recovery path')
    p = PurePosixPath(relative)
    if p.is_absolute() or str(p) != relative or any(x in {'.', '..'} for x in p.parts):
        raise ValueError('Non-canonical recovery path')
    return p.parts


def inside(root, relative):
    parts = safe_parts(relative)
    fd = anchored_directory(root)
    try:
        for part in parts[:-1]:
            next_fd = os.open(part, FLAGS, dir_fd=fd)
            os.close(fd)
            fd = next_fd
        return fd, parts[-1]
    except BaseException:
        os.close(fd)
        raise


def fingerprint(parent_fd, name, kind):
    info = os.stat(name, dir_fd=parent_fd, follow_symlinks=False)
    if kind != 'directory' and info.st_nlink != 1:
        raise ValueError('Shared hardlink or unexpected inode')
    mode = stat.S_IMODE(info.st_mode)
    result = {'kind': kind, 'dev': info.st_dev, 'ino': info.st_ino, 'uid': info.st_uid,
              'gid': info.st_gid, 'mode': mode}
    if kind == 'file':
        if not stat.S_ISREG(info.st_mode) or info.st_size > MAX_FILE:
            raise ValueError('Unexpected recovery file type or size')
        fd = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC | os.O_NONBLOCK, dir_fd=parent_fd)
        try:
            stat_before = os.fstat(fd)
            if (stat_before.st_ino, stat_before.st_dev, stat_before.st_size, stat_before.st_mtime_ns) != (info.st_ino, info.st_dev, info.st_size, info.st_mtime_ns):
                raise ValueError('Recovery object changed before read')
            h = hashlib.sha256()
            while block := os.read(fd, 65536):
                h.update(block)
            after = os.fstat(fd)
            if (after.st_ino, after.st_dev, after.st_size, after.st_mtime_ns) != (info.st_ino, info.st_dev, info.st_size, info.st_mtime_ns):
                raise ValueError('Recovery object changed during read')
            result.update({'size': info.st_size, 'sha256': h.hexdigest()})
        finally:
            os.close(fd)
    elif kind == 'directory':
        if not stat.S_ISDIR(info.st_mode):
            raise ValueError('Recovery object is not a directory')
    elif kind == 'link':
        if not stat.S_ISLNK(info.st_mode):
            raise ValueError('Recovery object is not a link')
        result['target'] = os.readlink(name, dir_fd=parent_fd)
    else:
        raise ValueError('Unsupported rollback kind')
    return result


class RecoveryJournal:
    """Journal is in a trusted, already-existing private (0700) directory.

    record() is called ONLY for newly created objects. A future installer must
    solve the write-ahead crash window *before* privileged apply can be enabled.
    """
    def __init__(self, root, journal_dir, manifest_sha256):
        self.root = Path(root)
        self.journal_dir = Path(journal_dir)
        if len(manifest_sha256) != 64 or any(c not in '0123456789abcdef' for c in manifest_sha256):
            raise ValueError('Pinned manifest SHA-256 required')
        self.manifest = manifest_sha256
        self.file = 'recovery.json'
        # Never permit journal storage in managed source tree.
        if self.journal_dir == self.root or self.journal_dir.is_relative_to(self.root):
            raise ValueError('Journal must be outside managed target')
        fd = anchored_directory(self.journal_dir)
        try:
            info = os.fstat(fd)
            if info.st_uid != os.geteuid() or stat.S_IMODE(info.st_mode) != 0o700:
                raise ValueError('Journal directory must be private and owned by executor')
        finally:
            os.close(fd)

    def _read(self):
        fd = anchored_directory(self.journal_dir)
        try:
            try:
                src = os.open(self.file, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC, dir_fd=fd)
            except FileNotFoundError:
                return None
            with os.fdopen(src, 'rb') as stream:
                meta = os.fstat(stream.fileno())
                if not stat.S_ISREG(meta.st_mode) or meta.st_uid != os.geteuid() or stat.S_IMODE(meta.st_mode) != 0o600 or meta.st_nlink != 1 or meta.st_size > MAX_JOURNAL:
                    raise ValueError('Unsafe recovery journal')
                raw = stream.read(MAX_JOURNAL + 1)
            data = json.loads(raw)
            if (data.get('format') != 1 or data.get('manifest') != self.manifest or
                    data.get('phase') not in ('recording', 'reverting', 'reverted') or
                    not isinstance(data.get('entries'), list)):
                raise ValueError('Recovery journal mismatch')
            return data
        finally:
            os.close(fd)

    def _write(self, data):
        raw = (json.dumps(data, sort_keys=True, separators=(',', ':')) + '\n').encode()
        if len(raw) > MAX_JOURNAL:
            raise ValueError('Recovery journal too large')
        fd = anchored_directory(self.journal_dir)
        name = '.recovery-' + secrets.token_hex(16)
        try:
            out = os.open(name, os.O_CREAT | os.O_EXCL | os.O_WRONLY | os.O_NOFOLLOW | os.O_CLOEXEC, 0o600, dir_fd=fd)
            try:
                with os.fdopen(out, 'wb') as stream:
                    stream.write(raw)
                    stream.flush()
                    os.fsync(stream.fileno())
                os.replace(name, self.file, src_dir_fd=fd, dst_dir_fd=fd)
                os.fsync(fd)
            finally:
                try: os.unlink(name, dir_fd=fd)
                except FileNotFoundError: pass
        finally:
            os.close(fd)

    def start(self):
        if self._read() is not None:
            raise ValueError('Existing recovery journal must be inspected, not overwritten')
        self._write({'format': 1, 'manifest': self.manifest, 'phase': 'recording', 'entries': []})

    def record(self, relative, kind):
        data = self._read()
        if data is None or data['phase'] != 'recording':
            raise ValueError('Recovery journal is not recording')
        fd, name = inside(self.root, relative)
        try:
            entry = {'path': relative, **fingerprint(fd, name, kind)}
        finally:
            os.close(fd)
        if any(old['path'] == relative for old in data['entries']):
            raise ValueError('Already recorded recovery path')
        data['entries'].append(entry)
        self._write(data)

    def recover(self):
        """Explicit recovery; preflight entire transaction before first deletion."""
        data = self._read()
        if data is None or data['phase'] == 'reverted':
            raise ValueError('No pending recovery transaction')
        # No deletion on any mismatch. Missing entries are allowed only after
        # recovery was durably marked started, to resume an interrupted rollback.
        for entry in reversed(data['entries']):
            try:
                fd, name = inside(self.root, entry['path'])
            except FileNotFoundError:
                if data['phase'] == 'reverting':
                    continue
                raise ValueError('Recovery parent directory disappeared before rollback')
            try:
                try: current = fingerprint(fd, name, entry['kind'])
                except FileNotFoundError:
                    if data['phase'] == 'reverting':
                        continue
                    raise ValueError('Recovery object disappeared before rollback')
                if current != {key: value for key, value in entry.items() if key != 'path'}:
                    raise ValueError('Recovery refused: object replaced or changed')
            finally:
                os.close(fd)
        data['phase'] = 'reverting'
        self._write(data)
        for entry in reversed(data['entries']):
            try:
                fd, name = inside(self.root, entry['path'])
            except FileNotFoundError:
                continue  # A prior interrupted recovery already removed this parent.
            try:
                try: current = fingerprint(fd, name, entry['kind'])
                except FileNotFoundError: continue
                if current != {key: value for key, value in entry.items() if key != 'path'}:
                    raise ValueError('Recovery halted: object changed during rollback')
                if entry['kind'] == 'directory':
                    os.rmdir(name, dir_fd=fd)  # refuses nonempty directories
                else:
                    os.unlink(name, dir_fd=fd)  # never follows a symlink
                os.fsync(fd)
            finally:
                os.close(fd)
        data['phase'] = 'reverted'
        self._write(data)
        return {'status': 'reverted', 'objects': len(data['entries'])}
