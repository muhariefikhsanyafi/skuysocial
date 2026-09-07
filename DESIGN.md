# SkuySocial Design Direction

## Authority

SkuySocial has an established colorful Neo-Brutalist identity.

This file is the authoritative project-specific design direction.

Anti-Slop is a quality filter only.

Anti-Slop MUST NOT replace, flatten, reinterpret, or redesign intentional
SkuySocial design decisions merely because they differ from generic UI
conventions.

## Product Identity

SkuySocial is a social application focused on conversational/thread-based
interaction.

The experience should feel:

- recognizable
- energetic
- direct
- social
- bold

without becoming visually chaotic.

## Core Visual Identity

Preserve:

- colorful Neo-Brutalism
- thick / strong borders
- hard offset shadows
- sharp / square geometry
- bold typography
- high contrast
- deliberate hierarchy
- established outline/icon language
- responsive desktop/mobile behavior
- established light/dark treatment

Established accent family:

- Yellow
- Green
- Purple
- Red

Compatible existing accents may remain where already established.

## Geometry

Prefer:

- rectangular forms
- sharp geometry
- strong outlines
- deliberate hard shadows

Do not automatically soften components.

Rounded corners may remain where already intentionally established or where
there is a functional reason.

Do NOT convert SkuySocial into an excessive rounded-card interface.

## Shadows

Preserve the established hard-offset-shadow language.

Avoid generic:

- diffuse shadows
- floating SaaS elevation
- soft card shadows
- glass-like depth

## Color

Use intentional high-contrast combinations and established SkuySocial accents.

Avoid arbitrary:

- gradients
- generic purple/blue SaaS palettes
- pastel redesigns
- neutral corporate palettes
- glassmorphism
- unnecessary neon treatment

Color should have a role.

## Typography

Typography should remain:

- bold
- legible
- direct
- strongly hierarchical

Do not replace existing typography merely because a generic AI/design rule
prefers something else.

## Component Consistency

Before creating a new visual pattern:

1. inspect existing equivalent SkuySocial components
2. reuse established border, shadow, spacing, geometry, typography and icon
   conventions
3. introduce a new pattern only when existing patterns cannot express the
   required interaction

Do not redesign established components during unrelated work.

## Mobile

Mobile is first-class.

Preserve:

- responsive behavior
- usable touch targets
- readable typography
- existing mobile/bottom navigation conventions
- appropriate spacing
- no unintended horizontal overflow
- recognizable Neo-Brutalist identity

## Light / Dark

Both are established product behavior.

New UI must support the relevant existing light/dark treatment.

Do not flatten dark mode into generic black/gray SaaS styling.

Preserve:

- borders
- accents
- hierarchy
- contrast
- hard-shadow character where applicable

## Icons

Follow the established SkuySocial outline/icon language.

Do not arbitrarily introduce:

- unrelated icon families
- decorative emoji replacing functional icons
- inconsistent visual icon styles

## Copy and Tone

Authentication, security, privacy, account settings, system messages and email:

- formal
- concise
- professional
- natural

Bahasa Indonesia:
use formal Bahasa Indonesia.

English:
use clear professional English.

Social/feed interactions:
may be more conversational and natural.

Marketing/welcome:
may retain stronger SkuySocial personality.

"Skuy" may remain intentional brand vocabulary.

Avoid copy that is:

- exaggerated
- childish
- forced
- overly promotional
- obviously AI-generated

## Product Glossary

Preserve these intended concepts unless a future explicit product decision
changes them:

Bahasa Indonesia / English

Utas / Thread
Nama pengguna / Username
Suka / Like
Tersimpan / Saved
Pengikut / Followers
Mengikuti / Following

SkuySocial remains untranslated as the product name.

"Skuy" is allowed as intentional brand vocabulary.

## Internationalization

Supported locales:

id = Bahasa Indonesia
en = English

Product default:
id

Fallback:
en

Localization must NOT change SkuySocial's visual identity.

Layouts must accommodate translation-length differences without:

- button overflow
- clipping
- broken navigation
- broken mobile layouts
- arbitrary font-size reduction
- inconsistent spacing

Prefer layout flexibility over unnatural translation shortening.

## Accessibility

Accessibility is required while preserving Neo-Brutalism.

Ensure where relevant:

- sufficient contrast
- visible keyboard focus
- semantic controls
- accessible names
- keyboard operability
- usable touch targets
- readable typography
- understandable loading states
- understandable error states
- understandable disabled states

Accessibility is NOT authorization to replace SkuySocial with generic UI.

## Email Design

SkuySocial email uses an email-safe interpretation of the same identity:

- strong borders
- hard offset shadows where supported
- bold hierarchy
- high contrast
- rectangular geometry
- established accent family

Email compatibility takes priority over pixel-perfect website reproduction.

Do not revert to generic Laravel or generic SaaS email styling without a
technical reason.

## Explicitly Avoid

Do NOT redesign SkuySocial into:

- generic SaaS
- generic admin/dashboard UI
- glassmorphism
- soft-shadow corporate UI
- excessive rounded cards
- arbitrary gradients
- neutral corporate visual systems
- template-driven bento layouts
- generic AI-generated landing-page aesthetics

## Anti-Slop Scope Guard

Anti-Slop MUST NEVER expand the approved task scope.

If Anti-Slop finds an issue outside the current approved task:

1. report the finding
2. do NOT modify it
3. wait for explicit approval

Examples:

If the task is a database migration:
Anti-Slop must NOT modify:

- Feed
- navigation
- CSS
- Blade
- copywriting
- unrelated UI

If the task is a language switcher:
Anti-Slop may evaluate:

- the selector
- directly affected navigation/layout
- mobile behavior
- accessibility of that interaction

but MUST NOT redesign unrelated pages.

## Design Precedence

When there is a conflict between:

1. generic Anti-Slop advice
2. an intentional established SkuySocial design decision

the established SkuySocial design decision wins.

Exceptions:

- genuine accessibility defect
- security issue
- functional bug
- serious responsive failure

Even then:

preserve the original design intent while correcting the defect.

If that cannot safely be achieved:

STOP and report the conflict instead of silently redesigning the product.

## Delivery Gate

Before declaring UI/copy work complete, verify as applicable:

- consistency with existing SkuySocial UI
- desktop behavior
- mobile behavior
- light mode
- dark mode
- accessibility
- active locale
- Indonesian copy
- English copy
- different translation lengths
- loading/error/disabled states
- no generic AI/SaaS redesign
- no task-scope expansion
