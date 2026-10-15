import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import github_bridge
import quality
import runner


class IntegrationTests(unittest.TestCase):
    def test_issue_intake_owner_label_and_acceptance_required(self):
        issue = {'title': 'Fix fixture', 'user': {'login': 'Owner'}, 'labels': [{'name': 'autopilot-approved'}],
                 'body': '### Task\n\nChange the fixture\n\n### Acceptance criteria\n\nChecks pass'}
        self.assertIsNotNone(github_bridge.issue_task(issue, 'owner'))
        for modified in ({**issue, 'user': {'login': 'stranger'}}, {**issue, 'labels': []},
                         {**issue, 'pull_request': {}}, {**issue, 'body': 'no criteria'}):
            self.assertIsNone(github_bridge.issue_task(modified, 'owner'))

    def test_disabled_bridge_never_reads_token_or_connects(self):
        with patch.object(github_bridge, 'GitHub', side_effect=AssertionError('must not initialize')):
            github_bridge.sync({}, None)

    def test_status_report_keeps_private_operational_fields_local(self):
        private = 'PRIVATE-OPERATIONAL-EVIDENCE'
        task = {'id': 'a' * 32, 'status': 'blocked', 'base': private, 'branch': private,
                'description': private, 'progress': private, 'qa': private,
                'report': private, 'tokens': private, 'attempts': private}
        marker, body = github_bridge.status_comment(task)
        self.assertTrue(body.startswith(marker))
        self.assertIn('**blocked**', body)
        self.assertIn('**DISABLED**', body)
        self.assertNotIn(private, body)
        with self.assertRaises(ValueError):
            github_bridge.status_comment({**task, 'status': private})

    def test_token_permissions(self):
        with tempfile.TemporaryDirectory() as temp:
            token = Path(temp) / 'token'
            token.write_text('synthetic-fixture')
            token.chmod(0o644)
            with self.assertRaises(ValueError):
                github_bridge.GitHub('owner/repo', token)
            token.chmod(0o600)
            self.assertEqual('owner/repo', github_bridge.GitHub('owner/repo', token).repository)

    def test_quality_refuses_environment_and_weakened_test_database(self):
        with tempfile.TemporaryDirectory() as temp:
            repo = Path(temp)
            xml = '<phpunit><php>' + ''.join(f'<env name="{k}" value="{v}" force="true"/>' for k, v in
                  {'APP_ENV': 'testing', 'DB_CONNECTION': 'sqlite', 'DB_DATABASE': ':memory:'}.items()) + '</php></phpunit>'
            (repo / 'phpunit.xml').write_text(xml)
            quality.validate(repo)
            (repo / '.env').touch()
            with self.assertRaises(RuntimeError):
                quality.validate(repo)
            (repo / '.env').unlink()
            (repo / 'phpunit.xml').write_text(xml.replace(':memory:', '/persistent.sqlite'))
            with self.assertRaises(RuntimeError):
                quality.validate(repo)

    def test_worker_does_not_claim_when_sandbox_preflight_fails(self):
        with tempfile.TemporaryDirectory() as temp:
            queue = runner.Queue(temp)
            taskid = queue.submit('fixture', 'done', 'abc')
            with patch.object(runner, 'sandbox_available', return_value=False), \
                 patch.object(runner, 'execute', side_effect=AssertionError('must not run')):
                runner.work({'state': temp, 'repo': temp, 'poll_seconds': 1}, once=True)
            self.assertEqual('queued', queue.get(taskid)['status'])
            self.assertEqual(0, queue.get(taskid)['agent_calls'])


    def test_cli_arguments_keep_supported_sandbox_and_finite_calls(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            queue = runner.Queue(root / 'state')
            taskid = queue.submit('fixture', 'done', 'abc')
            queue.claim()
            artifact = root / 'artifact'
            artifact.mkdir()
            invocations = []

            def process(argv, cwd, logfile, timeout, **kwargs):
                invocations.append(argv)
                output = Path(argv[argv.index('--output-last-message') + 1])
                output.write_text(json.dumps({'summary': 'done', 'acceptance_met': True, 'findings': []}))
                self.assertLessEqual(timeout, 600)
                return {'exit_code': 0, 'tokens': 5}

            with patch.object(runner, 'run_process', side_effect=process):
                for review in (False, True):
                    runner.agent(queue, taskid, root, artifact, 'fixture', review=review)
            for argv in invocations:
                self.assertLess(argv.index('exec'), argv.index('--ignore-user-config'))
                self.assertEqual('never', argv[argv.index('-a') + 1])
                self.assertNotIn('danger-full-access', argv)
            self.assertEqual('workspace-write', invocations[0][invocations[0].index('--sandbox') + 1])
            self.assertEqual('read-only', invocations[1][invocations[1].index('--sandbox') + 1])
            self.assertEqual(2, queue.get(taskid)['agent_calls'])
            self.assertEqual(10, queue.get(taskid)['tokens'])


if __name__ == '__main__':
    unittest.main()
