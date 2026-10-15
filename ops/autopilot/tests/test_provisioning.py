import copy
import hashlib
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
import io
from contextlib import redirect_stdout
from types import SimpleNamespace

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import provision_admin as provision


class ProvisioningTests(unittest.TestCase):
    def manifest(self):
        names = ['source/ops/autopilot/runner.py', 'source/ops/autopilot/service_guard.py',
                 'source/ops/autopilot/validate_service.py', 'source/composer.lock', 'source/package-lock.json',
                 'source/.git/HEAD', 'units/' + provision.MAIN, 'units/' + provision.VALIDATION, 'config/runner.toml']
        return {'format': 1, 'scope': 'akamai-development', 'production_enabled': False,
                'host_machine_id_sha256': 'a' * 64,
                'files': [{'path': name, 'kind': 'file', 'size': 0,
                           'sha256': hashlib.sha256(b'').hexdigest(), 'executable': False} for name in names]}

    def test_scope_traversal_private_files_and_extra_units_are_rejected(self):
        valid = self.manifest()
        provision.validate_manifest(valid)
        for path in ('../etc/sudoers', 'source/.env.production', 'source/database.sqlite',
                     'source/.ssh/id_rsa', 'source/storage/app.pre-final/upload',
                     'source/skuysocial-app.zip', 'units/unreviewed.service',
                     'config/extra.toml', 'source/docs/autopilot/READINESS.md'):
            altered = copy.deepcopy(valid)
            altered['files'].append({**altered['files'][0], 'path': path})
            with self.assertRaises(ValueError, msg=path):
                provision.validate_manifest(altered)
        valid['production_enabled'] = True
        with self.assertRaises(ValueError): provision.validate_manifest(valid)

    def test_package_reader_rejects_ancestor_and_file_symlinks(self):
        with tempfile.TemporaryDirectory() as directory, tempfile.TemporaryDirectory() as outside:
            root = Path(directory); foreign = Path(outside)
            (foreign / 'data').write_text('synthetic private data')
            (root / 'linked').symlink_to(foreign, target_is_directory=True)
            (root / 'file').symlink_to(foreign / 'data')
            fd = os.open(root, os.O_RDONLY | os.O_DIRECTORY)
            try:
                for name in ('linked/data', 'file'):
                    with self.assertRaises(OSError): provision.read_regular(fd, name, 100)
            finally: os.close(fd)

    def test_manifest_duplicate_and_escaping_dependency_link_are_rejected(self):
        valid = self.manifest()
        altered = copy.deepcopy(valid); altered['files'].append(altered['files'][0])
        with self.assertRaises(ValueError): provision.validate_manifest(altered)
        valid['files'].append({'path': 'source/node_modules/.bin/tool', 'kind': 'link', 'target': '../../../../etc/passwd'})
        with self.assertRaises(ValueError): provision.validate_manifest(valid)

    def test_unit_contract_rejects_root_execution_hooks_and_security_changes(self):
        folder = Path(__file__).resolve().parents[1]
        main = (folder / provision.MAIN).read_bytes()
        validation = (folder / provision.VALIDATION).read_bytes()
        provision.check_unit(main, validation=False)
        provision.check_unit(validation, validation=True)
        for data in (main.replace(b'User=skuysocial-autopilot', b'User=root'),
                     main.replace(b'ProtectHome=true', b'ProtectHome=false'),
                     main.replace(b'[Service]', b'[Service]\nExecStartPost=+/bin/sh -c something'),
                     main.replace(b'[Service]', b'[Service]\nEnvironmentFile=/private/credentials')):
            with self.assertRaises(ValueError): provision.check_unit(data, validation=False)

    def test_plan_grants_no_sudo_and_keeps_production_disabled(self):
        plan = provision.plan('a' * 64, 10)
        self.assertFalse(plan['account']['sudo'])
        self.assertFalse(plan['network_or_package_install_as_root'])
        self.assertEqual('DISABLED', plan['production_auto_deploy'])
        self.assertNotIn('/home/skuysocialdev', plan['managed_roots'])

    def test_default_cli_is_readonly_and_never_calls_privileged_operations(self):
        fd = os.open('/dev/null', os.O_RDONLY)
        with patch.object(sys, 'argv', ['provision_admin.py', '--manifest-sha256', 'a' * 64]), \
             patch.object(provision, 'load_package', return_value=(fd, {'host_machine_id_sha256': 'b' * 64}, [])), \
             patch.object(provision, 'host_check'), \
             patch.object(provision, 'command', side_effect=AssertionError('privileged command forbidden')), \
             patch.object(provision, 'apply', side_effect=AssertionError('apply forbidden')), redirect_stdout(io.StringIO()) as output:
            provision.main()
        self.assertIn('READ-ONLY', output.getvalue())


    def test_directory_rejects_symlink_without_changing_target_permissions(self):
        with tempfile.TemporaryDirectory() as tmp:
            parent = Path(tmp)
            protected = parent / 'protected'
            protected.mkdir(mode=0o700)
            link = parent / 'managed'
            link.symlink_to(protected, target_is_directory=True)
            before = protected.stat().st_mode & 0o777
            with self.assertRaises(OSError):
                provision.directory(link, os.getuid(), os.getgid(), 0o755)
            self.assertEqual(before, protected.stat().st_mode & 0o777)

    def test_rerun_directory_contract_rejects_permission_drift(self):
        with tempfile.TemporaryDirectory() as tmp:
            target = Path(tmp) / 'managed'
            target.mkdir(mode=0o700)
            provision.check_directory(target, os.getuid(), os.getgid(), 0o700)
            target.chmod(0o755)
            with self.assertRaises(ValueError):
                provision.check_directory(target, os.getuid(), os.getgid(), 0o700)

    def test_pinned_parent_and_file_symlinks_are_refused(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            real = root / 'real'
            real.mkdir()
            (root / 'link').symlink_to(real, target_is_directory=True)
            with self.assertRaises(OSError):
                provision.dir_fd(root / 'link')
            target = root / 'target'
            target.write_text('fixture')
            (root / 'aliased').symlink_to(target)
            payload = root / 'payload'
            payload.write_text('fixture')
            handle = os.open(root, os.O_RDONLY | os.O_DIRECTORY)
            record = {'path': 'payload', 'size': 7,
                      'sha256': hashlib.sha256(b'fixture').hexdigest(), 'executable': False}
            try:
                with self.assertRaises(OSError):
                    provision.install_file(handle, record, root / 'aliased', os.getgid())
            finally:
                os.close(handle)
            self.assertEqual('fixture', target.read_text())

    def test_existing_service_account_rejects_shared_uid_and_gid(self):
        user = SimpleNamespace(pw_name=provision.ACCOUNT, pw_uid=180, pw_gid=180,
                               pw_dir=str(provision.STATE), pw_shell='/usr/sbin/nologin')
        group = SimpleNamespace(gr_name=provision.ACCOUNT, gr_gid=180, gr_mem=[])
        other_user = SimpleNamespace(pw_name='other', pw_uid=180, pw_gid=999)
        other_group = SimpleNamespace(gr_name='other', gr_gid=180, gr_mem=[])
        with patch.object(provision.pwd, 'getpwnam', return_value=user), \
             patch.object(provision.grp, 'getgrnam', return_value=group), \
             patch.object(provision.os, 'getgrouplist', return_value=[180]), \
             patch.object(provision.pwd, 'getpwall', return_value=[user, other_user]), \
             patch.object(provision.grp, 'getgrall', return_value=[group]):
            with self.assertRaises(ValueError): provision.existing_account()
        with patch.object(provision.pwd, 'getpwnam', return_value=user), \
             patch.object(provision.grp, 'getgrnam', return_value=group), \
             patch.object(provision.os, 'getgrouplist', return_value=[180]), \
             patch.object(provision.pwd, 'getpwall', return_value=[user]), \
             patch.object(provision.grp, 'getgrall', return_value=[group, other_group]):
            with self.assertRaises(ValueError): provision.existing_account()

    def test_root_apply_is_blocked_even_when_identity_is_root(self):
        with patch.object(provision.os, 'geteuid', return_value=0), \
             patch.object(provision, 'host_check', side_effect=AssertionError('must not touch host')):
            with self.assertRaises(ValueError):
                provision.apply(None, {}, [], 'a' * 64)

    def test_existing_root_group_alias_is_rejected_before_creation(self):
        with patch.object(provision.pwd, 'getpwnam', side_effect=KeyError), \
             patch.object(provision.grp, 'getgrnam', return_value=SimpleNamespace(gr_gid=0)):
            with self.assertRaises(ValueError): provision.existing_account()


if __name__ == '__main__': unittest.main()
