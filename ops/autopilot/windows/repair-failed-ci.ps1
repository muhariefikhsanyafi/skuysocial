$ErrorActionPreference = 'Stop'
# Execute only from a fresh verified GitHub checkout under a dedicated Windows account.
# The Codex process receives no GitHub workflow write token or Production credentials.
$repo = 'muhariefikhsanyafi/skuysocial'
if ($env:GITHUB_REPOSITORY -ne $repo -or
    $env:FIX_SHA -notmatch '^[a-f0-9]{40}$' -or
    $env:FIX_BRANCH -notmatch '^(feat|codex)/[A-Za-z0-9._/-]+$' -or
    $env:FIX_BRANCH.Contains('..') -or $env:FIX_BRANCH.Contains('//')) {
    throw 'Refusing unexpected repository, commit, or branch'
}
$head = (git rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0 -or $head -ne $env:FIX_SHA) { throw 'Checked-out commit does not match verified PR' }
foreach ($required in @('git', 'codex', 'gitleaks')) {
    if (-not (Get-Command $required -ErrorAction SilentlyContinue)) {
        throw "BLOCKED: $required is missing from dedicated Windows runner"
    }
}
# Fail rather than silently selecting the user's ChatGPT account instead of 9Router.
$codexConfig = Join-Path $env:USERPROFILE '.codex\config.toml'
if (-not (Test-Path $codexConfig) -or
    -not (Select-String -Path $codexConfig -Pattern '9router|127\.0\.0\.1:20128|localhost:20128' -Quiet)) {
    throw 'BLOCKED: Codex on this Windows runner is not configured for local 9Router'
}
$client = New-Object System.Net.Sockets.TcpClient
try {
    $pending = $client.BeginConnect('127.0.0.1', 20128, $null, $null)
    if (-not $pending.AsyncWaitHandle.WaitOne(2000)) { throw 'Local 9Router unavailable' }
    $client.EndConnect($pending)
} finally { $client.Dispose() }

$prompt = @"
A SkuySocial development GitHub Actions check failed.
Owned Draft PR: #$env:FIX_PR
Failed run: $env:FAILED_RUN_URL

Repair only the confirmed CI failure. Reproduce with locally available tools if possible.
You may edit only existing tracked files under app/, resources/, routes/, tests/, or lang/.
Do not create files, delete files, edit workflows, execute deployment commands, access
private files, or install system packages. Never access .env, SSH keys, credentials,
Production, databases/backups or service-account configuration.
Do not attempt git commit, push, merge, rebases or force operations.
Keep changes focused; when there is no safe verified fix, leave the tree unchanged.
"@
$log = Join-Path $env:RUNNER_TEMP ('skuy-autofix-' + $env:FIX_SHA.Substring(0, 12) + '.log')
try {
    # Keep model output off the public Actions console; the dedicated account
    # owns the private diagnostic log, which will be removed after the job.
    & codex exec --sandbox workspace-write --skip-git-repo-check $prompt *> $log
    if ($LASTEXITCODE -ne 0) { throw 'Codex run failed (private local diagnostics retained only during job)' }

    $untracked = @(git ls-files --others --exclude-standard)
    if ($untracked.Count -gt 0) { throw 'Agent created new files; requires review' }
    $changes = @(git diff --name-only)
    if ($changes.Count -eq 0) {
        Write-Host 'No safe repair produced. No push performed.'
        exit 0
    }
    $removed = @(git diff --name-only --diff-filter=D)
    if ($removed.Count -gt 0) { throw 'Refusing deleted tracked files' }
    foreach ($file in $changes) {
        $path = $file.Replace('\', '/')
        if ($path -notmatch '^(app|resources|routes|tests|lang)/' -or
            $path -match '(^|/)(\.env|\.ssh|\.aws|auth\.json)' -or
            $path -match '\.(pem|key|p12|pfx|sqlite|db|bak)$') {
            throw 'Changes outside approved Development source paths'
        }
    }
    & gitleaks dir --no-banner --redact --exit-code 1 .
    if ($LASTEXITCODE -ne 0) { throw 'Secret scan failed: no changes published' }
    & git diff --check
    if ($LASTEXITCODE -ne 0) { throw 'Diff validation failed' }
    & git add -u
    if ($LASTEXITCODE -ne 0) { throw 'Staging failed' }
    & git diff --cached --check
    if ($LASTEXITCODE -ne 0) { throw 'Staged diff validation failed' }
    & git config user.name 'SkuySocial Development Autofix'
    & git config user.email 'skuysocial-autofix@users.noreply.github.com'
    & git commit -m "fix(ci): guarded automated repair for PR #$env:FIX_PR"
    if ($LASTEXITCODE -ne 0) { throw 'Commit failed' }
    # Uses Windows runner's pre-authorized scoped Git credentials.
    # Do not store PAT/SSH keys in the repo or Actions logs.
    & git push "https://github.com/$repo.git" "HEAD:refs/heads/$env:FIX_BRANCH"
    if ($LASTEXITCODE -ne 0) { throw 'Push blocked or branch changed; no force-push attempted' }
    Write-Host 'Guarded Development repair pushed. Normal CI will validate it.'
} finally {
    if (Test-Path $log) { Remove-Item -LiteralPath $log -Force -ErrorAction SilentlyContinue }
}
