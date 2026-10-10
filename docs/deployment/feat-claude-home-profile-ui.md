# Manual deployment and recovery guide

## Scope

This is a manual release checklist for the profile/media query, poll, pin, localization, and accessibility changes on `feat/claude-home-profile-ui`. No Production connection, migration, data change, deployment, or visual QA was performed for this work.

The migration is `database/migrations/2026_10_08_000001_add_polls_and_pins_to_posts.php`. Confirm the exact release artifact contains it. If Production already records this migration as applied, do not edit or replay it; stop and reconcile the migration history with the release owner.

## 1. Preflight: identify the actual Production database

Run these from the Production application release using its configured environment. Do not paste credentials, a DSN, or full environment output into tickets or chat.

```sh
php artisan tinker --execute='dump([
    "driver" => DB::connection()->getDriverName(),
    "server_version" => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
]);'
php artisan migrate:status --database=<verified-connection-name>
```

Record the driver and server version privately. Verify the database/schema compatibility level with the DBA as well; the PDO server version alone is not a complete compatibility check. For SQL Server, specifically confirm the database supports the `OPENJSON` expression used by the media filter. For MySQL/MariaDB, confirm the `posts` table uses a storage engine that supports the migration's foreign keys. Do not assume Production is SQLite or that DDL is online.

Review the migration status before applying anything. If unrelated migrations are pending, stop and get the release owner’s decision rather than running them accidentally. Apply only the intended migration as shown below.

Test the exact migration and the released application against a non-Production database using the same driver and server major version. Use a representative `posts` table size to assess DDL duration and locking; SQLite results do not establish Production locking behavior.

## 2. Backup and recovery preparation

Before schema changes:

1. Take a consistent, restorable database snapshot or native backup using the Production DBA's approved procedure.
2. Ensure it covers the `migrations`, `posts`, `users`, `user_pinned_posts`, `post_polls`, `post_poll_options`, and `post_poll_votes` tables (a full database backup is preferred).
3. Restore the backup into an isolated non-Production environment and verify the restore, relevant row counts, and foreign-key integrity. Keep the backup outside the repository and do not use it as a test fixture.
4. Record the restore owner and recovery window. Agree how writes made after the snapshot will be handled before any restore is attempted.

The migration is additive going forward, but its `down()` is destructive after feature use: it drops poll votes, options, polls, the pin registry, and `posts.pinned_at`. A migration rollback is **not** a data-recovery plan.

## 3. Apply and verify the schema

Schedule the change with the DBA. Adding an indexed column to `posts` can take locks or rebuild work depending on the actual engine, version, table size, and server settings. No online/lock-free guarantee has been established. Use a maintenance window or pause writes to the affected surfaces if the Production DBA determines it is needed.

After confirming the migration is pending and the backup is restorable, run from the intended release:

```sh
php artisan migrate \
  --path=database/migrations/2026_10_08_000001_add_polls_and_pins_to_posts.php \
  --database=<verified-connection-name> \
  --force
php artisan migrate:status --database=<verified-connection-name>
```

If the command fails or the result is partial, stop. Do not blindly rerun it, drop objects, or invoke `migrate:rollback`; have the DBA inspect the migration record and actual schema first.

Verify, read-only, that:

- `posts.pinned_at` exists and has its intended index.
- `user_pinned_posts` has a primary/unique key on `user_id` and a unique key on `post_id`.
- `post_poll_votes` has indexes on `option_id` and `user_id` (for vote totals and account-deletion cleanup) and retains unique `(poll_id, user_id)`.
- `post_poll_votes` uses the composite `(poll_id, option_id)` foreign key to the matching option; poll deletion cascades through options. Its `user_id` foreign key uses the driver's default no-action behavior, and account deletion explicitly removes that user's votes inside the same transaction. This avoids redundant SQL Server cascade paths.
- Poll, option, vote, user, post, and pin foreign keys are present with the expected delete behavior.
- The migration is recorded as applied exactly once.

Then deploy the matching application release and its prebuilt assets using the site's normal deployment process. Do not deploy code that uses polls/pins before the schema migration is verified.

## 4. Smoke checks after deployment

Have the release owner perform the final UI and Production QA. At minimum verify:

- Profile media tab loads on the actual Production driver.
- Profile supplemental links are not duplicated in keyboard/screen-reader navigation; keyboard focus reveals the intended link.
- Poll totals are visible to viewers who cannot vote and after a vote; a user still cannot cast more than one vote per poll.
- Pinning a second owned post replaces the previous pin; unpinning clears the pin; users cannot pin another user's post.
- English and Indonesian labels, follower singular/plural, and relative timestamps remain correct.
- Application, database, and queue logs show no migration, JSON, foreign-key, or query errors.

## 5. Recovery / rollback decision tree

- **Application-only regression after a successful migration:** roll back the application artifact using the normal release mechanism and leave the additive schema in place. Do not run the migration's `down()` merely to restore old code.
- **Migration failure before completion:** stop writes to the affected release path if necessary, preserve logs, inspect migration status and actual objects with the DBA, and repair forward only after a fresh backup and engine-specific review. Do not assume the migration is atomic across DDL statements.
- **Data loss or accidental destructive rollback:** stop writes, preserve the current database for investigation, and obtain explicit approval for recovery. Restore the verified pre-deploy backup to a controlled target, validate it, then have the DBA choose a recovery plan that accounts for writes made since that backup. Never restore a local/test database over Production.
- **Explicit schema rollback before feature use:** only consider it after confirming the affected poll/pin tables contain no needed data, taking another verified backup, obtaining release-owner approval, and scheduling the DBA-supervised maintenance window. Once polls, votes, or pins have been used, `down()` deletes that feature data.

## Known release risks

- Production driver, server version, SQL Server compatibility level, `posts` storage engine, table size, and DDL lock duration are unknown until the Production owner performs the preflight.
- Automated media-query tests compile JSON expressions for the configured Laravel drivers, and the migration is compiled with SQL Server's schema grammar plus exercised against the repository's isolated SQLite test database. Neither proves behavior or locking on the actual Production engine.
- The one-pin invariant is backed by the `user_pinned_posts.user_id` primary key and an atomic application transaction/upsert, but concurrency behavior should still be exercised on the real Production driver in the release owner's QA environment.
- Final visual/accessibility QA and Production deployment remain with the release owner.
