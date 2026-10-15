# SkuySocial Development Autopilot

Production auto-deploy: **DISABLED**. No Production host was contacted.

The runner uses Python 3.12 standard library and installed Codex CLI 0.162.1. It runs one task at a time in a private local clone of the exact submitted HEAD. Installed vendor/node_modules are copied into that clone. It never copies the application `.env`, persistent SQLite database, uploads, or `storage/app.pre-final/`. The original checkout remains the review surface for this implementation.

## Queue and evidence

```bash
python3 ops/autopilot/runner.py submit --description 'A scoped task' --acceptance 'Observable acceptance criteria'
python3 ops/autopilot/runner.py list
python3 ops/autopilot/runner.py show TASK_ID
python3 ops/autopilot/runner.py doctor
```

Each task stores description, acceptance, priority, baseline, generated branch, attempts, deadline, progress, QA results, and report location. Events and tasks are SQLite WAL records with FULL synchronization. `.autopilot/` is ignored and owner-only. Artifacts include bounded process logs, structured agent results, QA results, diagnosis, review, a binary patch including new files, and final report. No automated staging, commits, pushes, or merges occur.

Status meanings:

| Status | Meaning |
| --- | --- |
| queued | Accepted and waiting for the single worker |
| running | Preparing workspace, implementing/repairing, or reviewing |
| testing | Trusted quality commands are running |
| blocked | Interrupted, missing access, invalid workspace/output, or exhausted infrastructure budget; inspect before retry |
| failed | All allowed implementation attempts failed acceptance/gates/review |
| completed | Configured gates and read-only review accepted the result; owner review still required |

A failed worker is recovered on next startup. Active tasks become blocked with an explicit interruption report. They are **never automatically replayed**. After inspecting retained workspace, logs, and external side effects, an operator may run `retry TASK_ID`; this does not reset lifetime attempt, invocation, or usage budgets. Partial workspace creation and unexpected commits fail closed.

A task has a 30-minute default deadline (maximum one hour), at most three implementation attempts, at most six Codex calls (including review), ten-minute maximum per agent call, and a 250,000-token aggregate checkpoint ceiling. Codex usage events enforce the token checkpoint between calls; the CLI does not expose a verified hard per-call billing/token cap. Use an account/provider spending limit for monetary control. Timeouts kill process groups, including descendants. Logs have an 8 MiB capture limit and child file output a 32 MiB limit. The systemd cgroup applies 4 GiB memory, two CPUs, 128 tasks, and no core dumps. Do not treat direct CLI worker runs as equivalent cgroup memory isolation.

No API keys, SSH agent, or inherited database settings are passed to generated processes. Codex uses its existing private account login. Inference logs retain event types and usage, omitting command output and prompt content. Other logs have basic redaction and remain private: automatic redaction cannot prove arbitrary task output contains no secrets. The runner and Codex use the same Linux account; this is an owner-operated Development tool, not isolation for hostile tenants. Deployment credentials must never be provisioned to this identity.

## Service

The Development system-unit candidate is `ops/autopilot/skuysocial-autopilot-system.service`; it is not activated by placing files in the repository. It requires a controlled administrator installation and actual isolation verification. The legacy user-unit source is diagnostic history and must not be enabled as a replacement for a blocked system-unit activation.

The candidate uses a dedicated non-login service account, an administrator-owned secret-scanned source snapshot under `/opt/skuysocial-autopilot/source`, a trusted config under `/etc/skuysocial-autopilot/`, and private 0700 state under `/var/lib/skuysocial-autopilot`. Use `system.config.example.toml` for that config. Do not bind the live owner checkout or mount the owner's `.codex`/`.ssh` directories. Provision a dedicated approved Codex login through a secure local authorization flow; no passwords or tokens belong in chat, source, or issue comments.

Mandatory `ExecStartPre` checks run under Python isolated mode. They require the dedicated identity, zero capabilities, no-new-privileges, administrator-owned source/config, actual read-only source mounts, private state, denied sensitive roots, and a working native bubblewrap sandbox. A positive systemd static hardening score alone is insufficient. Keep kernel/AppArmor restrictions enabled. Do not use unrestricted Codex execution, remove isolation controls, or run an alternate manual worker to evade an activation blocker.

The service retains cgroup limits and can be enabled for reboot only after the guard and a finite real synthetic queue task pass. Verify timeout, retry, private-path denial, service restart, safe recovery, and Desktop disconnect independence on the installed system unit. Never claim those tests from a standalone CLI probe or mocked-agent test. Before relocating an existing queue, use a coherent SQLite backup, preserve all artifacts and lifetime budgets, validate stored paths, and keep interrupted tasks blocked. Do not recursively change ownership on the owner's live checkout.

The heartbeat is written after each idle poll or completed task; an old heartbeat during a long task is expected. Queue status is authoritative for a running task. Off-host downtime alerts require a separately provisioned monitor. Private state and reports need a backup policy; copying only an active SQLite main file is unsafe.

## Quality workflow

`quality.py` refuses workspaces containing `.env`, cached app config, or a Vite hot marker. It verifies phpunit.xml forces testing and SQLite `:memory:`. It uses unique cache/view directories, synthetic keys, disabled external push/CAPTCHA, and array mail/cache/session drivers. It runs focused PHP Unit tests before the full suite, Vitest, typecheck, build, smoke tests, and build-manifest verification. Full-suite results use JUnit to avoid enormous rendered-HTML assertion logs. Browser rendering requires an installed Chromium/Chrome executable and is a required separate CI job. Quality does not pass if only build succeeds.

```bash
python3 -m unittest discover -s ops/autopilot/tests -v
python3 ops/autopilot/quality.py --all --repo /path/to/isolated/clone --output /private/evidence/path
python3 ops/autopilot/quality.py --only browser --browser /path/to/chrome --repo /path/to/isolated/clone --output /private/evidence/path
```

The default task gate runs the trusted source script against the task clone. Configuration is operator-controlled TOML, not executable input from task descriptions. Configured commands must remain finite and side-effect safe. Do not use composer setup or persistent/Production databases. Clone dependencies reflect the local lockfile installation; refresh them deliberately when locks change. Do not reuse stale dependencies for changed lockfiles.

## Mobile intake and reporting

GitHub Issues is the selected mobile interface. The issue form requires Task and Acceptance criteria. The bridge accepts only issues authored by the configured owner with the manually applied `autopilot-approved` label. An immutable source key prevents repeat intake; editing an accepted issue does not alter an active task. Create a new scoped issue for revised requirements.

The bridge is **disabled by default**. A restricted identity needs repository metadata and Issues read/write, with no contents write, Actions administration, or Production access. Provision its token as a private 0600 file through the host's secure credential mechanism; never paste tokens into chat or commit them. Populate the trusted config's GitHub owner, repository, and bot login. The service must explicitly use that reviewed config via `--config`. No token is present in this implementation. HTTP calls have finite timeouts and pages are bounded to 100 oldest open issues and 100 comments. The worker polls when idle; reports for a long-running task are synchronized on the next cycle. GitHub outage does not remove local queue records.

One bot-owned issue comment carries only the queue status and disabled deployment boundary. Branch/commit references, attempt counts, QA results, task text, internal progress, report paths, logs, and patches stay local. Detailed logs stay private. Patches and final reports remain local until a separate reviewed publication path is authorized. This bridge does not grant GitHub contents writes or create PRs. A connected GitHub plugin can publish a minimal mobile status checkpoint independently of the host bridge; issue #1 was created and verified through that connector. This does not give the host worker credentials. Unattended mobile delivery and GitHub Actions cannot be verified until the reviewed changes are authorized for publication and the restricted bridge identity is provisioned.

## CI and release boundary

`quality.yml` runs on PRs, approved branch patterns, and manual dispatch using GitHub-hosted workers. It uses lockfiles, pinned action SHAs, read-only repository permissions, no saved checkout credentials, concurrency cancellation, finite job timeouts, dependency vulnerability audits, secret filename hygiene, actual browser regression, and retained failure artifacts. The release gate requires application, security, and browser success. No self-hosted Development runner processes untrusted PRs.

`release.py pack` packages only tracked source plus built assets, excludes sensitive/persistent paths, rejects links, validates Vite entries, and records commit and checksums. It produces a **source-and-assets bundle**, not a Production-ready release with vendor dependencies. Target PHP/extensions, a production-only dependency build, migration compatibility, approved baseline/branch, persistent paths, and server integration must be established by the authorized target audit before activation.

`release.py rehearse` builds its own synthetic temporary filesystem and SQLite database. It verifies a consistent database backup, integrity and restored rows, atomic symlink activation, repeated activation, health-failure code rollback, and unchanged synthetic database/uploads. It accepts no server or target path. It does not demonstrate a Production backup, database compatibility, nginx/PHP-FPM/queue reload, real HTTP health, or Production rollback.

```bash
python3 ops/deploy/release.py rehearse
python3 ops/deploy/release.py rehearse --fail-health
```

`deploy-production.yml` contains no SSH/deploy credentials or network commands. Manual dispatch explicitly reports disabled and fails; the Production job has a literal false condition. `release.py deploy` unconditionally refuses execution. Changing a repository variable or dispatching a workflow cannot activate it. `policy.json` records missing activation prerequisites rather than pretending they are satisfied.

First activation requires one separately scoped owner authorization for Production read-only audit, verification of the actual deployed commit and full persistent-path mapping, restored backup drill, restricted deploy identity distinct from this worker, target-specific atomic deploy and HTTP/asset verification, and a reviewed release policy. No automatic migrations are enabled; risky schema changes require a separate compatibility and data-protection plan. Code rollback must never roll back a database automatically. Do not deploy GitHub main merely because it is the default branch.

## Namespace compatibility and private evidence

An outer unprivileged user namespace can inherit AppArmor capability restrictions before bubblewrap starts. A user-unit declaration of filesystem hardening does not prove its mount namespace was established. Diagnose process labels, UID/GID maps, actual mount namespaces and failing syscalls before choosing a system-manager alternative. The candidate deliberately leaves native namespace creation available and does not disable AppArmor or user-namespace policy.

Current host readiness, exact diagnostic results, service state and access decisions are kept in local ignored reports, not published with generic operating guidance. The system candidate remains unverified until it is installed and its effective barriers and native sandbox are exercised under the dedicated account.
