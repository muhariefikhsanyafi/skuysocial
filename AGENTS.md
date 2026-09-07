# SkuySocial — Codex Project Guidelines

## Project
SkuySocial is a Laravel-based social media application inspired by Threads, with a Neo Brutalism visual direction.

## Stack
- Laravel 13.x
- PHP 8.3+
- Blade
- Alpine.js
- Tailwind CSS
- Vite
- SQLite for the included development database; production database may differ

## Product principles
1. Preserve SkuySocial's Neo Brutalism identity and existing UX patterns.
2. Prefer incremental changes over rewrites.
3. Existing working behavior is the baseline; do not remove or change it without a clear reason.
4. Design social features for growth: pagination, indexes, eager loading, efficient counts, and bounded queries.
5. Treat authorization and data ownership as first-class requirements.

## Coding rules
- Follow Laravel conventions and PSR-12 style.
- Use Form Requests for non-trivial validation where practical.
- Prefer route model binding and typed controller signatures.
- Prefer policies/gates or explicit authorization over ad-hoc ownership checks when a rule is reused.
- Avoid N+1 queries; use eager loading and constrained relationships intentionally.
- Do not load unbounded collections for feed, notifications, comments, followers, search, or trending data.
- Use database constraints for invariants that must always hold (for example unique user/post likes and unique follows).
- Use transactions when one user action changes multiple related records and partial completion would be incorrect.
- Do not trust client-supplied foreign keys such as comment parent IDs without checking their relationship to the current resource.
- Keep notification payloads stable and use the actual public route key (`uid` for posts) rather than assuming a nonexistent slug.
- Validate upload type and size; do not expose raw storage paths unnecessarily.
- Never commit or print `.env`, API keys, passwords, tokens, or other secrets.
- Never add a public web route that executes migrations, destructive maintenance, or arbitrary Artisan commands.

## Database rules
- Review existing migrations before creating new ones.
- Prefer additive migrations for existing environments.
- Add indexes based on actual query patterns.
- Foreign keys should generally use cascading deletes only where domain behavior clearly requires it.
- Use unique constraints for one-to-one-per-user interactions.
- When changing a column type/nullability, check compatibility with the existing database driver and deployed data.

## Social feature rules
- Likes: a user can like a post at most once.
- Comment likes: a user can like a comment at most once.
- Follows: a user cannot follow themselves or duplicate an existing follow.
- Comments/replies: a reply parent must belong to the same post unless the product explicitly supports cross-post references.
- Reposts: prevent duplicate reposts by the same user for the same original post; preserve the original post relationship.
- Notifications: do not notify users about their own actions unless the product explicitly requires it; avoid duplicate notifications where practical.

## Feed and scale
- Do not use `->get()` for an unbounded global feed.
- Prefer `paginate()`/`cursorPaginate()` according to the UI/query pattern.
- Select only columns needed when practical.
- Avoid loading all likes/comments merely to compute counts or current-user state.
- Revisit trending/search implementation before production scale; avoid scanning every post's full text into PHP.

## Testing expectations
For meaningful changes:
1. Add or update Feature/Unit tests for the behavior.
2. Run focused tests first.
3. Run the full test suite before considering the change complete when dependencies are available.
4. Run Pint on changed PHP files where appropriate.

## Current known issues to prioritize
- Feed/topic/profile/hashtag queries are currently mostly unbounded `get()` calls.
- Trending hashtags are computed by scanning post content in PHP.
- Comment `parent_id` validation does not yet guarantee that the parent belongs to the same post.
- `comment_likes` lacks a database-level unique `(comment_id, user_id)` constraint.
- Notification payloads reference `post->slug`, while posts currently use `uid` as the route key.
- Public maintenance routes exist for running migrations and repairing usernames; these must not remain public in production.
- Profile update logic contains duplicated filling/validation flow and should be simplified carefully.

## Change discipline
- Before editing, inspect the relevant model, migration, controller, route, view, and tests.
- Keep changes scoped to the requested feature unless a directly related correctness/security issue must be fixed.
- Explain migrations and backward-compatibility implications in the change summary.

# SkuySocial Project Instructions

## Design Authority

For any work involving:

- UI
- UX
- Blade presentation
- styling
- responsive behavior
- accessibility
- copywriting
- localization
- email presentation

Codex MUST:

1. Read DESIGN.md before making relevant changes.
2. Treat DESIGN.md as authoritative for SkuySocial design.
3. Treat Anti-Slop as a quality filter, not a replacement design system.
4. Preserve intentional existing SkuySocial patterns.
5. Never expand approved scope because Anti-Slop finds unrelated issues.
6. Report out-of-scope findings instead of modifying them.
7. Do not redesign established surfaces unless explicitly requested.
8. Preserve responsive and accessibility correctness.
9. Respect the active id/en localization architecture.
10. Stop and report if a generic Anti-Slop rule materially conflicts with an
    intentional SkuySocial design decision and cannot be reconciled safely.

## Backend Scope Guard

For backend-focused tasks including:

- migrations
- database
- models
- services
- middleware
- security
- infrastructure
- authentication logic

Anti-Slop MUST NOT trigger unrelated:

- UI cleanup
- Blade changes
- CSS changes
- copy edits
- design changes
- unrelated refactors

Out-of-scope findings are report-only.

## Git / Production Safety

Preserve the project's existing disciplined workflow.

Do not:

- force push
- use broad staging when selective staging is required
- touch unrelated files
- destructively reset production data
- use migrate:fresh against persistent/project/production data
- expose secrets
- modify production infrastructure unless explicitly requested

skuysocial-app.zip is intentionally excluded from project work and must remain
unopened, unread, untouched, unstaged, and not deleted.
