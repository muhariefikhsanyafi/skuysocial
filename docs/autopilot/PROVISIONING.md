# One-time Development administrator provisioning

The reviewed entry point is `ops/autopilot/provision_admin.py`. It uses only the Python standard library and never imports repository code while running as root. Its default mode verifies the package and host metadata without changing the system. `--apply` is reserved for a separately approved administrator execution.

The unprivileged builder prepares a local data package under ignored `.autopilot/provisioning/reviewed/`. It takes a shallow Git baseline, overlays the intended reviewed code, copies the already installed locked dependencies, and records every regular file's SHA-256, size and executable flag. Only internal pinned node dependency links are allowed. No root-time download, Composer/npm install, Artisan command, live environment, upload, persistent application database, backup, Desktop authentication directory or SSH key is included. Existing dependency advisories remain release blockers; copying dependencies does not certify them secure.

The administrator command must pin both the provisioning script and manifest hashes. The supplied Python isolated-mode loader reads and verifies the script bytes before compiling those same bytes; it does not execute a mutable unchecked file after a separate hash command. The manifest is verified before any privileged change, and payload files are checked again during installation. Symlinks in input ancestry are refused. The package is bound to the Development machine's hashed machine ID and Ubuntu 24.04 systemd environment. Do not reuse it on another server.

## Exact privileged changes

| Change | Boundary |
| --- | --- |
| Create `skuysocial-autopilot` system group and locked non-login account | Dedicated nonzero system UID/GID; `/usr/sbin/nologin`; no admin/sudo groups or sudoers/polkit changes |
| Install `/opt/skuysocial-autopilot/source` | Administrator-owned snapshot; directories 0750, files 0640 or 0750, service group read/execute only |
| Install `/etc/skuysocial-autopilot/runner.toml` and provisioning receipt | Administrator-owned 0750 directory, 0640 files; external GitHub sync remains disabled |
| Create `/var/lib/skuysocial-autopilot/{codex,queue,validation}` | Dedicated service owner, 0700; existing authentication and runtime data are never copied, reset or overwritten |
| Create `/var/lib/skuysocial-autopilot-status` | Service-owned, owner-group read-only status access (02750); sanitized status file 0640; no raw credentials/logs |
| Install two exact reviewed unit files under `/etc/systemd/system/` | Main worker and finite non-root validation service; digests are also pinned in the provisioning script |
| Reload systemd, enable guarded worker, schedule validation once | Worker has a passed-validation condition and mandatory effective-isolation guard; validation runs as non-root and starts worker only after success |

The only executed privileged programs are fixed-path `groupadd`, `useradd`, `systemd-analyze verify`, and fixed-unit `systemctl daemon-reload`, `enable`, and `start --no-block`. No shell command is built from task text. No live checkout ownership is changed and the old local queue/artifacts are not migrated or deleted. A completed same-hash rerun verifies installed files and returns without restarts/replay. Different packages, conflicting identities/files or interrupted partial installation require inspection; no destructive automatic repair is attempted.

## Validation after administrator approval

The validation unit preserves all the worker's identity, namespace, filesystem, capability and resource restrictions. It checks effective isolation and native bubblewrap first. It then performs one deliberate non-root validation-process crash around a synthetic queue record; systemd restarts that validation unit once, and queue recovery must retain the record and block replay. Other validation failures use exit statuses that prevent automatic retries. It runs the runner/security tests, publication scanner, a real finite Codex queue fixture with acceptance and read-only review, and reopens the queue after the submission process has exited. It writes a package-bound success marker only when these pass.

A dedicated approved Codex login must already be present or be provisioned through a separate secure non-root authentication flow. The package contains no authentication and does not borrow Desktop credentials. Missing login reports `blocked-codex-login` and leaves the worker inactive. An inherited AppArmor restriction or namespace EPERM reports blocked isolation and stops; no kernel policy/profile/capability change or privilege retry follows. The previous user-unit failure is not assumed fixed merely by installing a system unit.

Status can be read locally from `/var/lib/skuysocial-autopilot-status/status.json` without access to the service's private authentication directory. Raw validation evidence stays private. Physical Desktop disconnect and real reboot are separately recorded rather than inferred from tests. The worker remains on a fresh private Development queue; importing the legacy queue requires a coherent offline backup and path review, not automatic replay.

Production deployment remains disabled in the CLI, policy and workflow. This package grants no permission to commit, push, merge, deploy or access Production. No privileged provisioning or runtime-isolation success may be claimed from the read-only check.

## Fail-closed recovery groundwork (unprivileged tests only)

`ops/autopilot/rollback_journal.py` now implements a durable, manifest-bound recovery journal for **newly created** files, symlinks and empty directories in a deliberately scoped Development installation tree. It stores inode, ownership, mode and (for regular files) SHA-256. Explicit rollback preflights every recorded object before removing any of them; it uses no-follow descriptor traversal, refuses drift, refuses shared hardlinks, never traverses target symlinks, and never removes unrecorded files or nonempty directories. Journal state permits a subsequent explicit recovery attempt after interruption, but never automatically retries installation.

This is **not yet authorized privileged provisioning**. The critical write-ahead crash window between a future install's creation syscall and the durable journal entry, account/group rollback, systemd enable/start restoration, and real Ubuntu/AppArmor isolation must still be designed, threat-reviewed, and tested end-to-end. `provision_admin.py --apply` **still raises `BLOCKED` before any host mutation**; the obsolete unreachable root install code was removed rather than accidentally exposed. Do not grant `sudo`, activate systemd services, or point this utility at Production. The regression suite exercises recovery exclusively in disposable unprivileged temporary directories.
