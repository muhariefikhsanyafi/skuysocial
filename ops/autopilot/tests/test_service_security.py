import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import runner
import security_check
import service_guard


class ServiceSecurityTests(unittest.TestCase):
    def identity(self, **changes):
        values = {'uid': 1234, 'gid': 1234, 'groups': [1234], 'account_uid': 1234, 'account_gid': 1234,
                  'status': {'NoNewPrivs': '1', 'CapEff': '0', 'CapPrm': '0', 'CapAmb': '0', 'CapBnd': '0'},
                  'environment': {'HOME': str(service_guard.STATE), 'CODEX_HOME': str(service_guard.STATE / 'codex')}}
        values.update(changes)
        return service_guard.identity_errors(**values)

    def test_dedicated_identity_no_capabilities_and_no_inherited_credentials(self):
        self.assertEqual([], self.identity())
        self.assertTrue(self.identity(uid=0))
        self.assertTrue(self.identity(uid=1000))
        self.assertTrue(self.identity(groups=[1234, 1000]))
        self.assertTrue(self.identity(status={'NoNewPrivs': '0'}))
        self.assertTrue(self.identity(environment={'HOME': '/home/developer', 'SSH_AUTH_SOCK': '/synthetic/socket'}))
        status = {'NoNewPrivs': '1', 'CapEff': '0000000000200000', 'CapPrm': '0', 'CapAmb': '0', 'CapBnd': '0'}
        self.assertIn('zero-capabilities-required:CapEff', self.identity(status=status))

    def test_most_specific_mount_must_enforce_readonly_source(self):
        mounts = ('1 0 0:1 / / ro - ext4 root ro\n'
                  '2 1 0:2 / /var/lib/skuysocial-autopilot rw - ext4 state rw\n'
                  '3 1 0:3 / /opt/skuysocial-autopilot/source rw - ext4 source rw\n')
        self.assertIn('rw', service_guard.mount_options(service_guard.SOURCE, mounts))
        self.assertIn('rw', service_guard.mount_options(service_guard.STATE, mounts))
        self.assertIn('ro', service_guard.mount_options('/etc', mounts))
        self.assertEqual(set(), service_guard.mount_options('/opt', ''))

    def test_scanner_does_not_open_private_files_or_symlinks(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for name in ('.env.production', 'database.sqlite', 'skuysocial-app.zip', 'trace.log'):
                (root / name).touch()
            (root / 'linked.txt').symlink_to('/etc/passwd')
            with patch.object(Path, 'read_bytes', side_effect=AssertionError('must not read protected path')):
                report = security_check.scan(root, ['.env.production', 'database.sqlite', 'skuysocial-app.zip', 'trace.log', 'linked.txt'])
            self.assertEqual(5, len(report['findings']))

    def test_scanner_redacts_values_and_catches_untracked_code_tokens(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            secret = 'ghp_' + 'a' * 32
            (root / 'new.py').write_text('TOKEN = "' + secret + '"\n')
            report = security_check.scan(root, ['new.py'])
            self.assertEqual('github-token', report['findings'][0]['category'])
            self.assertNotIn(secret, json.dumps(report))

    def test_operational_reports_are_not_publication_candidates(self):
        for path in ('docs/autopilot/READINESS.md', 'docs/autopilot/UNBLOCK.md',
                     '.autopilot/queue.sqlite3', 'storage/app.pre-final/upload', 'keys/deploy.pem'):
            self.assertTrue(security_check.forbidden_path(path))
        self.assertFalse(security_check.forbidden_path('.env.example'))
        self.assertFalse(security_check.forbidden_path('database/migrations/add_table.php'))

    def test_export_diff_refuses_new_token_without_creating_patch(self):
        with tempfile.TemporaryDirectory() as tmp:
            repo = Path(tmp)
            subprocess.run(['git', '-C', tmp, 'init', '-q'], check=True)
            (repo / 'README.md').write_text('fixture\n')
            subprocess.run(['git', '-C', tmp, 'add', 'README.md'], check=True)
            subprocess.run(['git', '-C', tmp, '-c', 'user.name=Fixture',
                            '-c', 'user.email=fixture@example.invalid', 'commit', '-qm', 'fixture'], check=True)
            (repo / 'danger.py').write_text('TOKEN = "ghp_' + 'a' * 35 + '"\n')
            artifact = repo / 'artifact'
            artifact.mkdir()
            with self.assertRaises(RuntimeError):
                runner.export_diff(repo, artifact)
            self.assertFalse((artifact / 'changes.patch').exists())

    def test_nonlocal_source_clone_preserves_baseline_and_removes_remote(self):
        source = Path(__file__).resolve().parents[3]
        baseline = runner.git(source, 'rev-parse', 'HEAD')
        with tempfile.TemporaryDirectory() as directory:
            queue = runner.Queue(Path(directory) / 'state')
            taskid = queue.submit('Synthetic clone fixture', 'No secrets copied', baseline)
            with patch.object(runner.shutil, 'copytree'):
                workspace = runner.prepare(queue, queue.get(taskid), source)
            self.assertEqual(baseline, runner.git(workspace, 'rev-parse', 'HEAD'))
            self.assertEqual('', runner.git(workspace, 'remote'))
            self.assertFalse((workspace / '.env').exists())
            self.assertFalse((workspace / '.autopilot').exists())
            self.assertTrue(queue.get(taskid)['branch'].startswith('codex/'))


if __name__ == '__main__':
    unittest.main()
