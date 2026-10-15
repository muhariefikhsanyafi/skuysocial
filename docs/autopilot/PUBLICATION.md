# Development code publication boundary

Code publication is prepared on the separate local branch `codex/autopilot-development-runner`. Creating this branch does not authorize committing or pushing. The reviewed Development baseline remains the branch parent; Production is not a publication target.

Before a separately approved publication, review the complete tracked diff and intended untracked code files, then run `ops/autopilot/security_check.py`. It scans both sets, rejects sensitive filenames without reading their contents, checks text for known key/token formats, rejects symlinks, and never prints detected values. It is a heuristic safety check, not proof that all possible secrets are absent. Review new binaries separately. Release packaging runs the same safety check before creating an artifact.

Private `.env` variants, the excluded application archive, runtime databases, keys, logs, queue state and private readiness/security reports must never be staged. The private operational reports under `docs/autopilot/READINESS.md`, `QA_BACKLOG.md` and `UNBLOCK.md` are deliberately ignored and blocked by the publication scanner even if force-added. Do not use broad staging. Generic operations guidance, runner source, tests and disabled CI/deployment definitions are the intended review surface.

Use a repository-scoped publication identity with Contents write and the Workflows write permission required to publish `.github/workflows/`. It needs no repository administration, deployment environment secrets, merge bypass, or Production identity. The connected owner plugin is capable of repository writes but is not a least-privilege host credential; its tokens must not be extracted or installed into the worker. A read-only SSH deploy key remains read-only.

The persistent worker must receive no source-publishing credentials. An optional host Issues identity needs only metadata and Issues read/write for this repository. That integration stays disabled until its dedicated secure credential and owner-only intake are verified. The CI jobs use `contents: read`, GitHub-hosted execution, no persisted checkout credentials, and finite concurrency/timeouts. No self-hosted Development runner processes untrusted PRs.

Do not create a commit, push a ref, run a new remote workflow, or enable auto-merge before explicit owner authorization. Production deployment remains hard-disabled independently of successful publication or CI.
