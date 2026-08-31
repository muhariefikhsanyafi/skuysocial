# SkuySocial Initial Technical Audit

## Baseline
- Laravel 13.x / PHP 8.3+
- Blade + Alpine.js + Tailwind CSS + Vite
- Core social features already implemented: posts, images, likes, comments/replies, hashtags, topics, reposts, follows, profiles, search, notifications.

## Priority P0 — security/correctness
1. Remove or protect public maintenance routes that run migrations or mutate all usernames.
2. Fix notification payloads to use the post's actual route key (`uid`) instead of `slug`.
3. Validate reply parent ownership: `parent_id` must belong to the same post.
4. Add unique DB constraint to `comment_likes(comment_id, user_id)`.
5. Review repost duplication/race conditions and add a DB constraint suitable for the chosen repost model.

## Priority P1 — scale/performance
1. Convert global feed to pagination/cursor pagination.
2. Convert profile/topic/hashtag post lists to bounded pagination.
3. Avoid loading every like/comment just to render counts or current-user state.
4. Replace PHP-wide hashtag trending scan with a database-backed aggregation using `hashtags`/pivot data.
5. Add indexes based on actual feed/search/notification queries.

## Priority P2 — maintainability
1. Extract repeated authorization into Policies or reusable authorization methods.
2. Clean up duplicated profile-update fill/validation logic.
3. Standardize controller imports/types and naming.
4. Add focused Feature tests for social interactions and authorization.
5. Review notification behavior for duplicate/self notifications and pagination/read semantics.

## Environment note
The archive does not include `vendor/`, so Laravel artisan/test execution is not currently possible from the extracted archive until Composer dependencies are installed.
