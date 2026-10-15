#!/usr/bin/env python3
"""Optional owner-only GitHub Issues intake. Disabled until a restricted token is provisioned."""
from __future__ import annotations

import json
import os
from pathlib import Path
import re
import stat
import sys
import urllib.error
import urllib.parse
import urllib.request

from runner import Queue, git, private_json


def issue_task(issue, owner):
    if 'pull_request' in issue or issue.get('user', {}).get('login', '').lower() != owner.lower():
        return None
    if 'autopilot-approved' not in [label['name'] for label in issue.get('labels', [])]:
        return None
    body = issue.get('body') or ''
    description = re.search(r'### Task\s*\n(.*?)(?=\n### |\Z)', body, re.S)
    acceptance = re.search(r'### Acceptance criteria\s*\n(.*?)(?=\n### |\Z)', body, re.S)
    if not description or not acceptance or not description[1].strip() or not acceptance[1].strip():
        return None
    return {'description': issue['title'] + '\n' + description[1].strip(), 'acceptance': acceptance[1].strip()}


def status_comment(task):
    """Publish queue state only; detailed operational evidence stays on the host."""
    if not re.fullmatch(r'[a-f0-9]{32}', task['id']):
        raise ValueError('Invalid status marker')
    if task['status'] not in {'queued', 'running', 'testing', 'blocked', 'failed', 'completed'}:
        raise ValueError('Invalid queue status')
    marker = '<!-- skuysocial-autopilot:' + task['id'] + ' -->'
    body = (marker + '\nStatus antrean: **' + task['status'] + '**.' +
            '\n\nProduction auto-deploy: **DISABLED**.' +
            '\n\nRincian operasional, hasil QA, log, dan patch tetap privat.')
    return marker, body


class GitHub:
    def __init__(self, repository, token_file):
        if not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', repository):
            raise ValueError('Invalid repository')
        token_file = Path(token_file)
        info = token_file.lstat()
        if not stat.S_ISREG(info.st_mode) or info.st_mode & 0o077 or info.st_uid != os.getuid():
            raise ValueError('Token must be a private regular file owned by the worker (0600)')
        self.repository = repository
        self.token = token_file.read_text().strip()
        if not self.token:
            raise ValueError('Empty GitHub token')

    def request(self, suffix, data=None):
        request = urllib.request.Request('https://api.github.com/repos/' + self.repository + suffix,
                                         data=json.dumps(data).encode() if data is not None else None,
                                         headers={'Authorization': 'Bearer ' + self.token,
                                                  'Accept': 'application/vnd.github+json',
                                                  'Content-Type': 'application/json',
                                                  'User-Agent': 'SkuySocial-Development-Queue'})
        # Never log headers, tokens, or remote response bodies.
        try:
            with urllib.request.urlopen(request, timeout=15) as response:
                return json.load(response)
        except urllib.error.HTTPError as error:
            raise RuntimeError(f'GitHub API HTTP {error.code}; inspect restricted token permissions') from None


def sync(config, queue):
    options = config.get('github', {})
    if not options.get('enabled', False):
        return
    api = GitHub(options['repository'], options['token_file'])
    owner = options['owner']
    checkpoint = queue.state / 'github-sync.json'
    cache = json.loads(checkpoint.read_text()) if checkpoint.exists() else {}
    # Bounded intake; sort oldest first so the first 100 eligible issues are processed first.
    suffix = '/issues?state=open&labels=autopilot-approved&sort=created&direction=asc&per_page=100'
    issues = api.request(suffix)
    for issue in issues:
        task = issue_task(issue, owner)
        if task:
            queue.submit(**task, base=git(config['repo'], 'rev-parse', 'HEAD'),
                         source=f"github:{options['repository']}:{issue['number']}")
    for task in queue.list(100):
        prefix = 'github:' + options['repository'] + ':'
        if not (task.get('source') or '').startswith(prefix):
            continue
        number = int(task['source'][len(prefix):])
        state = task['status']
        if cache.get(task['id']) == state:
            continue
        # Update one bot-owned comment instead of appending duplicate notifications on retries.
        marker, body = status_comment(task)
        comments = api.request(f'/issues/{number}/comments?per_page=100')
        existing = next((comment for comment in comments if comment.get('body', '').startswith(marker) and
                         comment.get('user', {}).get('login', '').lower() == options['bot_login'].lower()), None)
        if not existing and len(comments) >= 100:
            raise RuntimeError('Comment history exceeds safe bounded lookup; inspect before publishing')
        if existing:
            # PATCH updates are idempotent; urllib infers POST by default so set method explicitly.
            request = urllib.request.Request('https://api.github.com/repos/' + api.repository +
                                             '/issues/comments/' + str(existing['id']), method='PATCH',
                                             data=json.dumps({'body': body}).encode(),
                                             headers={'Authorization': 'Bearer ' + api.token,
                                                      'Accept': 'application/vnd.github+json',
                                                      'Content-Type': 'application/json', 'User-Agent': 'SkuySocial-Development-Queue'})
            with urllib.request.urlopen(request, timeout=15) as response:
                response.read(1024)
        else:
            api.request(f'/issues/{number}/comments', {'body': body})
        cache[task['id']] = state
        private_json(checkpoint, cache)
