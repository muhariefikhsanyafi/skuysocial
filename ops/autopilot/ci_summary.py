#!/usr/bin/env python3
import json
from pathlib import Path
import sys

root = Path(sys.argv[1])
print('SkuySocial quality results\n')
if not (root / 'results.json').exists():
    print('Quality gates did not complete. Inspect the failed workflow step.')
else:
    for item in json.loads((root / 'results.json').read_text()):
        print('- ' + item['name'] + ': ' + ('PASS' if item['exit_code'] == 0 else 'FAIL') +
              ' (' + str(item['seconds']) + ' s)')
if (root / 'php-failures.json').exists():
    print('\nPHP failures:\n')
    for item in json.loads((root / 'php-failures.json').read_text()):
        print('- `' + item['test'].replace('`', '') + '`')
print('\nPRODUCTION AUTO-DEPLOY: DISABLED')
