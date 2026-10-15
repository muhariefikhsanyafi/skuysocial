#!/usr/bin/env python3
"""Private, single-worker development queue. Python 3.11+, no pip dependencies."""
from __future__ import annotations

import argparse
import contextlib
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import resource
import selectors
import signal
import shutil
import sqlite3
import subprocess
import sys
import time
import tomllib
import uuid

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
TERMINAL = {'blocked', 'failed', 'completed'}
SCHEMA = {
    'type': 'object', 'additionalProperties': False,
    'properties': {
        'summary': {'type': 'string'},
        'acceptance_met': {'type': 'boolean'},
        'findings': {'type': 'array', 'items': {'type': 'string'}},
    }, 'required': ['summary', 'acceptance_met', 'findings'],
}


def now():
    return dt.datetime.now(dt.timezone.utc).isoformat()


def private_json(path, data):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    temporary = path.with_suffix(path.suffix + '.tmp')
    with temporary.open('w') as stream:
        os.chmod(temporary, 0o600)
        json.dump(data, stream, indent=2)
        stream.write('\n')
        stream.flush()
        os.fsync(stream.fileno())
    temporary.replace(path)


def redact(text):
    text = re.sub(r'(?i)(bearer\s+)[\w.\-]+', r'\1[REDACTED]', text)
    text = re.sub(r'(?i)((?:password|token|api_key|secret)\s*[=:]\s*)[^\s,;]+', r'\1[REDACTED]', text)
    return re.sub(r'\b(?:gh[pousr]_|sk-)[A-Za-z0-9_\-]+', '[REDACTED]', text)


class ClosingConnection(sqlite3.Connection):
    """Commit/rollback and close SQLite explicitly after each queue transaction."""
    def __exit__(self, exc_type, exc_value, traceback):
        try:
            return super().__exit__(exc_type, exc_value, traceback)
        finally:
            self.close()


class Queue:
    def __init__(self, state):
        self.state = Path(state).resolve()
        self.state.mkdir(parents=True, exist_ok=True, mode=0o700)
        os.chmod(self.state, 0o700)
        self.db = self.state / 'queue.sqlite3'
        with self.connect() as db:
            db.execute('PRAGMA journal_mode=WAL')
            db.executescript('''
              CREATE TABLE IF NOT EXISTS tasks (
                id TEXT PRIMARY KEY, description TEXT NOT NULL, acceptance TEXT NOT NULL,
                priority INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'queued',
                progress TEXT NOT NULL DEFAULT 'Waiting', attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL, timeout INTEGER NOT NULL, created TEXT NOT NULL,
                updated TEXT NOT NULL, base TEXT NOT NULL, branch TEXT, workspace TEXT,
                deadline REAL, report TEXT, qa TEXT NOT NULL DEFAULT '[]',
                source TEXT UNIQUE, agent_calls INTEGER NOT NULL DEFAULT 0,
                tokens INTEGER NOT NULL DEFAULT 0
              );
              CREATE TABLE IF NOT EXISTS events (
                seq INTEGER PRIMARY KEY AUTOINCREMENT, task TEXT NOT NULL,
                at TEXT NOT NULL, status TEXT NOT NULL, message TEXT NOT NULL
              );
            ''')
        os.chmod(self.db, 0o600)

    def connect(self):
        db = sqlite3.connect(self.db, timeout=15, factory=ClosingConnection)
        db.row_factory = sqlite3.Row
        db.execute('PRAGMA synchronous=FULL')
        return db

    def submit(self, description, acceptance, base, priority=0, attempts=3, timeout=1800, source=None):
        if not description.strip() or not acceptance.strip():
            raise ValueError('Description and acceptance criteria are required')
        if len(description) > 20000 or len(acceptance) > 20000:
            raise ValueError('Task text exceeds 20,000 characters')
        if not 1 <= attempts <= 3 or not 10 <= timeout <= 3600 or not -100 <= priority <= 100:
            raise ValueError('Limits: attempts 1..3, timeout 10..3600 seconds, priority -100..100')
        task = uuid.uuid4().hex
        with self.connect() as db:
            db.execute('BEGIN IMMEDIATE')
            if source:
                existing = db.execute('SELECT id FROM tasks WHERE source=?', (source,)).fetchone()
                if existing:
                    return existing['id']
            if db.execute("SELECT COUNT(*) FROM tasks WHERE status='queued'").fetchone()[0] >= 100:
                raise ValueError('Pending queue limit reached (100)')
            db.execute('''INSERT INTO tasks
                (id,description,acceptance,priority,max_attempts,timeout,created,updated,base,source)
                VALUES (?,?,?,?,?,?,?,?,?,?)''',
                (task, description, acceptance, priority, attempts, timeout, now(), now(), base, source))
            db.execute('INSERT INTO events(task,at,status,message) VALUES (?,?,?,?)',
                       (task, now(), 'queued', 'Accepted'))
        return task

    def get(self, task):
        with self.connect() as db:
            row = db.execute('SELECT * FROM tasks WHERE id=?', (task,)).fetchone()
        if row is None:
            raise ValueError('Unknown task ID')
        return dict(row)

    def list(self, limit=50):
        with self.connect() as db:
            return [dict(row) for row in db.execute(
                'SELECT * FROM tasks ORDER BY created DESC LIMIT ?', (min(limit, 100),))]

    def update(self, task, **fields):
        allowed = {'status', 'progress', 'attempts', 'branch', 'workspace', 'deadline',
                   'report', 'qa', 'agent_calls', 'tokens'}
        if not fields.keys() <= allowed:
            raise ValueError('Invalid task field')
        fields['updated'] = now()
        with self.connect() as db:
            db.execute('UPDATE tasks SET ' + ','.join(f'{key}=?' for key in fields) + ' WHERE id=?',
                       (*fields.values(), task))
            if 'progress' in fields:
                status = fields.get('status', self.get(task)['status'])
                db.execute('INSERT INTO events(task,at,status,message) VALUES (?,?,?,?)',
                           (task, now(), status, fields['progress']))

    def claim(self):
        with self.connect() as db:
            db.execute('BEGIN IMMEDIATE')
            if db.execute("SELECT 1 FROM tasks WHERE status IN ('running','testing')").fetchone():
                return None
            row = db.execute("SELECT * FROM tasks WHERE status='queued' ORDER BY priority DESC,created LIMIT 1").fetchone()
            if row is None:
                return None
            db.execute("UPDATE tasks SET status='running',deadline=?,updated=? WHERE id=?",
                       (time.time() + row['timeout'], now(), row['id']))
        self.update(row['id'], status='running', progress='Preparing isolated workspace')
        return self.get(row['id'])

    def recover(self):
        # Never replay an interrupted agent turn: it may have performed a non-idempotent action.
        with self.connect() as db:
            ids = [row[0] for row in db.execute("SELECT id FROM tasks WHERE status IN ('running','testing')")]
        for task in ids:
            self.finish(task, 'blocked', 'Worker interrupted; inspect retained workspace before explicit retry')
        return ids

    def finish(self, task, status, message):
        self.update(task, status=status, progress=message)
        record = self.get(task)
        report = {'task': record, 'finished': now(), 'deployment': 'DISABLED',
                  'commit_created': False, 'github_push': False}
        path = self.state / 'artifacts' / task / 'report.json'
        private_json(path, report)
        self.update(task, report=str(path))

    def retry(self, task):
        record = self.get(task)
        if record['status'] not in {'failed', 'blocked'}:
            raise ValueError('Only failed or blocked tasks can be retried after inspection')
        if record['attempts'] >= record['max_attempts'] or record['agent_calls'] >= 6 or record['tokens'] >= 250000:
            raise ValueError('Lifetime task budget exhausted; create a separately scoped task')
        self.update(task, status='queued', progress='Explicit retry approved after workspace inspection')


@contextlib.contextmanager
def worker_lock(queue):
    with (queue.state / 'worker.lock').open('a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError as error:
            raise RuntimeError('Another worker owns this queue') from error
        yield


def clean_env():
    # Do not inherit credentials, DB settings, SSH agents, or provider API keys.
    env = {key: os.environ[key] for key in ('PATH', 'HOME', 'USER', 'LANG', 'CODEX_HOME',
                                           'XDG_RUNTIME_DIR', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_PACKAGES_CACHE',
                                           'APP_SERVICES_CACHE', 'VIEW_COMPILED_PATH') if key in os.environ}
    env.update({'APP_ENV': 'testing', 'APP_DEBUG': 'false', 'APP_URL': 'http://localhost',
                'APP_KEY': 'base64:' + 'BwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwc=',
                'DB_CONNECTION': 'sqlite', 'DB_DATABASE': ':memory:', 'DB_URL': '',
                'CACHE_STORE': 'array', 'SESSION_DRIVER': 'array', 'QUEUE_CONNECTION': 'sync',
                'MAIL_MAILER': 'array', 'LOG_CHANNEL': 'stderr', 'BCRYPT_ROUNDS': '4',
                'ONESIGNAL_ENABLED': 'false', 'TURNSTILE_ENABLED': 'false',
                'GIT_TERMINAL_PROMPT': '0', 'CI': 'true'})
    return env


def child_limits():
    resource.setrlimit(resource.RLIMIT_CORE, (0, 0))
    resource.setrlimit(resource.RLIMIT_FSIZE, (32 * 1024**2, 32 * 1024**2))
    resource.setrlimit(resource.RLIMIT_CPU, (600, 600))


def run_process(argv, cwd, logfile, timeout, stdin=None, codex=False):
    """No shell; bounded capture, process group cleanup, minimal Codex event logs."""
    start = time.monotonic()
    logfile = Path(logfile)
    logfile.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    count, usage, reason = 0, 0, None
    with logfile.open('w') as output:
        os.chmod(logfile, 0o600)
        proc = subprocess.Popen(argv, cwd=cwd, env=clean_env(), start_new_session=True,
                                preexec_fn=child_limits, stdin=subprocess.PIPE if stdin else subprocess.DEVNULL,
                                stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
        selector = selectors.DefaultSelector()
        selector.register(proc.stdout, selectors.EVENT_READ)
        buffer = b''
        try:
            if stdin:
                proc.stdin.write(stdin.encode())
                proc.stdin.close()
            while selector.get_map():
                if time.monotonic() - start >= timeout:
                    reason = 'timeout'
                    break
                for key, _ in selector.select(timeout=min(0.1, max(0.01, timeout))):
                    chunk = os.read(key.fd, 65536)
                    if not chunk:
                        selector.unregister(key.fileobj)
                        continue
                    buffer += chunk
                    if len(buffer) > 1024**2:
                        reason = 'oversized output event'
                        break
                    while b'\n' in buffer:
                        line, buffer = buffer.split(b'\n', 1)
                        decoded = line.decode(errors='replace')
                        if codex:
                            try:
                                event = json.loads(decoded)
                                # Deliberately exclude command output/prompts from inference logs.
                                item = {'type': event.get('type', 'unknown')}
                                if event.get('type') == 'turn.completed':
                                    item['usage'] = event.get('usage', {})
                                    usage += int(item['usage'].get('input_tokens', 0)) + int(item['usage'].get('output_tokens', 0))
                                decoded = json.dumps(item)
                            except (ValueError, TypeError):
                                decoded = '[Codex diagnostic omitted; inspect login/network locally]'
                        decoded = redact(decoded)
                        count += len(decoded.encode())
                        if count > 8 * 1024**2:
                            reason = 'log budget exhausted'
                            break
                        output.write(decoded + '\n')
                        output.flush()
                    if reason:
                        break
                if reason:
                    break
            if buffer and not codex:
                output.write(redact(buffer.decode(errors='replace'))[:65536])
            if reason:
                output.write('\nSTOP: ' + reason + '\n')
            else:
                proc.wait(timeout=max(0.1, timeout - (time.monotonic() - start)))
        except BaseException:
            reason = reason or 'interrupted'
            raise
        finally:
            selector.close()
            # Kill the entire group, including descendants holding pipe FDs after parent exit.
            with contextlib.suppress(ProcessLookupError):
                os.killpg(proc.pid, signal.SIGKILL)
            proc.wait()
            proc.stdout.close()
            if proc.stdin and not proc.stdin.closed:
                proc.stdin.close()
    return {'exit_code': proc.returncode if not reason else 124, 'reason': reason,
            'seconds': round(time.monotonic() - start, 2), 'tokens': usage, 'log': str(logfile)}


def git(repo, *args):
    return subprocess.check_output(['git', '-c', 'safe.directory=' + str(Path(repo).resolve()),
                                    '-C', str(repo), *args], env=clean_env(),
                                   stderr=subprocess.DEVNULL, timeout=30).decode().strip()


def prepare(queue, task, repo):
    workspace = queue.state / 'workspaces' / task['id']
    if workspace.exists():
        if not task['workspace'] or git(workspace, 'rev-parse', 'HEAD') != task['base']:
            raise RuntimeError('Partial workspace or changed HEAD: inspect manually')
        return workspace
    import shutil
    if shutil.disk_usage(queue.state).free < 2 * 1024**3:
        raise RuntimeError('Less than 2 GiB free disk; inspect/archive private workspaces')
    workspace.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    # Admin owns the immutable source; service identity owns the clone. Use pack transport,
    # not cross-owner local object sharing, and trust exactly the configured source path.
    subprocess.run(['git', '-c', 'safe.directory=' + str(repo.resolve()), 'clone',
                    '--no-local', '--no-hardlinks', '--no-checkout', str(repo), str(workspace)],
                   check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=60)
    branch = 'codex/task-' + task['id']
    subprocess.run(['git', '-C', str(workspace), 'checkout', '-b', branch, task['base']],
                   check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=30)
    # Remove remote write capability. No commits/pushes are part of this runner.
    subprocess.run(['git', '-C', str(workspace), 'remote', 'remove', 'origin'], check=True, timeout=30)
    # Copy installed dependencies, never source configuration, uploads, or persistent DBs.
    for dependency in ('vendor', 'node_modules'):
        if (repo / dependency).is_dir():
            shutil.copytree(repo / dependency, workspace / dependency, symlinks=True)
    with (workspace / '.git/info/exclude').open('a') as excludes:
        excludes.write('\n.autopilot-result.json\n.autopilot-review.json\n')
    private_json(workspace / '.git/autopilot-dependencies.json', {
        name: hashlib.sha256((workspace / name).read_bytes()).hexdigest()
        for name in ('composer.lock', 'package-lock.json')
    })
    queue.update(task['id'], workspace=str(workspace), branch=branch)
    return workspace


def export_diff(workspace, artifact):
    # Include new files without staging/index mutation. Never collect excluded secrets/archive/storage.
    changed = git(workspace, 'diff', '--name-only', 'HEAD').splitlines()
    new = git(workspace, 'ls-files', '--others', '--exclude-standard').splitlines()
    from security_check import scan
    findings = scan(workspace, changed + new)['findings']
    if findings:
        # Never include private paths or detected secrets in diff artifacts.
        raise RuntimeError('Protected path or suspected credential changed; diff export blocked')
    with (artifact / 'changes.patch').open('wb') as stream:
        stream.write(subprocess.check_output(['git', '-C', str(workspace), 'diff', '--binary', 'HEAD'], timeout=30))
        for name in new:
            path = workspace / name
            if path.is_symlink() or path.stat().st_size > 2 * 1024**2:
                raise RuntimeError('Unsafe new file in diff')
            result = subprocess.run(['git', 'diff', '--no-index', '--binary', '--', '/dev/null', name],
                                    cwd=workspace, stdout=stream, stderr=subprocess.DEVNULL, timeout=30)
            if result.returncode not in (0, 1):
                raise RuntimeError('Failed to export new file')
    private_json(artifact / 'files.json', changed + new)
    return changed + new


def remaining(queue, task, limit):
    return max(0.01, min(limit, queue.get(task)['deadline'] - time.time()))


def agent(queue, task, workspace, artifact, prompt, review=False):
    record = queue.get(task)
    if record['agent_calls'] >= 6 or record['tokens'] >= 250000:
        raise RuntimeError('Codex usage budget exhausted')
    queue.update(task, agent_calls=record['agent_calls'] + 1)
    resultfile = workspace / ('.autopilot-review.json' if review else '.autopilot-result.json')
    schemafile = artifact / 'result-schema.json'
    private_json(schemafile, SCHEMA)
    resultfile.unlink(missing_ok=True)
    argv = ['codex', '-a', 'never', 'exec', '--ignore-user-config', '--ephemeral', '--json',
            '--sandbox', 'read-only' if review else 'workspace-write', '--cd', str(workspace),
            '--output-schema', str(schemafile), '--output-last-message', str(resultfile), '-']
    result = run_process(argv, workspace, artifact / ('review.log' if review else 'agent.log'),
                         remaining(queue, task, 600), stdin=prompt, codex=True)
    queue.update(task, tokens=record['tokens'] + result['tokens'])
    if result['exit_code'] != 0 or not resultfile.is_file():
        raise RuntimeError('Codex failed or timed out; see private event log and login/network diagnostics')
    data = json.loads(resultfile.read_text())
    if set(data) != {'summary', 'acceptance_met', 'findings'} or not isinstance(data['summary'], str) or \
            type(data['acceptance_met']) is not bool or not isinstance(data['findings'], list) or \
            not all(isinstance(item, str) for item in data['findings']):
        raise RuntimeError('Invalid Codex completion schema')
    private_json(artifact / ('review.json' if review else 'agent.json'), data)
    resultfile.unlink()
    return data


POLICY = '''Follow repository AGENTS.md. Work only in this isolated Development clone.
Do not access Production, secrets, .env, storage/app.pre-final, or skuysocial-app.zip.
Do not commit, push, deploy, invoke privileged commands, change the queue/runner, disable tests,
weaken security, or change business rules to satisfy tests. Do not install dependencies.
The supervisor runs quality gates after your turn. Keep scope narrow and self-review.
If access is unavailable, report the blocker. Do not start background services.
'''


def execute(queue, task, repo, gates):
    taskid = task['id']
    artifact = queue.state / 'artifacts' / taskid
    artifact.mkdir(parents=True, exist_ok=True, mode=0o700)
    workspace = None
    try:
        workspace = prepare(queue, task, repo)
        diagnosis = ''
        while queue.get(taskid)['attempts'] < task['max_attempts']:
            record = queue.get(taskid)
            if time.time() >= record['deadline']:
                raise RuntimeError('Total task deadline exceeded')
            attempt = record['attempts'] + 1
            queue.update(taskid, attempts=attempt, status='running', progress=f'Implementation/repair attempt {attempt}')
            directory = artifact / f'attempt-{attempt}'
            directory.mkdir(mode=0o700)
            response = agent(queue, taskid, workspace, directory, POLICY + '\nTASK:\n' + task['description'] +
                             '\nACCEPTANCE:\n' + task['acceptance'] + '\nPrevious gate diagnosis:\n' + diagnosis)
            files = export_diff(workspace, directory)
            if not response['acceptance_met'] and not files:
                queue.finish(taskid, 'blocked', 'Agent reported unmet acceptance without changes: ' + redact(response['summary'])[:500])
                return
            if git(workspace, 'rev-parse', 'HEAD') != task['base']:
                raise RuntimeError('HEAD changed: commits are not authorized')
            queue.update(taskid, status='testing', progress=f'Quality gates for attempt {attempt}')
            qa = []
            for gate in gates:
                result = run_process([arg.replace('{artifacts}', str(directory)) for arg in gate['argv']], workspace, directory / (gate['name'] + '.log'),
                                     remaining(queue, taskid, gate.get('timeout', 600)))
                qa.append({'name': gate['name'], **result})
            queue.update(taskid, qa=json.dumps(qa))
            private_json(directory / 'qa.json', qa)
            passed = bool(qa) and all(item['exit_code'] == 0 for item in qa)
            if passed and response['acceptance_met']:
                queue.update(taskid, status='running', progress='Read-only Codex review')
                review = agent(queue, taskid, workspace, directory, POLICY + '\nReview the entire uncommitted diff '
                               'and acceptance criteria. Return acceptance_met=false and actionable findings if any '
                               'correctness/security issue remains. Do not edit files.\nTASK:\n' + task['description'] +
                               '\nACCEPTANCE:\n' + task['acceptance'], review=True)
                if review['acceptance_met'] and not review['findings']:
                    export_diff(workspace, artifact)
                    queue.finish(taskid, 'completed', 'Acceptance, quality gates, and read-only review passed; awaiting owner review')
                    return
                diagnosis = json.dumps(review)
            else:
                diagnosis = json.dumps({'acceptance': response, 'gates': qa})
                for item in qa:
                    if item['exit_code']:
                        diagnosis += '\n' + Path(item['log']).read_text()[-12000:]
            private_json(directory / 'diagnosis.json', {'diagnosis': redact(diagnosis), 'files': files})
        queue.finish(taskid, 'failed', 'Bounded attempts exhausted; see QA and diagnosis artifacts')
    except Exception as error:
        if workspace:
            with contextlib.suppress(Exception):
                export_diff(workspace, artifact)
        queue.finish(taskid, 'blocked', redact(str(error)))


def load_config(path=None):
    config = {'repo': str(ROOT), 'state': str(ROOT / '.autopilot'), 'poll_seconds': 10,
              'gates': [{'name': 'quality', 'argv': ['python3', str(ROOT / 'ops/autopilot/quality.py'), '--all', '--output', '{artifacts}/quality'], 'timeout': 900}]}
    if path:
        with Path(path).open('rb') as stream:
            config.update(tomllib.load(stream))
    return config


def sandbox_available(queue):
    helper = shutil.which('bwrap')
    if not helper:
        result = {'ready': False, 'reason': 'bubblewrap not installed'}
    else:
        try:
            probe = subprocess.run([helper, '--ro-bind', '/', '/', '--unshare-user',
                                    '--unshare-pid', '--', '/usr/bin/true'],
                                   capture_output=True, timeout=10, env=clean_env())
            result = {'ready': probe.returncode == 0,
                      'reason': 'ok' if probe.returncode == 0 else 'native sandbox namespace unavailable in service'}
        except (OSError, subprocess.TimeoutExpired):
            result = {'ready': False, 'reason': 'sandbox preflight unavailable'}
    private_json(queue.state / 'capability.json', {'at': now(), **result})
    return result['ready']


def work(config, once=False):
    queue = Queue(config['state'])
    with worker_lock(queue):
        recovered = queue.recover()
        print(json.dumps({'worker': 'started', 'recovered_blocked': recovered}), flush=True)
        while True:
            if config.get('github', {}).get('enabled', False):
                try:
                    from github_bridge import sync
                    sync(config, queue)
                except Exception:
                    private_json(queue.state / 'github-error.json', {'at': now(), 'error': 'GitHub sync failed; check network and restricted token permissions'})
            task = queue.claim() if sandbox_available(queue) else None
            if task:
                execute(queue, task, Path(config['repo']).resolve(), config['gates'])
            private_json(queue.state / 'heartbeat.json', {'at': now(), 'pid': os.getpid()})
            if once:
                return
            time.sleep(max(1, min(60, config['poll_seconds'])))


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config')
    parser.add_argument('--state')
    commands = parser.add_subparsers(dest='command', required=True)
    submit = commands.add_parser('submit')
    submit.add_argument('--description', required=True)
    submit.add_argument('--acceptance', required=True)
    submit.add_argument('--priority', type=int, default=0)
    submit.add_argument('--attempts', type=int, default=3)
    submit.add_argument('--timeout', type=int, default=1800)
    commands.add_parser('list')
    for name in ('show', 'retry'):
        commands.add_parser(name).add_argument('id')
    commands.add_parser('worker').add_argument('--once', action='store_true')
    commands.add_parser('doctor')
    args = parser.parse_args()
    config = load_config(args.config)
    if args.state:
        config['state'] = args.state
    queue = Queue(config['state'])
    if args.command == 'worker':
        work(config, args.once)
    elif args.command == 'submit':
        base = git(config['repo'], 'rev-parse', 'HEAD')
        print(queue.submit(args.description, args.acceptance, base, args.priority, args.attempts, args.timeout))
    elif args.command == 'list':
        print(json.dumps([{key: row[key] for key in ('id', 'status', 'progress', 'attempts', 'branch', 'updated')}
                          for row in queue.list()], indent=2))
    elif args.command == 'show':
        print(json.dumps(queue.get(args.id), indent=2))
    elif args.command == 'retry':
        queue.retry(args.id)
    elif args.command == 'doctor':
        print(json.dumps({'repo': config['repo'], 'state': str(queue.state), 'head': git(config['repo'], 'rev-parse', 'HEAD'),
                          'production_auto_deploy': 'DISABLED',
                          'sandbox': json.loads((queue.state / 'capability.json').read_text()) if (queue.state / 'capability.json').exists() else None, 'heartbeat': json.loads((queue.state / 'heartbeat.json').read_text())
                          if (queue.state / 'heartbeat.json').exists() else None}, indent=2))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, RuntimeError) as exc:
        print(redact(str(exc)), file=sys.stderr)
        sys.exit(1)
