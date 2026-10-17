# SkuySocial — Codex Project Guidelines

## Project Baseline

SkuySocial is a social product built with Laravel. Preserve its working product behavior and current architecture while moving through the roadmap incrementally.

Current shared GitHub integration branches (verify current refs before acting):

- Repository: github.com/muhariefikhsanyafi/skuysocial
- Stacked draft PRs: #2 (CI/Flow), #4 (Tailwind v4), #5 (framework refresh), and #6 (integrated release candidate targeting main).
- `main` and the Production server may differ; verify live Git HEAD and server backup/rollback before any authorized deployment.
- The project brief reports SkuySocial Flow Stage 1 (Feed) and Stage 2 (Profile) as complete, deployed to Production, and passed through Production QA. Production was not accessed or independently checked for this update.
- Treat Feed and Profile as completed work. Do not repeat their implementation unless a regression is verified with reproducible evidence and the task is scoped to address it.

Recheck branch, HEAD, and working tree before each new task. The values above are an audit point, not a permanent branch requirement.

## Stack and Rendering Architecture

The manifests and source tree currently show:

- Laravel 13.x with PHP 8.3 or later; composer.json requires Laravel ^13.17 and PHP ^8.3.
- Blade and Alpine.js are the primary rendering and interaction pattern for product surfaces. The audited tree contains 75 Blade view files and Alpine directives in those views.
- React and TypeScript are used selectively through Inertia. The audited tree has one Inertia page, resources/js/pages/Post/Show.tsx, with React components supporting it. Do not migrate another surface to React by default; follow its existing integration unless the task calls for a deliberate change.
- Filament ~5.0 is installed for the admin application. Keep admin UI work separate from public/product design unless explicitly requested.
- The frontend manifest declares React ^19.3, @inertiajs/react ^3.7.1, Inertia Laravel ^3.3, TypeScript ^7.0.2, Alpine.js ^3.4.2, and Vite ^8.0.
- Tailwind CSS v4.3.3 uses @tailwindcss/vite with CSS-first design tokens and Chromium regression coverage. Preserve the v4 CSS entry point and the 348 browser QA checks.

These counts and package declarations describe the audited baseline. Recheck current source and manifests before relying on them in later work.

## Current Product Design Direction

The current product direction supplied for this guidance update is **SkuySocial Flow — Minimalist Glassmorphism**:

- Light Mode uses clean white, #FFFFFF. Dark Mode uses neutral dark, #0D0E13.
- Use violet selectively as an accent. Keep the interface content-first, readable, and responsive.
- **Verification badge is immutable:** the canonical `public/images/verified-badge.svg` uses Facebook blue `#1877F2` with a white checkmark in **both** Light and Dark modes, on Profile, Feed, Post Detail, Messages, and every other surface. Never recolor it violet/teal or substitute a separately themed inline SVG. The violet accent is reserved for unrelated interface elements.
- Use subtle glassmorphism for navigation, menus, and floating elements. Keep text and controls legible in both themes and avoid making every surface translucent.
- Do not add aurora effects, decorative gradients, or excessive glow.
- Mobile bottom navigation has exactly five items, in this order: Beranda, Cari, Buat, Aktivitas, Pesan. Preserve real destinations and behavior, accessible labels, selected state, safe-area spacing, and sufficient content clearance.

Read DESIGN.md before UI work. The current DESIGN.md still names SkuySocial Expressive Clean and specifies a warm-neutral/teal/mint/saffron palette with limited shell glow and gradient treatments. Those visual directions conflict with the current product direction above. This task is scoped to AGENTS.md, so DESIGN.md is unchanged. Until it is reconciled in separately scoped work, this section records the latest explicit product direction for palette, visual effects, and the five-item mobile navigation; use DESIGN.md for its non-conflicting guidance on behavior, responsive composition, accessibility, localization, and interface states. Do not blend the conflicting palettes or effects. Surface any additional unresolved design conflict before implementing UI.

Anti-Slop is a quality filter, not an alternate design system. It must not expand an approved scope or override intentional SkuySocial patterns. For backend-only work, do not use Anti-Slop findings to trigger unrelated UI, Blade, CSS, copy, or design changes; report them only. If a generic suggestion materially conflicts with an intentional project decision, preserve the project direction and report the conflict.

Keep Filament Admin's separate visual language unless an admin design task explicitly changes that scope. Preserve existing SkuySocial patterns and do not redesign established surfaces without a scoped request.

## Product and Social Feature Invariants

Preserve existing working features, including social interactions, Points, appreciation, authentication, privacy, and authorization. Do not create fake controls, active-looking placeholders, invented counts, or behaviors that do not work.

- Treat authorization and data ownership as first-class requirements. Use policies/gates or explicit checks; validate ownership and relationships on the server even when the UI hides an action.
- Use Laravel conventions, PSR-12, typed controller signatures, route model binding, and Form Requests for non-trivial validation where practical.
- A user can like a post or comment at most once. A user cannot follow themselves or create duplicate follows.
- A reply parent must belong to the same post unless cross-post references are an explicit product decision.
- Prevent duplicate reposts by the same user for the same original post, preserve the original-post relationship, and enforce important invariants in the database where appropriate.
- Do not notify users about their own actions unless explicitly required. Avoid duplicate notifications where practical and keep payloads stable.
- Build notification and other URLs with the route key actually configured for the model; verify route binding instead of assuming a slug or hard-coding an identifier.
- Validate upload type and size, and avoid exposing raw storage paths.
- Do not add public routes that run migrations, destructive maintenance, or arbitrary Artisan commands.

## Database and Scale

- Review existing migrations, models, and query patterns before changing the schema or adding an index.
- Prefer additive migrations for existing environments. Check driver compatibility and deployed data before changing column type or nullability.
- Use transactions when one action changes multiple records and partial completion would be incorrect. Use foreign keys and cascading deletes only when domain behavior supports them.
- Use unique constraints for one-user-per-resource interactions and other invariants that must always hold.
- Bound feed, profile, topic, hashtag, search, notification, follower, and comment queries. Use pagination or cursor pagination to fit the UI.
- Avoid N+1 queries, unbounded global get() calls, loading every like/comment for counts or current-user state, and scanning all post text into PHP for trending results.
- Select only needed columns and add indexes from measured query patterns.
- Never run tests or migrations against Production. Do not run migrate:fresh against a persistent, project, or Production database. Run a migration only when required by the task and only after confirming it targets an isolated local database.
- Never delete, move, overwrite, clean, or otherwise alter persistent Production data. In particular, preserve storage/app.pre-final/.

## Privacy and Security

- Do not read or display .env, API keys, passwords, tokens, or other secrets unless a concrete task requires it. Never include them in command output, diffs, logs, or reports.
- skuysocial-app.zip is intentionally excluded from project work: do not open, read, copy, modify, stage, or delete it.
- Do not modify Production infrastructure unless explicitly requested and scoped.
- Do not access Production for this project workflow unless the user explicitly requests and scopes it. Do not use Production credentials for local development or tests.
- Do not add a route that exposes maintenance operations or arbitrary Artisan commands.
- Do not commit, push, merge, or deploy without the user's explicit approval.
- Do not use force push, destructive resets, broad staging, or production data repair as a shortcut.
- Keep changes limited to the requested scope. Report unrelated findings rather than fixing them opportunistically.

## Testing and QA

For meaningful application changes, choose checks based on the changed layer and report what actually ran:

- Run focused PHP Feature/Unit tests first, then the full PHP suite when dependencies are available.
- Run relevant frontend tests with npm run test:client, typecheck with npm run typecheck, and compile with npm run build as appropriate to the change.
- For UI work, perform browser QA on the affected flows, themes, responsive widths, keyboard behavior, and id/en localization states. Never claim QA that was not performed.
- Run Pint on changed PHP files where appropriate.
- Use an isolated local test database. The audited phpunit.xml forces APP_ENV=testing, DB_CONNECTION=sqlite, and DB_DATABASE=:memory:; preserve those safeguards and verify test configuration before running tests. Do not point tests at a persistent or Production database.
- Do not run tests, migrations, or build steps for a documentation-only change unless the task specifically requires them.

The composer.json setup script copies/creates .env, generates an application key, and runs php artisan migrate --force before installing frontend dependencies and building. Do not invoke the setup script casually in an existing checkout. Inspect its steps and confirm an isolated local environment before using any setup command; never run it against Production or persistent project data.

## Documentation Status

- DESIGN.md is the product design reference, subject to the current explicit direction and conflict handling documented above.
- README.md is still the Laravel starter README and is not a project-specific setup guide.
- SKUYSOCIAL-AUDIT.md is titled as an initial technical audit and lists candidate issues from an earlier snapshot. Treat it as historical context, not a live backlog. Verify any listed condition in the current code and tests before treating it as open or changing it.
- Do not repeat an old issue list as current status without verification.

## Windows and Laragon Workflow

- Use PowerShell from the repository root. Verify the root, active branch, and git status before edits; check command resolution and versions with Get-Command and each tool's version command rather than assuming PATH contents.
- The audited host had Git on PATH from the Codex runtime. Laragon Git was also found at C:\laragon\bin\git\cmd\git.exe and C:\laragon\bin\git\bin\git.exe. If Git is absent from PATH, verify the installed Laragon path with Test-Path and invoke that executable by its full path. Laragon may be installed on a different drive on another machine.
- On the audited host, PHP resolved to C:\php\php.exe, Node resolved from C:\laragon\bin\nodejs\node-v22, and Composer was not available as a PowerShell command. Resolve and verify the actual PHP/Composer pair before Composer or Artisan work; do not assume all tools come from the same Laragon installation. These are host observations, not portable path requirements.
- Avoid relying on Bash-only shell syntax in Windows tasks. Quote Windows paths and use PowerShell-native commands for file operations.
- Check a Composer script's steps before running it. In particular, the project setup script includes a forced migration and writes local environment/key files as described above.
- Keep local development and test databases isolated from Production. Do not read secret configuration just to discover a tool path or run routine tests.

## Change Discipline

Before application edits, inspect the relevant current models, migrations, controllers, routes, views/components, and tests. Keep the diff limited to the approved task, preserve existing behavior, and include compatibility implications when a schema change is required. Work on the branch appropriate to the task and stop after the requested deliverable; wait for approval before any commit, push, merge, or deployment.

## Shared Theme and Navigation Regression Contracts

- Include `partials.theme-bootstrap` in each product document head before any stylesheet. Alpine must adopt the root's resolved `dark` class and persist changes through `window.skuyTheme.persist`; do not resolve the initial preference a second time. Preserve saved dark/light preferences, Feed's dark-first default, and the system preference default elsewhere. Use the existing Vite nonce when a CSP nonce is configured; do not weaken CSP to permit the bootstrap.
- Keep navigation markup in `layouts.navigation` and Mobile badge geometry in `resources/css/app.css`. Page styles must not override badge positioning, sizing, colors, or transform. The approved Feed badge is the reference. Preserve five Mobile destinations and real unread counts.
- After changing a shared layout or adding a page stylesheet, build assets and run `npm run test:browser:theme-navigation -- --php=<local PHP 8.3+ executable> --browser=<installed Chromium/Edge executable>`. The runner creates its own temporary SQLite database and uses real routes. It checks first paint with the Alpine module held, theme navigation/reloads, icon-relative badge geometry, both themes, and Desktop sticky layout. Do not replace these rendering checks with CSS-string assertions. Never point the runner at Production or a persistent database.
