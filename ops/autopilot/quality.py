#!/usr/bin/env python3
"""Run the same isolated quality gates locally and in CI; never load the project .env."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET

sys.path.insert(0, str(Path(__file__).resolve().parent))
from runner import clean_env, private_json, run_process


def validate(repo):
    tree = ET.parse(repo / 'phpunit.xml')
    forced = {item.attrib['name']: item.attrib.get('value') for item in tree.findall('./php/env')
              if item.attrib.get('force') == 'true'}
    if any(forced.get(key) != value for key, value in
           {'APP_ENV': 'testing', 'DB_CONNECTION': 'sqlite', 'DB_DATABASE': ':memory:'}.items()):
        raise RuntimeError('phpunit.xml must force testing + SQLite :memory:')
    if (repo / '.env').exists() or (repo / 'bootstrap/cache/config.php').exists():
        raise RuntimeError('Quality workspace must contain neither .env nor cached app configuration; use an isolated clone')
    fingerprint = repo / '.git/autopilot-dependencies.json'
    if fingerprint.is_file():
        for name, expected in json.loads(fingerprint.read_text()).items():
            if hashlib.sha256((repo / name).read_bytes()).hexdigest() != expected:
                raise RuntimeError('Dependency lockfile changed; isolated locked installation must be provisioned before QA')
    if (repo / 'public/hot').exists():
        raise RuntimeError('Quality workspace must use compiled assets; public/hot exists')


def commands(repo, output, browser=None):
    steps = [
        ('php-unit', ['php', 'vendor/bin/phpunit', '--testsuite', 'Unit']),
        # Blade/Inertia feature tests render through Vite and require compiled manifest.json.
        ('build', ['npm', 'run', 'build']),
        ('php', ['php', 'vendor/bin/phpunit', '--no-output', '--log-junit', str(output / 'phpunit.xml')]),
        ('frontend', ['npm', 'run', 'test:client', '--', '--maxWorkers=2']),
        ('types', ['npm', 'run', 'typecheck']),
        ('smoke', ['php', 'vendor/bin/phpunit', '--filter', 'ReleaseSmokeTest|ExampleTest|TrustedHostTest|AuthenticationTest']),
        ('artifact', ['python3', str(Path(__file__).resolve().parents[1] / 'deploy/release.py'),
                      'validate-build', '--repo', str(repo)]),
    ]
    if browser:
        steps.append(('browser', ['npm', 'run', 'test:browser:theme-navigation', '--',
                                 '--php=' + shutil.which('php'), '--browser=' + browser,
                                 '--output=' + str(output / 'browser')]))
    return steps


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--all', action='store_true')
    parser.add_argument('--repo', type=Path, default=Path.cwd())
    parser.add_argument('--output', type=Path)
    parser.add_argument('--browser')
    parser.add_argument('--only', choices=['php-unit', 'php', 'frontend', 'types', 'build', 'smoke', 'artifact', 'browser'])
    args = parser.parse_args()
    repo = args.repo.resolve()
    validate(repo)
    output = (args.output or Path(tempfile.mkdtemp(prefix='skuy-quality-'))).resolve()
    output.mkdir(parents=True, exist_ok=True, mode=0o700)
    env_dir = output / 'environment'
    env_dir.mkdir(exist_ok=True)
    (env_dir / 'views').mkdir(exist_ok=True)
    # Unique cache paths prevent stale cache/config from selecting a persistent DB.
    os.environ.update({'APP_CONFIG_CACHE': str(env_dir / 'config.php'),
                       'APP_ROUTES_CACHE': str(env_dir / 'routes.php'),
                       'APP_SERVICES_CACHE': str(env_dir / 'services.php'),
                       'APP_PACKAGES_CACHE': str(env_dir / 'packages.php'),
                       'VIEW_COMPILED_PATH': str(env_dir / 'views')})
    results = []
    for name, argv in commands(repo, output, args.browser):
        if args.only and name != args.only:
            continue
        print('Running ' + name, flush=True)
        result = run_process(argv, repo, output / (name + '.log'), 900)
        results.append({'name': name, **result})
        if name == 'php' and (output / 'phpunit.xml').exists():
            junit = ET.parse(output / 'phpunit.xml')
            failures = []
            for case in junit.findall('.//testcase'):
                for error in list(case.findall('failure')) + list(case.findall('error')):
                    failures.append({'test': case.get('classname', '') + '::' + case.get('name', ''),
                                     'message': (error.text or '')[:900]})
            private_json(output / 'php-failures.json', failures)
            print(json.dumps({'php_cases': len(junit.findall('.//testcase')), 'failures': failures}), flush=True)
        print(json.dumps(results[-1]), flush=True)
    private_json(output / 'results.json', results)
    if not results:
        raise RuntimeError('No gates selected; browser gate requires --browser')
    return 0 if all(result['exit_code'] == 0 for result in results) else 1


if __name__ == '__main__':
    sys.exit(main())
