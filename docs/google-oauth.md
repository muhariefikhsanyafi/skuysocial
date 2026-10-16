# SkuySocial Google Login / Registration

This integration uses Google's Authorization Code flow with PKCE, a 10-minute
single-use session-bound state, HTTPS token/userinfo requests, and Google's
permanent subject identifier as the account key. No Google tokens are stored.
A verified email is required. Existing password accounts are not automatically
linked by email.

## Google Cloud Console
1. Set up an OAuth consent screen (choose test/production audience).
2. Create OAuth Client ID of type Web application.
3. Register authorized redirect URI:
   https://skuysocial.id/auth/google/callback
4. Store GOOGLE_OAUTH_CLIENT_ID, GOOGLE_OAUTH_CLIENT_SECRET and
   GOOGLE_OAUTH_ENABLED=true only in server secrets. Optional:
   GOOGLE_OAUTH_REDIRECT_URI for a different authorized callback.
5. Keep disabled until after migration, backup, callback check and account QA.

## Existing accounts
Sign in with your password, open Profile Settings and choose Connect Google.
A recently confirmed password is required and the Google email must match.
The same Google identity may not belong to multiple accounts. Subsequent Google
login uses the existing 2FA challenge and moderation restrictions.

Normal email/password authentication is preserved. No Filament admin change.
Do not commit or print credentials, Google codes, or access tokens.
Production activation requires approval of quality gates and rollback.

## First-time Google registration
On the first successful Google callback (verified email and new Google subject),
SkuySocial does **not** create a user or log them in automatically. The guest
is sent to `/auth/google/complete` to select their display name and permanent
username, using the existing SkuySocial Flow guest theme.

The verified Google email is read-only and comes exclusively from Google (never
from form inputs). Username rules mirror email/password registration: unique,
lowercase, letters/numbers/underscore/dot, and at most 255 characters. A Google
password is **not** required. Account creation is atomic and occurs only after
valid submission. Onboarding state is stored in the server-side session for
10 minutes; cancel, expiry, provider restart, and successful registration
invalidate it. Concurrent identity/email claims fail without linking accounts.

Accounts already linked to Google continue the existing login/2FA flow without
an onboarding prompt. Existing password accounts still require explicit linking
with password confirmation and a matching verified Google email. No schema
changes or Google tokens are stored by this onboarding change.
