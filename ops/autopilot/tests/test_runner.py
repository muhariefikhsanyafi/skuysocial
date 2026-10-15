import concurrent.futures
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import runner
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'deploy'))
import release


class QueueTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.state = Path(self.temp.name)
        self.queue = runner.Queue(self.state)

    def tearDown(self):
        self.temp.cleanup()

    def submit(self, **kwargs):
        return self.queue.submit('Implement a bounded fixture', 'Fixture checks pass', 'abc', **kwargs)

    def test_persistence_priority_and_single_claim(self):
        low = self.submit(priority=-1)
        high = self.submit(priority=10)
        reopened = runner.Queue(self.state)
        self.assertEqual(high, reopened.claim()['id'])
        self.assertIsNone(self.queue.claim())
        self.queue.finish(high, 'completed', 'ok')
        self.assertEqual(low, reopened.claim()['id'])
        self.assertEqual(0o700, self.state.stat().st_mode & 0o777)
        self.assertEqual(0o600, self.queue.db.stat().st_mode & 0o777)

    def test_concurrent_claim_only_one_winner(self):
        self.submit()
        self.submit()
        with concurrent.futures.ThreadPoolExecutor(2) as executor:
            results = list(executor.map(lambda _: runner.Queue(self.state).claim(), range(2)))
        self.assertEqual(1, sum(result is not None for result in results))

    def test_crash_recovery_does_not_replay(self):
        task = self.submit()
        self.queue.claim()
        self.queue.update(task, attempts=1)
        recovered = runner.Queue(self.state)
        self.assertEqual([task], recovered.recover())
        self.assertEqual('blocked', recovered.get(task)['status'])
        self.assertIsNone(recovered.claim())
        self.assertTrue(Path(recovered.get(task)['report']).is_file())
        self.assertEqual([], recovered.recover())
        recovered.retry(task)
        self.assertEqual(task, recovered.claim()['id'])

    def test_idempotency_budget_and_validation(self):
        task = self.submit(source='github:1')
        self.assertEqual(task, self.submit(source='github:1'))
        for kwargs in ({'attempts': 4}, {'timeout': 0}, {'priority': 200}):
            with self.assertRaises(ValueError):
                self.submit(**kwargs)
        self.queue.update(task, status='failed', attempts=3)
        with self.assertRaises(ValueError):
            self.queue.retry(task)

    def test_worker_exclusion(self):
        with runner.worker_lock(self.queue):
            with self.assertRaises(RuntimeError):
                with runner.worker_lock(self.queue):
                    pass

    def test_timeout_and_process_group_cleanup(self):
        pidfile = self.state / 'pid'
        program = ('import subprocess,time; from pathlib import Path; '
                   'p=subprocess.Popen(["sleep","30"]); '
                   f'Path({str(pidfile)!r}).write_text(str(p.pid)); time.sleep(30)')
        result = runner.run_process([sys.executable, '-c', program], self.state, self.state / 'timeout.log', 2.0)
        self.assertEqual('timeout', result['reason'])
        self.assertEqual(124, result['exit_code'])
        pid = int(pidfile.read_text())
        # The process may disappear between looking up /proc and reading it.
        # A missing process or a zombie both mean cleanup succeeded.
        procstat = Path(f'/proc/{pid}/stat')
        try:
            state = procstat.read_text().split()[2]
        except (FileNotFoundError, ProcessLookupError):
            state = None
        self.assertIn(state, (None, 'Z'))

    def test_sensitive_codex_output_omitted(self):
        secret = 'ghp_' + 'a' * 30
        code = 'print(' + repr(json.dumps({'type': 'item.completed', 'item': {'text': secret}})) + ')'
        result = runner.run_process([sys.executable, '-c', code], self.state, self.state / 'codex.log', 5, codex=True)
        self.assertEqual(0, result['exit_code'])
        self.assertNotIn(secret, (self.state / 'codex.log').read_text())

    def test_bounded_repair_test_review_cycle(self):
        task = self.submit(attempts=2)
        record = self.queue.claim()
        workspace = self.state / 'fixture'
        workspace.mkdir()
        calls = []

        def agent(queue, taskid, cwd, artifact, prompt, review=False):
            calls.append(('review' if review else 'coding', prompt))
            (cwd / 'answer').write_text('wrong' if len(calls) == 1 else 'correct')
            return {'summary': 'fixture', 'acceptance_met': True, 'findings': []}

        check = 'from pathlib import Path; assert Path("answer").read_text()=="correct"'
        with patch.object(runner, 'prepare', return_value=workspace), \
             patch.object(runner, 'agent', side_effect=agent), \
             patch.object(runner, 'export_diff', return_value=['answer']), \
             patch.object(runner, 'git', return_value='abc'):
            runner.execute(self.queue, record, self.state,
                           [{'name': 'fixture', 'argv': [sys.executable, '-c', check], 'timeout': 5}])
        self.assertEqual('completed', self.queue.get(task)['status'])
        self.assertEqual(2, self.queue.get(task)['attempts'])
        self.assertEqual(['coding', 'coding', 'review'], [call[0] for call in calls])
        self.assertIn('AssertionError', calls[1][1])
        self.assertTrue((self.state / 'artifacts' / task / 'attempt-1/diagnosis.json').exists())

    def test_failure_attempt_limit_and_blocked_agent(self):
        task = self.submit(attempts=1)
        record = self.queue.claim()
        with patch.object(runner, 'prepare', return_value=self.state), \
             patch.object(runner, 'agent', side_effect=RuntimeError('unavailable')):
            runner.execute(self.queue, record, self.state, [])
        self.assertEqual('blocked', self.queue.get(task)['status'])
        self.assertEqual(1, self.queue.get(task)['attempts'])

    def test_minimal_environment(self):
        with patch.dict(os.environ, {'DB_DATABASE': '/persistent', 'OPENAI_API_KEY': 'not-for-tests', 'SSH_AUTH_SOCK': 'sock'}):
            env = runner.clean_env()
        self.assertEqual(':memory:', env['DB_DATABASE'])
        self.assertNotIn('OPENAI_API_KEY', env)
        self.assertNotIn('SSH_AUTH_SOCK', env)


class ReleaseTests(unittest.TestCase):
    def test_success_and_rollback_preserve_data(self):
        for fail in (False, True):
            result = release.rehearsal(fail)
            self.assertEqual(fail, result['code_rollback'])
            self.assertTrue(result['persistent_data_unchanged'])
            self.assertFalse(result['production_accessed'])

    def test_unsafe_archive_names(self):
        for name in ('../secret', '/etc/passwd', '.env', 'storage/app.pre-final/file', 'database/database.sqlite'):
            self.assertFalse(release.safe_name(name))
        self.assertTrue(release.safe_name('app/Models/User.php'))

    def test_dirty_release_provenance_is_rejected(self):
        with patch.object(release.subprocess, 'check_output', return_value=b' M changed.php'):
            with self.assertRaisesRegex(ValueError, 'clean reviewed commit'):
                release.pack(Path('/unused'), Path('/unused'))


    def test_production_cli_is_locked(self):
        result = subprocess.run([sys.executable, str(Path(release.__file__)), 'deploy'], capture_output=True)
        self.assertNotEqual(0, result.returncode)
        self.assertIn(b'DISABLED', result.stderr)


if __name__ == '__main__':
    unittest.main()
