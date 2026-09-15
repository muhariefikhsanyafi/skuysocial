# SkuySocial Design System

## 1. Status and Scope

This document is the authoritative design specification for SkuySocial
public/product UI.

- Status: Phase D1, documentation authority only.
- Direction: SkuySocial Expressive Clean.
- Product surface: public and signed-in product experiences.
- Admin surface: Filament Admin is outside this authority and keeps its
  existing visual language.

This authority covers the home feed, post composer, post detail,
replies/comments, profiles, search and Explore, notifications, settings,
authentication and other public product screens, plus future messaging or
chat product UI.

The previous Neo-Brutalism public/product direction is retired. Liquid Glass
is dropped. Do not combine either direction with SkuySocial Expressive Clean,
and do not create a parallel design system for Filament Admin.

Existing public UI that conflicts with this document should be migrated
intentionally over time. A migration may preserve behavior and content while
moving one surface at a time to these rules. New work follows this document
from the start.

## 2. Design Vision

SkuySocial Expressive Clean makes conversation easy to enter, easy to read,
and easy to return to. The feed stays quiet enough for people to follow a
thread. Meaningful social moments, such as a reaction, an active destination,
or a new notification, get a visible but short-lived expression.

The intended balance is:

- 80% to 90% calm, readable, content-first interface.
- 10% to 20% deliberate expression concentrated in navigation, primary
  actions, composing, reactions, notifications, selected states, and useful
  empty states.

Design Read: reading this as a conversational social product for
Indonesian-first and English-supported users, with a warm expressive-clean
visual language, dial ENERGY 2 / RHYTHM 2 / MOTION 1.

The product should feel modern, comfortable, human, social, responsive, fast,
and accessible without becoming sterile or constantly animated.

## 3. Brand Personality

SkuySocial is clear, warm, spirited, and grounded. It has enough personality
to feel like a place where people talk, but it never performs excitement over
the content.

The interface voice is conversational in social interactions and concise in
system tasks. It can use the intentional brand word "Skuy" where the product
voice calls for it. Authentication, privacy, security, account, and error
copy stay direct and professional.

Brand expression comes from a recognizable warm color signal, generous but
controlled spacing, active states that visibly respond, and a small amount of
editorial contrast in key moments. It does not come from decoration layered
onto every surface.

## 4. Core Principles

### Feed first

Posts, authors, replies, and media receive the strongest hierarchy. Whitespace,
type, and sequence do more work than boxes, shadows, or badges.

Reason: people come to read and respond, so the content should remain the
most visible part of the screen.

### Calm base, expressive signals

The base interface uses warm neutrals and restrained surfaces. The primary
brand color appears at moments that need attention, such as composing,
following, reacting, or acknowledging a notification.

Reason: limiting expression to meaningful events gives those events weight.

### A shape is a sentence

Corners, containers, and controls should communicate what an object is. A
post is not automatically a card. A menu is not automatically a sheet. A
status is not automatically a pill.

Reason: shape variation improves scanning and prevents a page of interchangeable
rounded containers.

### Content earns structure

Use the simplest composition that supports the content. Do not add a panel,
stat tile, chip, illustration, or divider only to fill space.

Reason: social screens become noisy when template structures compete with the
conversation.

### Responsive by composition

Desktop, tablet, and mobile are designed states with different priorities,
not the same layout at different scales.

Reason: navigation, reading width, controls, and composing need different
arrangements as the available space changes.

### Real states are part of the product

Loading, empty, error, disabled, selected, and permission states receive the
same care as the successful state. Each state explains what happened and what
the user can do next.

Reason: a social product is defined by its transitions and unavailable data
as much as by its full feed.

### Accessible expression

Personality must survive keyboard use, zoom, high contrast, reduced motion,
dark mode, and translation expansion.

Reason: a visual idea that excludes people is an incomplete product idea.

## 5. Color System

### Palette intent

The color system uses a warm off-white and charcoal foundation, a warm coral
brand signal, a deep teal supporting accent, and a restrained saffron accent
for reactions and attention states.

- Warm coral identifies SkuySocial actions and moments of social energy.
- Deep teal supports navigation, links, focus, and positive confirmation
  without competing with the brand action.
- Saffron is reserved for reactions, warnings, and occasional emphasis where
  a second signal is genuinely useful.
- Warm neutrals keep long reading sessions comfortable and distinguish the
  product from cold white SaaS surfaces.

Reason: three active hues plus neutrals create a recognizable palette without
turning every piece of content into a colored object.

### Semantic tokens

The following values are the baseline tokens for light and dark themes. Token
names are semantic. Components must use the role rather than hardcoding a
page-specific color.

| Token | Light | Dark | Role |
| --- | --- | --- | --- |
| `--color-background` | `#FFF9F2` | `#14171A` | App and page background. |
| `--color-surface` | `#FFFFFF` | `#1C2126` | Primary reading and form surface. |
| `--color-surface-elevated` | `#FFFFFF` | `#242A30` | Menu, modal, and intentionally lifted surface. |
| `--color-surface-subtle` | `#F2EEE8` | `#20262B` | Quiet grouping, selected support, and skeleton base. |
| `--color-text-primary` | `#20262E` | `#F8F1E7` | Post content, headings, and primary labels. |
| `--color-text-secondary` | `#4A5563` | `#D0D8DE` | Supporting descriptions and secondary metadata. |
| `--color-text-muted` | `#5F6B78` | `#AAB4BD` | Tertiary metadata and helper text that remains readable. |
| `--color-border-subtle` | `#D6DCE1` | `#39434C` | Low-noise separators that do not define a control. |
| `--color-border-strong` | `#788594` | `#82909B` | Input, control, media, and other visible boundaries. |
| `--color-brand-primary` | `#C7473F` | `#F07E70` | Primary action and social activity signal. |
| `--color-brand-hover` | `#A93B35` | `#FF9688` | Hover state for primary actions. |
| `--color-brand-pressed` | `#8E302D` | `#D86358` | Pressed state for primary actions. |
| `--color-on-brand` | `#FFFFFF` | `#321714` | Text and icons placed on brand surfaces. |
| `--color-secondary` | `#0D6B65` | `#6EC9C0` | Links, focus, navigation support, and confirmation. |
| `--color-secondary-hover` | `#09524E` | `#93DED6` | Hover state for secondary actions. |
| `--color-on-secondary` | `#FFFFFF` | `#10302E` | Text and icons placed on secondary surfaces. |
| `--color-accent` | `#B87400` | `#E8B94F` | Saffron attention, reaction, and warning signal. |
| `--color-on-accent` | `#1A1B14` | `#34250D` | Text and icons placed on accent surfaces. |
| `--color-focus` | `#0D6B65` | `#98E5DE` | Visible keyboard focus indicator. |
| `--color-selected` | `#FCE3DC` | `#3A2825` | Selected navigation, tab, or filter support surface. |
| `--color-on-selected` | `#7D2B2A` | `#FFE2D8` | Text and icons for selected support surfaces. |
| `--color-success` | `#0D6B65` | `#6EC9C0` | Successful completion and positive status. |
| `--color-on-success` | `#FFFFFF` | `#10302E` | Text and icons on success surfaces. |
| `--color-warning` | `#B87400` | `#E8B94F` | Caution that needs a human-readable explanation. |
| `--color-on-warning` | `#1A1B14` | `#34250D` | Text and icons on warning surfaces. |
| `--color-danger` | `#B3261E` | `#FF9B92` | Destructive action and validation error signal. |
| `--color-on-danger` | `#FFFFFF` | `#341513` | Text and icons on danger surfaces. |
| `--color-reaction` | `#B87400` | `#E8B94F` | Active like or reaction state. |
| `--color-on-reaction` | `#1A1B14` | `#34250D` | Text and icons on reaction surfaces. |

### Color rules

- The active palette is limited to the warm coral brand, deep teal secondary,
  saffron accent, and neutral surfaces. Additional colors require a semantic
  role and a one-line product reason.
- There is no default gradient token. A gradient may be introduced only for a
  specific hierarchy or brand need, with the reason recorded beside the
  component decision. It must not cover the page or sit behind readable text.
- Do not use glow, neon-on-black treatments, or colored fog as default
  decoration.
- Use solid surfaces for the app shell, posts, menus, and forms. Transparency
  is reserved for scrims or temporary focus effects where the underlying
  content remains legible.
- Never use color as the only indicator of unread, selected, success, warning,
  danger, or reaction. Pair color with text, icon shape, weight, fill, or a
  state announcement.
- Normal text targets a contrast ratio of at least 4.5:1. Large text targets
  at least 3:1. Control boundaries, focus indicators, and other non-text
  signals target at least 3:1 against adjacent colors.
- Contrast must be rechecked after opacity, overlays, images, or localization
  changes. The worst visible background is the one that matters.

## 6. Typography

### Typeface

Use Plus Jakarta Sans as the primary interface family, with a local or
approved web font source and a readable system fallback.

Reason: its open forms and broad language support suit Indonesian and English
text while giving SkuySocial a warmer, more contemporary voice than a default
system stack.

Use the same family for display and interface text by default. A separate
editorial face is not required. If a future welcome or empty-state concept
needs one, it must be approved for that specific moment and must never carry
meaning that the interface text does not also carry.

### Type hierarchy

| Role | Baseline | Use |
| --- | --- | --- |
| Display / special | 32 to 40px, 1.1 line height, weight 700 | Welcome moments or a key empty state only. |
| Page title | 24 to 30px, 1.2 line height, weight 700 | Main screen heading. |
| Section title | 18 to 22px, 1.3 line height, weight 700 | A meaningful sub-section, not every group. |
| Post author/name | 15 to 16px, 1.3 line height, weight 700 | Identity and authorship. |
| Body/feed content | 16px, 1.5 to 1.6 line height, weight 400 to 500 | Default post, reply, and explanatory text. |
| Metadata | 13px, 1.4 line height, weight 500 | Time, visibility, counts, and secondary context. |
| Labels/buttons | 14px, 1.25 line height, weight 700 | Controls and compact labels. |
| Captions | 12px, 1.4 line height, weight 500 | Media notes and low-priority supporting text. |

These are ranges, not a license to make every heading large. Use the smallest
level that preserves hierarchy. Avoid extreme weights, wide letter spacing,
monospace display type, and all-caps interface copy.

### Reading rules

- Keep feed copy at a readable measure, normally 40rem to 44rem on wide
  screens. Do not widen posts merely because the viewport is wide.
- Use fluid sizing or breakpoint-specific steps for large headings. Body text
  must remain comfortable at 200% zoom.
- Let long posts grow vertically. Do not clip post content to protect a fixed
  card height.
- Long usernames, names, hashtags, and localized labels may wrap or truncate
  with an accessible full-name affordance. Never let them force horizontal
  overflow.
- Truncation is appropriate for previews and navigation labels, not for the
  only copy of a post. Provide a real expand action when content is shortened.
- Do not reduce type size merely to hide Indonesian or English expansion. Let
  controls grow, wrap, or recompose.

## 7. Spacing and Layout

### Spacing scale

Use a 4px base scale with these shared steps:

| Token | Value | Typical use |
| --- | --- | --- |
| `--space-1` | `4px` | Icon to label, compact inset. |
| `--space-2` | `8px` | Metadata groups and small control gaps. |
| `--space-3` | `12px` | Identity rows and compact fields. |
| `--space-4` | `16px` | Default control padding and content gap. |
| `--space-5` | `20px` | Post internals and mobile page gutter. |
| `--space-6` | `24px` | Section separation and desktop gutter. |
| `--space-8` | `32px` | Page grouping and larger actions. |
| `--space-10` | `40px` | Intentional breathing room. |
| `--space-12` | `48px` | Major page separation. |
| `--space-16` | `64px` | Rare public welcome or empty-state breathing room. |

Reason: a compact scale keeps controls related while larger steps create
visible rhythm between content groups.

### Layout discipline

- The app shell uses a centered content frame on wide screens. The main feed
  rail stays readable, normally 40rem to 44rem, with optional side context
  only when that context serves the current page.
- A desktop navigation rail may occupy a stable column, but it must not push
  the feed beyond its reading measure. Secondary context yields before the
  feed becomes too narrow.
- Use `minmax(0, 1fr)`, wrapping, and fluid widths for children that can shrink.
  Fixed widths are reserved for genuinely fixed media ratios or controls with
  a documented reason.
- Whitespace is structural. Use tighter gaps inside a post and clearer gaps
  between posts, sections, and page regions.
- Avoid using a full-viewport-height section for ordinary app content. Let
  content determine height; use dynamic viewport units only for an intentionally
  full-height sheet or focused composing state.
- Keep source order aligned with reading and visual order so keyboard users
  encounter content predictably.

### Density

Default density is comfortable rather than sparse or dashboard-tight. Keep
enough vertical room for a post to be scanned as a complete thought, while
grouping metadata and secondary controls compactly. Use denser treatment only
for high-volume utility views such as a notification list or search results,
and never compress the primary reading text to fit more items on screen.

Reason: people need to distinguish one social thought from the next without
losing the ability to scan a busy product surface.

## 8. Shape System

Use a restrained family rather than one universal radius:

| Token | Value | Use |
| --- | --- | --- |
| `--radius-none` | `0px` | Full-width dividers, media crops, and deliberately open edges. |
| `--radius-small` | `8px` | Inputs, compact menus, and small controls. |
| `--radius-medium` | `14px` | Buttons, composer surfaces, search fields, and standard panels. |
| `--radius-large` | `22px` | Bottom sheets, primary compose moments, and selected navigation surfaces. |
| `--radius-full` | `9999px` | Avatars and compact status or filter controls only. |

Reason: radius variation lets the interface distinguish a control, a surface,
and a person without returning to hard-edged Neo-Brutalist geometry.

Shape rules:

- Do not make every container a pill.
- Do not place a rounded card around every post. A feed post normally uses
  its own spacing and a divider or change in surface, not a floating box.
- Use a larger shape when a surface is a deliberate focal point, such as the
  compose action or a bottom sheet. Do not reuse it as a default page radius.
- Avatars are circular because they identify people. A status chip may be full
  radius because its compact shape signals a bounded label.

## 9. Surfaces, Borders, and Elevation

### Surfaces

- The background is the ground plane.
- The primary surface supports reading and forms.
- The elevated surface is reserved for objects that sit above content, such as
  a menu, modal, or purposeful floating compose control.
- The subtle surface groups related content without turning it into a card
  grid.
- Feed posts normally sit directly on the feed surface. Use a surface change
  for a meaningful region, not for every row.

Reason: surface contrast establishes hierarchy before shadow or ornament is
needed.

### Borders

- Use 1px borders for input boundaries, media boundaries, and clear panel
  edges.
- Use the subtle border token for quiet feed separators. Separators should not
  appear between every line of a dense interaction.
- Use the strong border token where a control must be found, focused, or
  distinguished from its background.
- Do not use thick black outlines, hard offset borders, or a colored left stripe
  as decoration.

Reason: thin, semantic boundaries preserve calm reading while keeping controls
  discoverable.

### Elevation and shadows

Use elevation sparingly:

- `elevation-1`: `0 2px 8px rgb(24 30 36 / 0.10)` in light mode and
  `0 4px 12px rgb(0 0 0 / 0.24)` in dark mode, for a menu or floating action.
- `elevation-2`: `0 12px 30px rgb(24 30 36 / 0.16)` in light mode and
  `0 16px 36px rgb(0 0 0 / 0.36)` in dark mode, for a modal or sheet.
- Feed posts, navigation rails, tabs, and ordinary inputs are flat unless a
  specific interaction requires lift.

Reason: a shadow marks an object that is temporarily above the reading plane;
it should not make the entire app look like it is floating.

Do not use blur or backdrop-filter as the default surface treatment. Solid
surfaces are the baseline. A temporary scrim may use transparency when it
improves focus and preserves contrast.

## 10. Iconography

Use a coherent, semantic icon language. Retain an existing SkuySocial icon
where it represents the action correctly. For new icons, use a simple outline
with open counters and a consistent visual weight, then use a filled or
stronger state only when the action is selected.

Reason: familiar symbols reduce learning cost, while a deliberate weight and
selected-state change give the product a recognizable interaction language.

- Use 24px icons for primary actions, 20px for inline controls, and 16px only
  for metadata where the icon remains legible.
- Every icon-only control has an accessible name and a visible tooltip only as
  supporting help, never as the sole label on touch devices.
- Choose an icon because it describes the action, not because a library has a
  fashionable look. If no icon is genuinely relevant, use a text label.
- Do not use sparkle, star, magic, lightning, diamond, orb, robot, or emoji as
  generic feature decoration.
- Do not introduce a new icon library solely for its recognizable library
  style. Verify stroke weight, optical size, and filled-state behavior against
  the existing product.

## 11. Motion

### Motion dial

MOTION is 1. The interface uses short state transitions and direct feedback,
not choreography. Motion is concentrated around the action that just changed.

Reason: a reading-first social feed should settle quickly so people can follow
conversation without visual competition.

### Motion tokens

- Fast feedback: 120ms to 160ms.
- Standard transition: 180ms to 220ms.
- Entering a sheet or modal: 220ms to 280ms when the movement explains where
  the surface came from.
- Use an ease-out curve for entering and a shorter ease-in curve for leaving.
- Make every transition interruptible. A new action should not wait for an
  animation to finish.

### Approved motion

- A like or reaction changes icon weight and support surface immediately, then
  may use one brief scale or color transition to confirm the action.
- A composer may expand from its trigger into its destination surface so the
  user understands the relationship between the two.
- Active navigation may transition its selected surface and icon state once.
- A notification acknowledgement may briefly change the item state, then
  settle without a repeating pulse.
- Loading completion may crossfade the real content in after the state is
  ready. Skeletons must not pulse forever.
- Content expansion may animate height only when it does not trap focus or
  move the user's reading position unexpectedly.

### Motion limits

- No gratuitous bouncing, floating, parallax, or cinematic page transitions.
- No endless pulsing status dots. A dot must mark a real live, active, or
  warning state.
- Do not animate every list item on entry. A feed should become usable as soon
  as content is available.
- Honor `prefers-reduced-motion`. Remove transforms and non-essential movement;
  preserve the state change with an immediate color, label, or icon update.

## 12. Interaction States

Every interactive primitive defines these states where relevant: default,
hover, pressed, focus-visible, selected, disabled, loading, success, and
error.

- Default state establishes the quiet baseline.
- Hover adds a small surface or border change on pointer-capable devices. It
  must not be the only way to discover an action.
- Pressed state gives immediate tactile feedback through a surface, icon, or
  position change. Do not rely on a bounce.
- Focus-visible uses a clear 2px ring with an offset that remains visible in
  both themes. Never remove the browser focus indicator without a stronger
  replacement.
- Selected state changes at least two perceivable properties, such as icon
  fill plus label weight, or surface plus text. Color alone is insufficient.
- Disabled state remains legible and communicates why the action cannot run
  when that reason is not obvious. Do not reduce opacity until the label is
  unreadable.
- Loading state disables duplicate submission, names what is loading, and
  preserves the control's place in the layout.
- Success and error states include text or an accessible announcement, not
  just a green or red tint.

## 13. Responsive and Adaptive Layout

### Composition states

Define responsive states where content stops fitting, not by a fixed device
list. The baseline composition has three states:

#### Wide desktop

- A stable navigation rail or side navigation can remain visible.
- The feed keeps a controlled reading measure.
- Optional context, such as related activity, may sit beside the feed only
  when it has a clear job and enough width.
- The primary compose action remains visible without covering feed content.

#### Intermediate tablet and small laptop

- Collapse secondary context before shrinking the feed below a comfortable
  measure.
- Use a compact navigation rail or top navigation when a full rail no longer
  earns its width.
- Rebalance header controls and action rows rather than allowing them to wrap
  unpredictably.
- Test the entire width band between compact and wide states. It is not a
  scaled desktop screenshot.

#### Compact mobile

- Use a touch-first top bar and bottom navigation for the primary destinations
  when appropriate.
- Keep the feed full-width within safe page gutters and reserve space for any
  fixed navigation.
- Move secondary actions into a labeled menu or sheet. Keep compose and the
  most common feed actions reachable by one hand.
- Stack identity, media, tabs, and controls where the content needs the room.
- Let labels wrap or use a real accessible label. Never squeeze a desktop row
  into the viewport.

### Mobile rules

- Minimum interactive hit area is 44px by 44px, with visible gaps between
  neighboring controls.
- No horizontal page overflow. Long text, URLs, hashtags, tables, and media
  must wrap, reflow, or use a clearly labeled scroll region where scrolling is
  the intended behavior.
- Do not clip readable text with `overflow: hidden`.
- Use a mobile spacing register, generally reducing page gutters and section
  gaps while preserving touch targets and body readability.
- A fixed bottom navigation reserves its own safe-area and scroll space. The
  final post, button, or form field must remain reachable above it.
- Focused inputs must scroll above the on-screen keyboard with enough bottom
  padding to remain visible.
- Test at narrow widths, intermediate widths, 200% zoom, and with the longest
  supported labels in both locales.

## 14. Component Rules

The following rules define the baseline primitives for public/product UI.
Each new component must use semantic tokens and document any intentional
exception.

### App shell

The shell gives the product a stable background, a readable content frame, and
one clear focal area per screen. Keep the feed or active task visually ahead
of navigation chrome.

On wide screens, use the navigation and optional context columns only when
they preserve the feed measure. On compact screens, recompose into a compact
header and bottom or menu navigation. The shell owns safe-area padding and
focus order.

### Desktop navigation

Use a calm rail with clear labels, relevant icons, and one selected surface.
The selected state uses `--color-selected`, `--color-on-selected`, and a
visible icon or weight change. Keep primary destinations in a stable order and
do not include links for unbuilt pages.

Navigation can include the primary compose action when it is a real reachable
action. Secondary account actions belong in a menu or settings route rather
than competing with the feed.

### Mobile navigation

Use a compact bottom navigation for a small set of primary destinations, or a
labeled Menu control when the product's real route set does not fit. The
active item has a text label, icon state, and selected surface. Do not hide the
only route to a core task behind an unlabeled hamburger.

Reserve bottom-bar height and device safe-area space in the scroll container.
Keep the bar short enough that the content remains dominant.

### Header and top bar

The top bar establishes page context and contains only the actions relevant to
that context. Use a real back action on detail and sheet flows. Avoid stacking
multiple rows of controls on compact screens.

On desktop, the top bar may align with the content frame or navigation rail.
On mobile, keep the title, back/menu control, and one primary action within
comfortable reach. Long titles wrap or truncate with a complete accessible
name.

### Buttons

Buttons use `--radius-medium` by default, sentence-case labels, and a clear
priority hierarchy:

- Primary uses the brand token for the one main action in a region.
- Secondary uses an outlined or quiet surface treatment for useful alternatives.
- Tertiary uses text and spacing when a box would add noise.
- Destructive uses the danger role and states the action plainly.

Labels describe the action, such as "Post reply", "Save post", or a verified
localized equivalent. Avoid generic labels such as "Get Started", "Explore",
or "Learn More" when a more precise action is available. Do not append arrows
to every button.

All buttons meet the 44px minimum hit area and expose hover, pressed,
focus-visible, disabled, and loading states.

### Icon buttons

Use an icon button only for a familiar, frequent action or when a nearby label
already supplies context. Provide an accessible name, a 44px hit area, and a
visible pressed or selected state. Pair unfamiliar or high-impact actions with
a text label.

### FAB and primary compose action

Use a floating compose action only when composing is the primary frequent task
and the current shell does not already provide an equally reachable action.
Give it `--radius-large`, a solid brand surface, a readable label on wide
screens, and a strong icon plus accessible name on compact screens.

Reason: one prominent compose action helps people enter conversation quickly;
duplicating it in every region creates noise.

The action must never cover posts, replies, keyboard focus, or the bottom of a
form. On mobile, it may become a bottom-sheet trigger or a docked action when
that improves reachability.

### Post composer

The composer is a primary product surface, not a decorative card. Give the
input the strongest initial focus, show the current author identity, and keep
the submit action close to the text entry area.

Only render controls for real supported features. Attachments, mentions,
visibility, drafts, and scheduling require their own behavior and states.
Validation explains the problem beside the relevant field. Submission shows a
clear pending state and visible success or error feedback.

On mobile, use a focused sheet or full-width composing surface with keyboard
safe-area padding. Keep the primary submit action reachable without requiring
the user to dismiss the keyboard.

### Feed post

Posts usually flow directly in the feed. Use author identity, body content,
media, actions, and replies as a natural sequence separated by spacing and
quiet dividers. Use a surface or border change only when it clarifies a real
relationship, such as a quoted post or a focused detail view.

Do not nest a card around the post and another card around each action. Keep
the author and timestamp scannable, let content grow, and preserve the
original post relationship in detail and reply contexts.

On wide screens, keep the post measure controlled. On mobile, stack media and
actions, allow the body to wrap, and keep the action row from becoming a
desktop toolbar squeezed into one line.

### Avatar

Avatars identify people and use the full radius. Use real profile media when
available. Otherwise use an initial-based or simple geometric placeholder that
is clearly a placeholder, not a fabricated person.

Provide an accessible name or adjacent identity text. Do not use avatar color
alone to distinguish users. Protect the image with a stable aspect ratio and
an intentional fallback for loading, missing, and failed media.

### User identity row

The identity row establishes who authored the content before secondary
metadata. It may include avatar, display name, username, time, and visibility
in a compact hierarchy.

Keep names readable when they are long. Wrap or truncate secondary identifiers
without moving the primary action off-screen. On mobile, allow the row to
recompose into two lines rather than shrinking type.

### Post action row

Action rows prioritize the actions the product actually supports, normally
reply, reaction, repost, save, and share where those behaviors exist. Use
consistent order and spacing, but do not show empty affordances for missing
features.

Counts are real data and must not be invented. If a count is not available,
omit it or use an honest loading state. Icon, label, and state should remain
understandable at 200% zoom and on touch screens.

### Reaction and like state

Resting reactions are quiet. An active reaction uses the reaction token plus a
filled or stronger icon, changed label state, and a short confirmation motion.
The state must be perceivable without color, including for forced-colors and
screen-reader users.

Do not use a generic celebration animation or an always-visible colored glow.
Do not notify a user about their own reaction unless the product explicitly
defines that behavior.

### Replies and comments

Replies use indentation, spacing, and identity hierarchy to show conversation
depth. Keep nesting bounded in the visible layout; provide a real expand or
open-detail action for deeper branches.

A reply must remain attached to its post context. Reply composition inherits
the same validation, loading, error, and keyboard rules as the main composer.
Do not bury replies inside layers of cards.

### Inputs

Inputs use a visible label, a readable value, and a boundary that meets the
non-text contrast target. Placeholder text is instructional support, not the
only label. Keep helper text close to the field and associate errors with the
field programmatically.

Focus is visible in both themes. Invalid state uses an error message and an
icon or label in addition to the danger color. Do not clear valid user input
just to show an error.

### Textarea

Textareas support multi-line content without a fixed-height trap. They expand
or scroll in a predictable way and keep the submit action visible. If a real
character limit exists, show the limit and remaining state without inventing a
number for a feature that has not been defined.

On mobile, preserve bottom padding when the keyboard is open and keep the
focused text visible. Do not place essential controls only in a hover state.

### Search field

Search is a labeled field with a clear submit or live-search behavior, a
visible clear action when text exists, and a real loading state. The field may
be visually prominent on Explore, but it should remain compact on the feed.

Search results state the query context, preserve the query when possible, and
offer a meaningful empty or error action. Never fill results with invented
accounts, posts, topics, or counts.

### Tabs

Tabs switch between real sibling views. Use a clear selected indicator made of
text weight plus a surface, underline, or icon change. Keep tab order logical
and provide the appropriate tab and panel semantics.

On mobile, allow horizontal scrolling only for a short, labeled tab set that
cannot reasonably wrap. The selected tab must be discoverable without color
alone and the scroll position must keep it visible.

### Chips and filter controls

Use chips only for a real filter, applied state, removable query, or compact
status. They may use `--radius-full`, but not every control should become a
chip. A selected chip changes label, icon, or fill as well as color.

Keep filters reachable by keyboard and touch. On mobile, let the control row
wrap or open a labeled filter sheet instead of clipping.

### Dropdown and menu

Menus contain real actions and open from a labeled trigger. They use the
elevated surface, modest radius, and enough padding for touch. Keyboard users
can open, move through, activate, and close the menu with expected keys.

Menus close on Escape and return focus to their trigger. Do not use a menu as a
place to hide a core destination that should stay visible.

### Modal

Use a modal for a focused decision or short task that should block the page.
Give it a clear title, a close action, a visible focus treatment, and an
Escape path. Use a scrim to establish focus, not as decorative glass.

Trap focus while open, restore it on close, and prevent the underlying page
from receiving accidental input. On narrow screens, use a full-width or
sheet-like composition when it is more usable than a centered desktop dialog.

### Bottom sheet

Use a bottom sheet for mobile-first actions, filters, or short focused tasks.
It has a visible title or purpose, a close action, safe-area padding, and a
keyboard-safe scroll region. The sheet may use `--radius-large` at its top
edge, while the rest of the screen remains calm.

Keep the sheet content bounded and dismissible. Do not turn every menu into a
sheet on desktop.

### Toast

Use a toast for brief, non-blocking confirmation or failure when the message
does not need to remain beside a field. Include readable text, sufficient
contrast, and a way to dismiss when it could cover content.

Do not put essential instructions only in a disappearing toast. Announce
important status changes to assistive technology and avoid stacking multiple
toasts for one user action.

### Inline notice

Use an inline notice when the message belongs to the current region, such as
an offline state, permission limitation, validation summary, or retry result.
The notice includes a concise explanation and the next action when one exists.

Pair success, warning, and danger colors with a semantic icon or explicit
label. Do not use a decorative colored stripe as the notice's only signal.

### Notification item

Notification items lead with the event and the person or post involved. Use
real avatars and real event data only. Unread state combines a selected
surface, stronger text, and an accessible status such as "Unread".

Keep acknowledgement short and reversible where possible. Grouping may reduce
noise, but it must preserve the event's meaning and route to a real destination.
No notification should be generated for the user's own action unless the
product explicitly requires it.

### Profile header

The profile header leads with identity, avatar, bio, and the primary follow or
account action. It should feel like a person view, not a dashboard.

On wide screens, identity and actions may share a row with enough breathing
room. On mobile, stack identity and actions in an order that keeps follow or
edit reachable without covering the bio.

### Stats and follow controls

Stats support the profile decision and use readable text, not a row of KPI
tiles. Show only real counts and label them clearly. If a count is not
available, omit it or show a named loading state.

Follow, unfollow, edit, and block actions use clear labels, confirmation where
the action is consequential, and real loading or error states. Selected
following state must use label and visual state, not color alone.

### Media container

Media preserves its intended aspect ratio, has a useful alternative text or
caption, and exposes loading, missing, and failed states. Use a modest boundary
only when it helps distinguish the media from the surface.

Do not place readable text over an image without a verified scrim or solid
text region. Do not fabricate media previews, avatars, or thumbnails.

On mobile, media is fluid and stays within the content width. Cropping is
allowed for a thumbnail when the crop is intentional and the full media can
be opened.

### Skeleton and loading

Skeletons preserve the approximate shape of the content they replace, use the
subtle surface token, and include a visible or announced loading description.
They should not look like a finished product screenshot or use a decorative
shimmer loop.

Prefer stable placeholders that prevent layout shift. Load the feed in bounded
chunks and keep already-read content usable while more content arrives.

### Empty state

An empty state explains why the region is empty and gives the one action that
can populate it when an action exists. Examples include a new feed, no saved
posts, no search results, and no notifications. Each message must reflect the
real product state and locale.

Use an expressive heading or simple relevant mark only when it helps the user
understand the state. Do not add a generic illustration, fake statistic, or
decorative blob to fill the space.

### Error state

An error state names what failed in plain language, keeps the user's context
where possible, and offers a real retry, edit, sign-in, or support path. Field
errors sit beside the field; feed errors sit near the affected region.

Do not show raw exception text, stack traces, or storage paths. Do not present
an error only through red color or an icon.

### Pagination and infinite loading

The UI indicates when more content is loading and when the current result is
complete. A visible "Load more" action is the accessible fallback for any
infinite loading behavior. Preserve scroll position and avoid duplicate posts.

Pagination controls use real destinations or real loading behavior. Do not
show invented page counts or an endless spinner with no status. If there is no
more content, say so only when the product has enough information to know it.

## 15. Feed-Specific Rules

- The feed is the calmest major surface in the product.
- Keep one clear focal point per viewport, normally the current post or the
  composer when it has focus.
- Use author identity, post text, media, and action order as the primary
  hierarchy. Do not use dashboard KPI tiles or decorative summary cards.
- Keep posts flat by default. A detail view may use a stronger surface to
  separate the main post from its replies, but avoid card nesting.
- Use real counts, timestamps, and interaction states only. Never create
  sample-looking activity to make an empty feed look busy.
- Keep reply depth understandable with indentation and spacing. If a branch
  becomes deep, open it in a real detail route or controlled expansion.
- Use bounded loading and pagination behavior. Do not render an unbounded
  global collection in one view.
- On mobile, keep action targets in a comfortable row or wrap them into a
  deliberate two-line arrangement. Never hide core feed actions behind hover.

## 16. Profile Rules

- A profile answers who this person is and what the visitor can do next.
- Lead with identity and relationship actions before secondary counts.
- Use a restrained stats line, not a grid of equal dashboard tiles.
- Keep bio text readable and allow it to grow. Do not clip it to a decorative
  header height.
- Tabs represent real profile views, such as posts or replies, and show their
  loading, empty, and error states.
- On compact screens, stack the avatar, identity, action, bio, and tabs with
  enough separation for thumb use.
- Treat missing avatars as honest placeholders and never invent profile
  photos, follower counts, or activity.

## 17. Search and Explore Rules

- Search is the primary task. Keep the field visible, labeled, and easy to
  clear.
- Results are organized by the real product taxonomy. Use tabs or filters only
  when each option maps to a real result view.
- Explore may surface topics or accounts, but every item and count comes from
  real data. Do not build a decorative trend dashboard.
- Empty states distinguish an empty query, no matches, and unavailable data.
- Search errors preserve the query and offer a real retry or edit action.
- On mobile, stack the search field, filters, and results. Use a filter sheet
  only when the real filter set cannot fit comfortably.

## 18. Notification Rules

- Notifications prioritize who acted, what happened, and where the user can
  respond.
- Unread state is visible through text weight, support surface, and an
  accessible label. A tiny color dot may supplement the state but cannot carry
  it alone.
- Grouping is allowed when it improves scanning and preserves each event's
  meaning. Avoid a wall of colored badges.
- Acknowledgement changes state quickly and does not use endless animation.
- Loading, no-notification, failure, and permission states explain what the
  user can do next.
- Do not notify users about their own actions unless a product decision
  explicitly requires it.

## 19. Composer Rules

- Composing is an invitation to speak, not a promotional hero.
- Put identity, text entry, and the primary submit action in one understandable
  flow.
- Keep supported secondary actions nearby but visually subordinate.
- Show validation before submission when possible and show a pending state
  during submission.
- Preserve draft text on recoverable errors when the product supports drafts.
  Do not imply draft persistence if it does not exist.
- Use a focused sheet or dedicated mobile surface when the inline composer
  cannot remain comfortable above the keyboard.
- Make the post or reply destination clear after successful submission.

## 20. Dark Mode

Dark mode is an intentional theme, not an inversion of light mode. It uses
charcoal surfaces, warm light text, muted but readable supporting text, and
lighter versions of the brand colors with dark on-color text.

- Keep at least two visible surface levels so menus, sheets, and content do
  not flatten into one black plane.
- Do not use pure black as the default background or pure white for every
  surface.
- Recheck brand, secondary, accent, danger, disabled, selected, and focus
  states in dark mode. A token that works in light mode may not work against a
  dark surface.
- Images and media need their own contrast treatment. Add a scrim or text
  region when text overlays a light image.
- If a theme toggle is shipped, it is a real accessible control with a saved
  preference, keyboard support, and a clear state. Both modes must work before
  the toggle is considered complete.
- Prefer the user's system preference as the initial choice unless the product
  makes a documented product decision otherwise.

## 21. Accessibility

Accessibility is part of the design definition, not a post-build polish pass.

### Contrast and non-text signals

- Normal text meets WCAG AA at 4.5:1 or better. Large text meets 3:1 or
  better.
- Input borders, control boundaries, focus rings, and meaningful icons meet
  3:1 against adjacent colors.
- Verify text over media and overlays at the worst visible point.
- Pair color with text, shape, weight, fill, position, or an icon so state is
  never communicated by hue alone.

### Keyboard and assistive technology

- Every link, button, input, tab, menu, sheet, and dialog is reachable and
  operable with the keyboard.
- Tab order follows visual and reading order. Provide a skip link on long app
  surfaces where it materially shortens navigation.
- Focus-visible indicators are clear in both themes and are never removed
  without an equivalent replacement.
- Dialogs and menus close with Escape and return focus to their trigger.
- Use semantic landmarks, headings, labels, button names, list semantics, and
  status/live-region announcements appropriate to the content.
- Associate helper and error text with its field. Announce asynchronous
  loading, success, and failure without stealing focus unnecessarily.

### Hands, zoom, and motion

- Interactive targets are at least 44px by 44px where practical, preferably
  48px for primary touch actions, with enough separation to avoid accidental
  taps.
- Content remains usable at 200% zoom without clipped text or forced desktop
  layout.
- Focused fields remain visible above the mobile keyboard.
- Respect `prefers-reduced-motion` and preserve meaning when movement is
  removed.
- Disabled controls remain understandable and are not distinguished by
  opacity alone.

## 22. Localization and Content Resilience

Supported locales remain:

- `id`: formal Bahasa Indonesia as the default product locale.
- `en`: clear professional English as the fallback and supported locale.

Social copy may be conversational in both locales, while authentication,
security, privacy, account, and system copy stays formal and concise. Preserve
the product glossary: Utas / Thread, Nama pengguna / Username, Suka / Like,
Tersimpan / Saved, Pengikut / Followers, and Mengikuti / Following.

Implementation rules:

- Build labels, navigation, buttons, errors, empty states, and accessibility
  names for translation expansion from the start.
- Test both locales at every responsive composition state, including the
  longest real label in a group.
- Let buttons grow, wrap, or recompose. Do not solve translation expansion by
  clipping or arbitrary type reduction.
- Keep long usernames, hashtags, URLs, and post text inside their containers.
- Use locale-aware date, number, and count formatting. Do not invent a number
  merely to make a layout look balanced.
- Do not embed user-facing copy inside a visual asset when the text needs
  localization.

## 23. Loading, Empty, and Error States

Every data-bearing surface has at least these states:

| State | Required behavior |
| --- | --- |
| Loading | Explain what is loading, preserve layout shape where possible, and avoid an endless decorative animation. |
| Empty | Explain why there is no data and provide the real next action when one exists. |
| Error | Explain what failed, preserve useful context, and provide a real retry, edit, sign-in, or support path. |

Also define permission denied, offline, filtered-to-zero, and partially loaded
states when the feature can reach them. Each state must work in light mode,
dark mode, compact layouts, keyboard navigation, screen readers, and both
locales.

## 24. Anti-Patterns

The following patterns are explicitly prohibited in SkuySocial public/product
UI unless a product owner documents a specific functional reason and the
exception does not conflict with accessibility:

- Generic SaaS dashboard styling, including KPI tiles in the social feed.
- Excessive cards or card-inside-card nesting.
- Liquid Glass, glassmorphism as a page-wide character, or blur on every
  surface.
- Neo-Brutalist hard black borders, hard offset shadows, and sharp geometry as
  a public/product default.
- Random, full-page, or decorative gradients.
- Neon-on-black gaming aesthetics.
- Oversized hero sections inside the core app.
- Pill-shaped buttons, inputs, cards, menus, badges, and tabs by default.
- Excessive badges, chips, colored dots, or notification counters.
- Decorative blobs, generic illustrations, or assets that imply real people or
  data without a source.
- Generic AI sparkle motifs, robot marks, and emoji used as primary UI icons.
- Hover-dependent essential actions, especially on mobile.
- Desktop layouts merely scaled down into mobile.
- Excessive shadows, glows, or floating surfaces.
- Unnecessary dividers between every row or field.
- Animation without a state meaning, especially perpetual loops.
- Google or Material clone appearance, including copied component rhythm,
  palette, or visual identity. Material 3 Expressive may inform principles,
  but SkuySocial must remain recognizably its own product.
- Generic marketing copy, unsupported claims, invented statistics, fake
  testimonials, fictional user activity, or dead navigation.
- Any control that looks functional but has no real behavior.

## 25. Implementation Governance

- This file is authoritative for public/product UI. Read it before changing
  Blade presentation, styling, responsive behavior, public copy, or other
  product-facing visual work.
- Existing public UI that conflicts with this document should be migrated
  intentionally. Do not mix the retired Neo-Brutalist direction with the new
  system during migration.
- Do not introduce Liquid Glass or create a parallel design system for
  Filament Admin. Filament Admin remains outside this authority.
- New public components use semantic tokens, the shared shape and spacing
  scales, the interaction-state rules, and the component behavior defined
  here.
- Responsive behavior and dark-mode behavior are designed with the component,
  not bolted on after the desktop version is complete.
- Accessibility is part of completion criteria, including contrast, focus,
  keyboard behavior, semantics, touch targets, zoom, reduced motion, and
  non-color state communication.
- One-off styling requires a one-line reason tied to hierarchy, content,
  identity, or interaction. If the reason cannot be stated clearly, use an
  existing primitive.
- Prefer reusable primitives over page-specific visual hacks. A page-specific
  exception should be named and documented near the component decision.
- Do not add navigation items, controls, claims, assets, statistics,
  testimonials, or illustrations without a real destination, behavior,
  source, or clearly labeled placeholder.
- Verify real content, loading, empty, error, disabled, light, dark, compact,
  intermediate, wide, keyboard, zoom, reduced-motion, and `id` / `en` states
  before declaring a public surface complete.
- Keep Filament Admin changes out of public/product design work unless a
  separate admin design decision explicitly changes its scope.

## 26. Definition of Done

A public/product UI change is complete when:

- The surface follows SkuySocial Expressive Clean and does not blend retired
  Neo-Brutalism, Liquid Glass, or a copied product language.
- The focal point and content hierarchy are clear without decorative noise.
- Color, type, spacing, radius, borders, elevation, and icons use shared
  semantic decisions with a written reason for any exception.
- Feed content remains readable at its intended measure and long content does
  not clip or overflow.
- Desktop, intermediate, and mobile compositions are intentionally designed;
  the narrow layout is not a squeezed desktop.
- Light and dark themes preserve contrast and behavior, including all states.
- Loading, empty, error, permission, disabled, and success states are useful
  and perceivable.
- Keyboard, screen-reader, touch, zoom, reduced-motion, and focus behavior
  meet the accessibility rules in this document.
- Indonesian and English copy fit without clipping, broken navigation, or
  misleading truncation.
- Every interactive element has a real destination or behavior, and every
  displayed fact, count, person, image, and claim is real or honestly labeled.
- The implementation has been run and checked at the relevant breakpoints and
  themes, with console and interaction issues resolved.

## Next Design Deliverables

Before implementation, the next design deliverables are:

1. Home Feed, desktop mockup/spec.
2. Home Feed, mobile mockup/spec.
3. Profile, responsive mockup/spec.
4. Search / Explore, responsive mockup/spec.

Do not implement these mockups as part of Phase D1.
