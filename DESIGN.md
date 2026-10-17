# SkuySocial Design System

> **Status:** Active product design authority — SkuySocial Flow — Minimalist Glassmorphism  
> **Reconciled:** 2026-10-18, based on the project handoff and current `AGENTS.md`  
> **Scope:** Public and authenticated product UI; **excludes Filament Admin**  
> **Implementation note:** This document specifies the approved direction. It does **not** assert that every live page or CSS token is already compliant. Verify the actual source, visual output, and GitHub state before implementation.

## 1. Authority, Scope, and Change Discipline

**SkuySocial Flow — Minimalist Glassmorphism** is the sole approved product design direction. The latest approved **Feed** and **Profile** (including the subsequent Profile revisions) are the primary visual references. Their accepted patterns take precedence over generic templates or speculative redesigns.

This document replaces the conflicting **SkuySocial Expressive Clean** and **Premium Visual Revolution** palette/effects guidance previously documented here. Do **not** mix warm off-white, teal, mint, saffron, shell auroras, ambient gradient fields, or expressive glow with Flow. Older guidance remains useful only where it does not conflict: accessibility, content hierarchy, responsive composition, interaction states, data integrity, and useful component behavior.

This system applies to Feed, Profile, Search, Saved/Bookmarks, messages and Chat, Activity and Announcements, Post Detail (both React/Inertia and Blade fallback), Edit Utas, Topics/Hashtags, Points history, Followers/Following, Dashboard, account settings, protected 2FA and recovery, Welcome, authentication and Google onboarding, errors, and overlays. The scope does not authorize changing all of those pages at once.

- **No unsolicited redesign:** preserve stable, approved surfaces and working product behavior; change only explicitly authorized scope.
- **Filament Admin:** retain its separate visual language unless specifically authorized for an admin task.
- **Documentation is not a deployment claim:** CI success, merged PRs, and this specification do not establish Production state or human visual approval.
- **User approval is required before actions:** do not access systems, edit repo files, commit, push, create PRs, merge, run workflows, or deploy without the required explicit approval for that action. Permission for one step does not extend to the next.
- `AGENTS.md` governs engineering constraints, safety, and rendering architecture; keep both documents aligned when either is changed.

## 2. Design Vision and Core Principles

SkuySocial is a conversation-first social product: modern, clean, calm, welcoming, readable, and fast. Glass provides subtle context around navigation and floating UI, not visual spectacle.

1. **Content first.** Posts, authors, replies, messages, and media lead the hierarchy. Typography, spacing, and order matter more than decorative panels.
2. **Feed and Profile are references, not blank canvases.** Preserve recognizable spacing, patterns, navigation, and visual identity unless a reproducible regression or explicit product decision requires a change.
3. **Use violet selectively.** It is an interface accent, not the color of every icon, border, surface, button, or state.
4. **A surface has a purpose.** A post is not necessarily a floating card; a menu is not necessarily a sheet; a status is not automatically a pill.
5. **Glass remains restrained.** Use it for navigational chrome, contextual menus, and floating elements; keep posts, text, forms, and thread reading surfaces legible and usually solid.
6. **Responsive by composition.** Desktop, tablet, and mobile can rearrange content without changing meaning or hiding essential actions.
7. **States are part of the design.** Loading, empty, error, disabled, permission, unread, selected, and success states need accurate data and clear affordances.
8. **Accessible by default.** Respect keyboard interaction, focus, zoom, localization, contrast, reduced motion, reduced transparency, and screen readers.
9. **No fake UI.** Counts, names, badges, navigation destinations, notifications, and visible controls must connect to genuine state or working behavior.

## 3. Color and Theming

### Non-negotiable identity values

| Design role | Canonical value | Required behavior |
| --- | --- | --- |
| Light page canvas | `#FFFFFF` | White, clean, content-first foundation. |
| Dark page canvas | `#0D0E13` | Neutral dark foundation; preserve readable separation of content and overlays. |
| Violet UI accent | Example `#6E5BEF` | Use sparingly for meaningful interface emphasis. Verify contrast in each context. |
| Verified account badge | **`#1877F2`** with **white check** | Identical asset in both themes and on every product surface. Never recolor to violet or teal. |

The four identity values above are design decisions, not permission to hardcode arbitrary colors across pages. Other neutral, success, warning, danger, disabled, and interactive-state colors must be semantic, contrast-checked, and consistent with existing approved Flow surfaces. This document does not claim a new complete numeric palette was approved.

### Semantic token contract

Prefer existing `--skuy-*` semantic names in source code and Tailwind v4 CSS-first tokens. Map them to Flow before changing UI; do not create an incompatible parallel token system. The following table describes **roles and target direction**, not a claim about currently shipped CSS values:

| Existing semantic token family | Flow role |
| --- | --- |
| `--skuy-bg-canvas`, `--skuy-background` | Light `#FFFFFF` / Dark `#0D0E13` page canvas; preserve compatibility aliasing where used. |
| `--skuy-bg-workspace`, `--skuy-surface-base` | Quiet workspace ground; distinguish content by structure, not warm/teal tint. |
| `--skuy-surface`, `--skuy-surface-raised` | Legible primary reading/form surfaces; near-white or neutral dark as appropriate. |
| `--skuy-surface-elevated`, `--skuy-surface-overlay` | Contextual popovers, menus, dialogs and sheets, with readable separation. |
| `--skuy-surface-subtle` | Restrained neutral grouping, loading, and selected support. |
| `--skuy-surface-glass` | Theme-aware near-opaque navigation/context surface with solid fallback. |
| `--skuy-text-primary`, `--skuy-text-secondary`, `--skuy-text-muted` | Hierarchical text with tested contrast; neutral rather than warm-tinted. |
| `--skuy-border-subtle`, `--skuy-border-soft`, `--skuy-border-strong` | Neutral separators and discernible input/control boundaries. |
| `--skuy-brand`, `--skuy-brand-hover`, `--skuy-brand-pressed`, `--skuy-on-brand` | Violet-related primary accent and its accessible interaction/on-color states where needed. |
| `--skuy-secondary`, `--skuy-secondary-hover`, `--skuy-on-secondary` | Secondary actions; neutral or reserved semantic treatment rather than legacy teal. |
| `--skuy-selected`, `--skuy-on-selected`, `--skuy-surface-accent` | Clearly selected states with contrast and a non-color cue; **not legacy mint**. |
| `--skuy-focus` | High-contrast keyboard focus ring, distinct from decorative emphasis. |
| `--skuy-danger`, `--skuy-on-danger` | Real destructive/error states; do not replace with brand violet. |
| `--skuy-accent`, `--skuy-reaction`, corresponding `--skuy-on-*` | Purpose-specific reactions/achievements; avoid legacy saffron as an automatic design rule. |
| `--skuy-shadow-soft`, `--skuy-shadow-raised`, `--skuy-shadow-floating` | Restrained existing elevation roles; no universal floating-card treatment. |

**Deprecated as visual direction:** legacy teal/mint/saffron color values, warm canvas values, and `--skuy-glow-primary` / `--skuy-glow-secondary` as authorization for ambient lighting. If those tokens remain in code for compatibility, do not infer that their old appearance is approved. Retire or remap them only in a separately authorized, tested implementation change.

### Color rules

- Use the canonical verified badge asset independently of theme, hover, selected state, and brand violet.
- Color must never be the only sign of selection, unread content, success, warning, danger, or reaction. Pair it with a visible shape, label, weight, icon, or announcement.
- Aim for WCAG AA contrast: normal text at least **4.5:1**; large text and non-text control/focus indicators at least **3:1**. Recheck over images and translucent surfaces.
- Do not introduce decorative gradients, auroras, shell glow, neon effects, color fog, or backdrop lighting. Functional image scrims for legibility are different from decorative gradients.
- Avoid default tinted page backgrounds and colorful card grids. Whitespace, hierarchy, and thin neutral separators should do the work.

## 4. Verified Account Badge — Hard Contract

The **only** approved account verification artwork is `public/images/verified-badge.svg`:

- Background/badge blue: **`#1877F2`**; checkmark: **white**.
- Same geometry and color in Light and Dark and in Feed, Profile, Post Detail, Search, Chat, user lists, comment/reply identity rows, and all other surfaces.
- Do not substitute inline SVG variants, themed SVGs, emoji, CSS recoloring, or generic check icons for the canonical asset.
- Badge visibility comes from genuine verified account state. The accessible name must convey verification; purely decorative duplicates can be hidden from assistive technology when the state is already named.
- Keep the approved navigation unread-badge geometry separate from account verification. Shared page CSS must not unexpectedly override icon-relative size, alignment, or positioning.
- Any exception requires a separate explicit product decision and affected-page visual QA.

## 5. Typography

Use **Plus Jakarta Sans** as the existing primary UI family where supported by the current application, with a legible system fallback. Preserve current approved Feed/Profile typography instead of forcing a new font scale across established pages.

| Role | Baseline guidance | Intent |
| --- | --- | --- |
| Page heading | 24–30px, approximately 1.2 line-height | Screen context without oversized decoration. |
| Section heading | 18–22px, approximately 1.3 line-height | Meaningful subsection only. |
| Post author/name | 15–16px, approximately 1.3 line-height | Clear identity. |
| Post/reply/body | 16px, approximately 1.5–1.6 line-height | Comfortable reading. |
| Metadata | Approximately 13px | Timestamps, visibility, counts. |
| Buttons/labels | Approximately 14px | Direct, readable controls. |
| Supporting caption | Approximately 12–13px | Secondary information, still readable. |

Treat these as ranges; the accepted Feed/Profile implementation remains the concrete reference. Use sentence case, sensible weights, and avoid all-caps, wide letterspacing, and decorative display fonts. Default feed reading measure on wider screens is approximately **40–44rem**, subject to the approved layout. Long text must expand vertically. Usernames, tags, URLs, and translations must wrap or truncate safely with accessible full text as appropriate. Body text must remain usable at 200% zoom.

## 6. Spacing, Shape, and Layout

### Spacing

Use an existing 4px-based scale; where these tokens exist, preserve their role:

| Token | Value | Typical use |
| --- | --- | --- |
| `--space-1` | 4px | Compact icon/label inset. |
| `--space-2` | 8px | Metadata groups. |
| `--space-3` | 12px | Identity rows, compact controls. |
| `--space-4` | 16px | Standard gaps and padding. |
| `--space-5` | 20px | Mobile gutters and post interiors. |
| `--space-6` | 24px | Section or desktop gutter. |
| `--space-8` | 32px | Major content grouping. |
| `--space-10` | 40px | Intentional breathing room. |
| `--space-12` | 48px | Large separations. |
| `--space-16` | 64px | Rare Welcome/empty-state emphasis. |

Use these existing radius roles without turning every item into a pill: `--radius-none` (0), `--radius-small` (8px), `--radius-medium` (14px), `--radius-large` (22px), and `--radius-full` (circular avatars/small status controls). Follow accepted Feed/Profile shapes over new global overrides.

### Composition

- Feed content stays at a comfortable measure; the desktop shell can use available width with a real navigation rail and **optional** contextual right column.
- Hide or collapse secondary content before making primary reading or chat columns unusably narrow.
- Mobile is a deliberate single-column composition, with reachable controls and content clear of sticky navigation, safe areas, and the on-screen keyboard.
- Use flexible widths, `minmax(0, 1fr)`/wrapping where appropriate, and natural content height. Avoid horizontal scrolling at **320px and above**.
- Source/DOM reading order should match visual and keyboard navigation order.
- Use whitespace as structure; avoid a full-page rounded wrapper, nested cards, unneeded KPI tiles, and repeating stat-chip decoration.

## 7. Surface, Glassmorphism, Borders, and Elevation

- **Solid or sufficiently opaque surfaces** are the baseline for posts, messages, input fields, forms, account settings, auth, and primary reading content.
- **Subtle glassmorphism** is permitted on authenticated top navigation, mobile bottom dock, menus, sheets, and meaningful floating controls. It is not a second theme or a universal surface style.
- A near-opaque glass surface may use a small amount of blur (the previous engineering guideline bounded desktop top bar to **8px**, mobile top bar to **4px**, bottom dock to **6px**). These are ceilings, not requirements to add blur.
- Supply a fully solid fallback for unsupported backdrop filters and reduced-transparency preferences; never sacrifice text contrast or click targets.
- Separate reading regions with spacing and subtle neutral borders. Feed timeline items should not become highly shadowed glass cards.
- Reserve intentional elevation for menus, modals, sheets, or a floating compose control. Prefer thin borders to heavy shadows; do not add ambient radial fields, glow, gradient halos, or neon edges.
- Keep overlay placement, focus trapping/return, safe-area padding, scroll locking, and dismissal accessible.

## 8. Responsive Navigation — Fixed Product Contract

### Mobile bottom navigation

On the applicable authenticated product navigation, there must be **exactly five primary destinations in this order**:

1. **Beranda** — Home/Feed.
2. **Cari** — Search.
3. **Buat** — genuine create/compose action.
4. **Aktivitas** — Activity.
5. **Pesan** — Messages.

Do not insert a sixth item, reorder destinations, silently replace a destination with an unrelated menu, or create a decorative compose button with no working action. Preserve real route URLs, browser back behavior, genuine unread counts, state persistence, and appropriate localization. The central create action must remain clearly usable.

- Each item requires a visible selected/active state, accessible label, keyboard focus treatment, and a comfortable mobile touch target (aim for **at least 44×44 CSS px** where practical).
- Account for `env(safe-area-inset-bottom)`, sticky bar height, and content scroll clearance; do not obscure the final message, composer, form button, or Chat **Kirim** action.
- When the mobile keyboard opens, chat composition and Send must remain visible/reachable. Test real device or browser behavior; CSS-only assumptions are insufficient.
- Unread badges must be anchored to the intended icon, maintain geometry, and use real data.
- Navigation styles should remain in the shared navigation component/layout and shared CSS rather than per-page overrides.

### Desktop and tablet navigation

Keep the established far-left rail/desktop composition and actual destinations. Selected routes require identifiable text/icon feedback, not just violet. Secondary context is optional. At intermediate widths, recompose rather than allowing cramped columns. Preserve accessible navigation order, keyboard operation, and strong but restrained real compose affordance.

## 9. Shared Components and Interaction Patterns

### Feed and posts

- Author, avatar, verified badge, timestamp, content, media, and interactions have clear hierarchy.
- Use restrained timeline separators or compact stable per-post surfaces matching the approved Feed; no shadow on every post, nested ornamental panels, or forced full-height cards.
- Preserve real like, comment, reply, bookmark, repost, and follow state/counts; selected behavior must not depend on color alone.
- Long posts and translated text expand without clipping. Link destinations and media viewer behavior must be genuine and accessible.
- Empty/loading/error/end-of-list states must reflect real API/database state, not mock posts.

### Profile and user identity

- Use the **latest approved Profile revision** as authority: cover/no-cover handling, avatar alignment, identity actions, follow controls, text measure, and the blue verified badge.
- Empty cover must not create broken color/geometry; no fake profile stats or invented information.
- Real follow state, followers/following counts, privacy restrictions, own-profile vs. other-profile actions, and account permissions must remain accurate.
- Avoid page-local CSS that overrides shared verification or navigation assets.

### Post Detail, comments, and replies

- The default **React/TypeScript/Inertia** Post Detail and the **Blade fallback** must represent the same actual product behavior and visual direction.
- Maintain content, media viewer, thread structure, reply nesting, edit rights, action visibility, and genuine counts.
- Provide real navigation back to Feed/Profile and keep mobile overlays/composers clear of bottom navigation and keyboard.
- Do not use a purely visual redesign to introduce new authorization or data-flow behavior.

### Inbox and Chat

- Distinguish inbox previews, unread state, blocked/permission state, active conversation, message bubbles, timestamps, and input area using subtle hierarchy.
- **The Kirim/Send action must remain visibly reachable**, including narrow screens and when a virtual keyboard is open. Ensure composer input can wrap/resize and does not sit behind the bottom dock.
- Sending, disabled/loading state, failure recovery, scroll to latest, unread updates, and message identity must be functional and truthful.
- Screen-reader labels, focus return, long messages/links, and empty conversations require intentional handling.

### Search, Topics, Saved, Activity, Points, and social lists

- Keep search fields legible and keyboard-operable, with real query loading, no-result states, filters, and navigable results.
- Topics/hashtags, Saved, Followers/Following, Points history, announcements, and Activity use genuine counts, state, links, and pagination.
- Notifications and unread badges communicate state through shape/label plus appropriate semantic emphasis, never a decorative color dot alone.
- Loading skeletons should match expected content geometry and stop when data resolves or fails.

### Composer, forms, and account settings

- Buttons show clear priority and real disabled, hover, focus, pending, success, and error states. Avoid active-looking placeholders.
- Form labels, hints, validation errors, password visibility controls, and destructive actions must be accessible and truthful.
- Maintain actual access controls for edits, replies, privacy, password/email changes, 2FA/recovery, and account management.
- Composer, uploads, character counts, media previews, and submission feedback must be connected to real behavior; do not fabricate content or counts.
- Sheets, dialogs, menus, and toasts require discoverable closing, proper semantics, focus handling, and responsive positioning.

### Welcome, authentication, Google onboarding, and error pages

- Keep clean Flow composition rather than introducing a separate warm/teal hero style.
- Maintain email/password flows, verification/reset, optional Google OAuth, and the onboarding requirement that **new Google users choose their display name and username** before account creation.
- Use direct, professional security/error copy, clear next actions, and accessible fields.
- Error pages **403/404/419/429/500** require recognizable Flow identity, truthful explanations, and valid navigation/retry affordances.

### Dashboard

- Preserve **existing real** welcome, account, check-in, points, and destination features. Do not add invented KPIs, fake activity, decorative widgets, or unnecessary hero glow.
- Established Dashboard layout may use controlled modular grouping, but the old "Premium Visual Revolution" bento/gradient prescription is **not an independent authority** to redesign the page.
- Keep desktop, tablet, and mobile source order and long-name/localization resilience.

## 10. Iconography, Media, and Motion

- Use consistent icon stroke/weight/size and established approved Feed/Profile icon language. Icons are actionable or meaningful, not decorative filler.
- Every icon-only control needs an accessible name and visible focus/hover feedback. Decorative icons may be hidden from assistive technology.
- Avatars remain circular; media respects aspect ratio, crop behavior, loading, fallback, and available alt descriptions.
- Keep motion restrained, brief, and purposeful. Prefer subtle transition for selected states, dropdowns, toasts, and confirmations; no continual decorative animation or bouncing icons.
- Support `prefers-reduced-motion`; nonessential motion should be removed or simplified. Do not rely on animation to communicate success or error.

## 11. State and Accessibility Contract

All meaningful controls must define **default, hover (where applicable), active/selected, keyboard focus, disabled, loading, success, error, and permission/blocked** behavior as relevant. Hover-only functionality is not acceptable.

- Use semantic native controls and landmarks; labels and validation must be announced accessibly.
- Maintain logical tab order, visible focus, keyboard activation/dismissal, and dialog focus restoration.
- Re-check visual and non-text contrast in **both themes**, including transparent overlays and images.
- Maintain adequate touch targets and spacing, especially for navigation, compose, menus, action rows, and message sending.
- At **200% zoom**, text and controls remain usable; no mandatory horizontal scroll for core flows.
- Respect OS/user reduced motion and reduced transparency; theme switching must not cause first-paint flashes or conflicting saved preference state.
- Use the existing `partials.theme-bootstrap` before stylesheets and the established `window.skuyTheme.persist` flow when implementing or reviewing them; follow `AGENTS.md` rather than inventing competing theme initialization. Preserve saved Light/Dark preferences, the documented **dark-first default for Feed**, and the **system-preference default on other product pages** unless an explicitly approved product decision changes them.
- Genuine access restrictions, 2FA screens, recovery states, and blocked interactions must not be styled to imply a usable action when it is unavailable.

## 12. Localization and Content Resilience

The product is Indonesian-first and English-supported. Design labels, controls, and layouts for both `id` and `en` without shrinking text just to hide overflow.

- Prefer short, clear, conversational UI copy; security, privacy, account and error copy must be explicit and professional.
- Long names, usernames, hashtags, links, timestamps, count formats, and system notices must wrap, clamp with an accessible full value, or recompose predictably.
- Avoid icon-only meaning, unexplained abbreviations, hardcoded localized strings in layout-only CSS, and unsupported translated mockup promises.
- Never label an action as successful before backend confirmation or actual product state.

## 13. QA Matrix and Acceptance Criteria

A UI change is complete only when it is scoped, reviewed, and **actually validated**; a passed CI job is not a replacement for visual/interaction QA.

| Dimension | Minimum validation |
| --- | --- |
| Themes | Light `#FFFFFF`; Dark `#0D0E13`; meaningful contrast and unchanged blue verified badge. |
| Viewports | **320px, 375px, 768px, 1280px** and wider where the affected layout needs it; no horizontal overflow. |
| Navigation | Exactly 5 mobile destinations in contract order; active states, keyboard/tap targets, safe area and real badge counts. |
| Theme behavior | First paint, saved preference, Alpine/bootstrap compatibility, reload, and reduced transparency/motion. |
| Localization | Indonesian and English, including long labels and user-generated content. |
| Interaction | Real click/keyboard/touch behavior; loading/empty/error/read-only and relevant permission/blocked states. |
| Visual | Real rendered browser screenshots or comparable inspection for changed pages; compare to approved Feed/Profile references. |
| Feature parity | Check React/Inertia and Blade Post Detail where affected; real mobile Chat composer/Send where affected. |
| Data integrity | No invented counts, badges, statuses, actions, or bypassed authorization. |
| CI | Run checks appropriate to actual code changes and verify results for the exact commit SHA; do not call unrun checks PASS. |

The full page matrix when performing broad, **separately approved** QA includes Feed ↔ Profile ↔ Post Detail ↔ Edit Utas ↔ Search ↔ Topics/Hashtags ↔ Saved; Inbox/Chat; Activity/Announcements; Points/Followers; Dashboard/Settings/2FA; Welcome/Auth/Google onboarding; error pages; and overlays such as composer, media viewer, reply thread, pagination, menus, and modals.

Do **not** treat the historical unchecked items in GitHub Issue #18 as proof that a surface is unimplemented; check current code and observed behavior. Do **not** assume the closed, unmerged PR #25 fixes are present in `main`; verify before acting.

## 14. Engineering and Governance Boundaries

This is a **design authority**, not permission to change architecture, repositories, systems, or Production.

- Preserve Laravel/Blade/Alpine as the dominant product architecture. React/Inertia is selective; Post Detail has a default React page with Blade fallback. Do not migrate more screens to React as part of a design update.
- Keep Filament Admin outside Flow changes.
- Maintain authentication, permissions, real state, privacy, upload validation, social interaction invariants, and accessible navigation while editing UI.
- New design decisions require a scoped product reason and explicit user approval. Do not broaden a documentation correction into a UI redesign.
- Before editing source or making a PR, inspect actual component and CSS contracts; avoid conflicting overrides and duplicate tokens.
- Documentation-only edits do not require PHP/JS builds or browser tests unless separately requested. For actual UI changes, follow relevant project test and browser QA procedures.
- Never access or modify Production, expose secrets/user data, run privileged or destructive operations, or deploy without separately scoped authorization and safeguards.
- No commit, push, PR, merge, workflow run, or external mutation without explicit approval for the applicable action.

## 15. Retired Directions and Interpretation

The following are **historical, not active** design authorities:

- Neo-Brutalism product UI.
- SkuySocial Expressive Clean's warm-neutral foundation and teal/mint/saffron palette.
- Premium Visual Revolution's shell ambient glow, radial gradients, and Dashboard hero gradient prescription.
- Full Liquid Glass and indiscriminate translucent card styling.

Do not keep obsolete instructions alongside Flow as equal alternatives. If an existing page or CSS token still reflects the older visual direction, treat it as a **potential discrepancy requiring evidence and scope approval**, not a reason to change the whole product automatically.

**Single governing rule:** preserve the latest approved SkuySocial Flow Feed/Profile patterns, the five-item mobile navigation, the canonical blue verified badge, accessible real states, and working product behavior. Verify before claiming a fix, and obtain approval before acting.
