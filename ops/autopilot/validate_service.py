#!/usr/bin/env python3
"""Finite non-root validation invoked only by the reviewed systemd validation unit."""
from __future__ import annotations

import json
import os
from pathlib import Path
import subprocess
import sys
import signal

sys.path.insert(0, str(Path(__file__).resolve().parent))
import runner
import security_check
import service_guard

STATE = Path('/var/lib/skuysocial-autopilot')
OUTPUT = STATE / 'validation'
PUBLIC = Path('/var/lib/skuysocial-autopilot-status/status.json')
SOURCE = Path('/opt/skuysocial-autopilot/source')


def report(stage, **extra):
    # Outward status contains fixed states and booleans only, never task text/logs/credentials.
    data = {'stage': stage, 'production_auto_deploy': 'DISABLED', **extra}
    runner.private_json(OUTPUT / 'report.json', data)
    PUBLIC.parent.mkdir(exist_ok=True)
    runner.private_json(PUBLIC, data)
    os.chmod(PUBLIC, 0o640)
    print(json.dumps(data), flush=True)


def main():
    os.umask(0o077)
    OUTPUT.mkdir(exist_ok=True, mode=0o700)
    receipt = json.loads(Path('/etc/skuysocial-autopilot/provisioning.json').read_text())
    package_id = receipt['manifest_sha256']
    previous = OUTPUT / 'report.json'
    if previous.exists() and json.loads(previous.read_text()).get('stage') == 'blocked-isolation':
        print('Isolation previously failed. Further attempts require administrator review.', flush=True)
        return 80
    problems = service_guard.inspect()
    if problems:
        runner.private_json(OUTPUT / 'isolation-errors.json', problems)
        report('blocked-isolation', isolation_verified=False)
        return 80
    phase_file = OUTPUT / 'recovery-phase.json'
    recovery = runner.Queue(STATE / 'validation-recovery')
    if not phase_file.exists():
        taskid = recovery.submit('Synthetic recovery fixture, never execute Codex',
                                  'Block interrupted task without replay', 'fixture', attempts=1, timeout=60)
        recovery.claim()
        recovery.update(taskid, attempts=1)
        runner.private_json(OUTPUT / 'recovery-sentinel.json', {'preserve': True})
        runner.private_json(phase_file, {'task': taskid, 'phase': 'pending'})
        report('recovery-restart-pending', isolation_verified=True)
        # Restart=on-failure restarts only this non-root validation unit. Never touch user tasks.
        os.kill(os.getpid(), signal.SIGKILL)
    phase = json.loads(phase_file.read_text())
    if phase['phase'] == 'pending':
        recovery.recover()
        if recovery.get(phase['task'])['status'] != 'blocked' or recovery.claim() is not None or \
                json.loads((OUTPUT / 'recovery-sentinel.json').read_text()) != {'preserve': True}:
            report('failed-recovery', isolation_verified=True)
            return 1
        runner.private_json(phase_file, {**phase, 'phase': 'verified'})
    report('testing', isolation_verified=True)
    scan = security_check.scan(SOURCE, security_check.publication_files(SOURCE))
    runner.private_json(OUTPUT / 'secret-scan.json', scan)
    if scan['findings']:
        report('blocked-source-scan', isolation_verified=True)
        return 1
    tests = runner.run_process(['/usr/bin/python3', '-I', '-m', 'unittest', 'discover', '-s',
                                str(SOURCE / 'ops/autopilot/tests'), '-v'], SOURCE, OUTPUT / 'tests.log', 120)
    if tests['exit_code']:
        report('failed-tests', isolation_verified=True)
        return 1
    auth = subprocess.run(['/usr/bin/codex', 'login', 'status'], env=runner.clean_env(),
                          capture_output=True, timeout=20)
    if auth.returncode:
        report('blocked-codex-login', isolation_verified=True, tests_passed=True)
        return 78
    queue_state = STATE / 'validation-queue'
    queue = runner.Queue(queue_state)
    source_key = 'provisioning-dummy:' + package_id
    existing = next((task for task in queue.list(100) if task.get('source') == source_key), None)
    if existing and existing['status'] != 'completed':
        report('blocked-inspection-required', isolation_verified=True, tests_passed=True)
        return 1  # Never replay an interrupted or failed real Codex turn.
    if not existing:
        submit = runner.run_process(['/usr/bin/python3', '-I', str(SOURCE / 'ops/autopilot/runner.py'),
                    '--state', str(queue_state), 'submit', '--description',
                    'Create docs/system-service-probe.txt containing exactly DEVELOPMENT SANDBOX VERIFIED followed by a newline. No application, credential, background service, commit, push or network changes.',
                    '--acceptance', 'Only the requested fixture is changed; it has exact content; preserve all product code.',
                    '--attempts', '1', '--timeout', '600'], SOURCE, OUTPUT / 'submission.log', 30)
        if submit['exit_code']:
            report('failed-queue-submission', isolation_verified=True, tests_passed=True)
            return 1
        taskid = (OUTPUT / 'submission.log').read_text().strip()
        with queue.connect() as db:
            db.execute('UPDATE tasks SET source=? WHERE id=?', (source_key, taskid))
        # The submission process has exited before this worker begins, proving client lifetime separation.
        gates = [{'name': 'fixture', 'argv': ['/usr/bin/python3', '-I', '-c',
                    'from pathlib import Path; assert Path("docs/system-service-probe.txt").read_text()=="DEVELOPMENT SANDBOX VERIFIED" + chr(10)'], 'timeout': 10}]
        runner.work({'repo': str(SOURCE), 'state': str(queue_state), 'gates': gates, 'poll_seconds': 1}, once=True)
        if queue.get(taskid)['status'] != 'completed':
            report('failed-real-queue-task', isolation_verified=True, tests_passed=True)
            return 1
    # Reopen from disk after the submitter exited; never reset existing records or lifetime budgets.
    reopened = runner.Queue(queue_state)
    if not any(task['status'] == 'completed' and task.get('source') == source_key for task in reopened.list(100)):
        report('failed-persistence', isolation_verified=True, tests_passed=True)
        return 1
    runner.private_json(OUTPUT / 'passed.json', {'manifest_sha256': package_id,
                        'native_isolation': True, 'tests': True, 'real_queue': True,
                        'submission_process_exited': True, 'production_auto_deploy': 'DISABLED'})
    report('validated', isolation_verified=True, tests_passed=True, real_queue_passed=True,
           submission_process_independent=True, service_restart_recovery_verified=True,
           physical_desktop_disconnect_tested=False)
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception:
        # Raw provider/command exceptions are deliberately not published.
        try: report('blocked-validation-error')
        except Exception: pass
        sys.exit(1)
