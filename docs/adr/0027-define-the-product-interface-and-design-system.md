# ADR 0027: Define the Product Interface and Design System

## Status

Accepted

## Date

2026-08-21

## Relationship to prior ADRs

### Consumes ADR-0006, ADR-0023, ADR-0024, ADR-0025 and ADR-0026 unchanged; this document does not reopen any of them

This ADR makes no generation, retrieval, evidence-authority,
tenant-authorization or domain-semantics decision. It does make the bounded
browser-facing presentation-contract and route decisions defined below;
those additions do not alter the authority, validation or ownership
decisions established by the prior ADRs. Every
role boundary (ADR-0006, ADR-0025), every conversation/streaming state (ADR-0024),
every generation outcome (ADR-0023) and every observability/platform-admin
boundary (ADR-0026) is treated as settled, verified-against-the-real-repository
fact, not as something this document is free to redesign. Where this ADR
describes an interface state, it is describing how an *already-decided* domain
state is presented — never inventing a new one.

### Supersedes no prior ADR

Nothing here supersedes an accepted decision. Where the current frontend
contradicts a decision made elsewhere (for example, `WorkspaceUsage` rendering
conditionally on `role !== "member"` inline in a page component rather than as
a distinct route), this ADR treats that as an implementation detail to carry
forward faithfully into new component structure, not as an architectural
question to relitigate.

## Context

### Verified current frontend state (`apps/web`, inspected directly)

`apps/web/package.json` pins **Next.js 16.2.11**, **React 19.2.4** /
`react-dom` 19.2.4, TypeScript `^5`, App Router only (`src/app/`, no `pages/`
directory), `output: "standalone"`. Testing is Vitest 4 + Testing Library +
jsdom (`vitest run`), with `eslint-config-next` and `eslint-plugin-jsx-a11y`
already wired into lint. There is no CSS framework, no component library, no
icon library and no CSS-in-JS of any kind today — the entire visual surface is
one hand-written stylesheet, `src/app/globals.css` (1,217 lines), using
semantic BEM-ish class names (`.chat-message`, `.document-card`,
`.auth-shell`), not utility classes.

That stylesheet defines exactly nine custom properties in `:root`
(`--ink`, `--muted`, `--paper`, `--card`, `--line`, `--accent`, `--accent-dark`,
`--forest`, `--mint` — the orange/forest/off-white palette the user has asked
to replace) and then over twenty further raw, un-tokenized hex/alpha color
literals scattered through component-specific rules (status colors, danger
buttons, upload states, focus rings). One rule, `.operations-card`, references
`var(--border)` and `var(--surface, #fffdf7)` — neither `--border` nor
`--surface` is ever defined in `:root`, a genuine pre-existing bug (undefined
CSS custom property, silently falls back to no border) that this phase's
token migration will incidentally fix by construction, not as a special
carve-out.

There is **no dark-mode infrastructure in the live application at all**. The
only dark-mode code in the repository is inside `src/app/page.module.css`, an
unreferenced `create-next-app` boilerplate file that nothing imports — dead
code, not a foundation to build on. `next/font/google` loads `Geist` and
`Geist_Mono` in the root layout and exposes them as CSS variables, but
`globals.css`'s `body` rule hardcodes `font-family: Arial, Helvetica,
sans-serif`, so the fonts the app pays a loading cost for are not the fonts
users actually see. Icons are literal text glyphs (`?`, `!`, `M`) inside
CSS-styled circles — there is no icon library dependency.

Navigation is **not a single shell**. The authenticated root
(`src/app/app/layout.tsx`) renders one persistent top nav (wordmark, a
conditional "Platform health" link, the user's email as plain text, logout) —
not a sidebar. Inside the single workspace page
(`src/app/app/workspaces/[workspacePublicId]/page.tsx`), two independent,
nested CSS-grid "sidebars" exist: `WorkspaceSwitcher` (workspace list) and, one
level deeper, `ChatWorkspace`'s own internal `.conversation-sidebar`
(conversation history). There is no off-canvas/drawer pattern for mobile —
the one `@media (max-width: 800px)` block simply collapses both grids to
single-column stacks. Administration is not a route at all: it is a fixed,
top-to-bottom sequence of `<details>` disclosure widgets
(`WorkspaceAdministration`, `WorkspaceUsage`) concatenated onto the same
workspace page below `DocumentUploadPanel` and `DocumentAdministration`, with
`WorkspaceUsage` omitted entirely for `member`-role users and
`WorkspaceAdministration`'s member/invitation data fetched as `null` for
members server-side.

The chat interface (`ChatWorkspace.tsx`, ~420 lines, everything inlined in one
component) already implements substantially more of ADR-0024's state machine
than the visual reference implies: provisional streaming answer parts,
per-stage progress labels, citation disclosure with raw JSON provenance dumps,
retry of failed retryable runs, cancellation of in-flight runs, and a generic
reconnect-interruption message. It does **not** yet have a distinct UI for
timeout as opposed to ordinary failure, and clarification is not visually
distinguished from an ordinary completed answer — both are legitimate gaps
this phase closes, not net-new product behaviour.

`apps/web/src/lib/branding.test.ts` is a standing repository-wide guardrail:
it requires the literal string `"Dolved"` in `layout.tsx`, `page.tsx`,
`app/layout.tsx` and `AuthForm.tsx`, and rejects the legacy name
(`Make Time`/`maketime`) anywhere in the tracked repository, with a narrow,
named allowlist for immutable historical identifiers. This phase's shell
consolidation will move where the wordmark renders; the test's
`brandedSurfaces` list will need updating to match, but the underlying
guarantee (branding present, legacy name absent) is unchanged and must survive.

### The visual reference

The approved visual references are stored as stable, repository-relative
assets under `docs/assets/design/phase-21/` (not as absolute paths under a
user-specific `.codex`, Desktop or Pictures directory, which an earlier draft
of this ADR cited directly and which would not resolve for anyone else, or
even for the same user on a different machine). `docs/assets/design/phase-21/README.md`
records each file's SHA-256 checksum and states explicitly that these are
design/reference artifacts for this ADR and its implementation sessions, not
runtime production assets bundled into `apps/web`.

The approved mock-up (`docs/assets/design/phase-21/approved-chat-shell-dark.png`,
`sha256:e3d9591d2015e2a29c6573ff2597d914fe9dd550c6dad2c94831cbb362ed8fb0`)
shows: a true-black canvas; a lowercase, tightly-tracked, very bold `dolved`
wordmark; a workspace selector; an emerald "New conversation" button; a
sidebar list of `Search`, `Documents`, `Administration`; a "Recent" section of
conversation links with an active-state treatment; an account row at the
bottom (avatar, name, email); a message thread with avatar-labelled
`You`/`Dolved` turns, a numbered, evidence-cited answer, compact citation
chips (`[1]` `[2]`), an evidence/source card with a "View source" link, a
"Grounded in 4 sources" status line, and a bottom composer with attach/settings
icons and a send button. It also shows a bookmark icon, a history-clock icon,
a share icon, an overflow menu and thumbs-up/down reaction icons in the
conversation header and under the answer — **none of these five controls
correspond to anything the application actually implements**, and per the
governing instruction for this ADR they are read as style/placement reference
only, not as approved scope. Section "Chat and evidence presentation" below
states explicitly which mock-up controls are adopted and which are excluded.

The slate swatch reference
(`docs/assets/design/phase-21/approved-slate-palette.png`,
`sha256:9d20e1293f2ea803a8d7e7720938b483b3bdae97f1a21c270ac94e60e13dfdfa`) is
the exact `#1F2A35` / `#455766` / `#7F93A1` /`#C9D4DC` / `#F3F6F8` five-step
ramp already specified in the user's brief and is treated as authoritative
literal values, not inspiration to re-derive.

Two further references were inspected and are deliberately **not** adopted
wholesale. Neither is copied into this repository — see
`docs/assets/design/phase-21/README.md` for the full attribution record;
what follows is a summary:

- An "Apex Dashboard" admin-template screenshot shares this product's
  true-black canvas and emerald-accent instinct, and its sidebar's grouped,
  labelled sections and top command-bar placement are a reasonable
  structural precedent worth citing — but it is a chart-and-KPI-dense
  analytics dashboard with sparkline cards, donut charts and goal-progress
  bars fed by data this platform does not have. The user's brief explicitly
  excludes "dashboards or charts unsupported by real data"; this ADR does
  not adopt that visual density, none of its chart components are in scope,
  and Dolved must not reproduce this third party's branding, content or page
  composition verbatim. Its original source URL was not independently
  verified during this ADR's drafting; it is recorded honestly as a
  product-owner-supplied inspiration screenshot with source unavailable,
  rather than inventing an attribution.
- A "Coliner" font specimen screenshot is used as the wordmark's *stylistic*
  reference only — its bold, rounded-geometric, single-story letterforms and
  tight tracking. The referenced *typeface's* license (verified directly,
  see "Typography and wordmark" below) is personal-use-only under the
  1001Fonts Free-For-Personal license; commercial use requires a paid
  license from its foundry. This ADR therefore treats Coliner as a
  shape/character reference, not an adoptable asset, and recommends an
  open-licensed shortlist instead (section 4). The specimen screenshot's own
  source URL was likewise not independently verified; it is recorded
  honestly as source-unavailable rather than inventing an attribution.

## Decision

### 1. Technology and ownership

**Adopt Tailwind CSS v4** (CSS-first configuration via `@theme`, no
`tailwind.config.js` required) and **shadcn/ui with components generated into
and owned by the repository** (`apps/web/src/components/ui/`), not consumed
as an opaque published package. Both are verified, current-generation and
compatible with the repository's actual pinned versions: shadcn/ui's current
release line has full support for React 19 and Tailwind v4 (`data-slot`
attributes replacing `forwardRef`-based composition, native `@theme`/`@theme
inline` support), and Next.js 16 is a supported target for both. This is not
inherited from a tutorial written for Tailwind v3/React 18 — the specific
combination pinned in this repository was checked against current tooling
before this recommendation was made.

**Select Radix UI as the sole primitive foundation.** shadcn/ui's mainstream,
stable component registry is built on `@radix-ui/react-*` primitives
(accessible, unstyled, composable — dialog, dropdown-menu, popover, tooltip,
select, tabs, etc.). Base UI and React Aria variants exist in the wider
ecosystem but are not the default shadcn registry and would fragment the
accessibility/behaviour contract (focus trapping, ARIA wiring, keyboard
handling) across two different unstyled-primitive philosophies for no
functional gain here. One foundation, used consistently, is the decision.

**Lucide React is the sole general application icon set.** It is the icon
family shadcn/ui's own components are written against by convention, ships as
tree-shakeable individual React components, and matches the "Lucide-style
outline icons" the user has already approved. No second icon package (no
Heroicons, no react-icons, no bespoke SVG-glyph text as used today) is
introduced.

**Semantic design tokens are the single source of truth for color, spacing,
radius and typography**; no component may hardcode a literal hex/hsl value or
reach directly for a raw palette primitive. The exact Tailwind v4 plumbing —
which values live where, and how a plain CSS custom property becomes a
Tailwind utility class — is specified precisely in "Visual identity, palette
and token architecture" below, since Tailwind v4's utility generation is
namespace-driven (a bare `--surface` variable does not itself produce a
`bg-surface` utility) and an ADR that only gestured at token names without
fixing this would not be implementation-ready. In outline: shadcn components
and all hand-written components alike consume Tailwind-generated utilities
such as `bg-surface-raised`, `text-foreground-muted` and `border-border`,
never `#101316` directly. This directly retires the twenty-plus raw color
literals and the two undefined `--border`/`--surface` references found in the
current `globals.css`.

**shadcn/ui is a starting point, not the destination.** Its default components
ship with a generic visual identity (its own default radius, shadow and
spacing choices, a generic blue/neutral palette) that is explicitly the
"generic AI-product styling" the user wants to move away from. Every shadcn
primitive brought into the repository is themed against Dolved's tokens before
it is used anywhere in the product — a raw, unthemed shadcn button or dialog
must never ship. Ownership of the generated component source (rather than an
npm dependency) is precisely what makes this possible: the components are
Dolved's own code from the moment they land, editable in place, not
overridden through a theming API layered on top of someone else's package.

**No parallel styling system once migration completes**, and **no
backend/API/domain redesign to accommodate the visual implementation** — this
phase changes how existing data is rendered, never what data exists or how it
is authorized.

### 2. Visual identity, palette and token architecture

Four distinct layers exist, and every component is written against the
correct one:

1. **Raw palette primitives** — the five-step deep-slate ramp and the base
   brand/status hex values. Defined once; never consumed directly by a
   component (no component references `--palette-slate-700` or a literal
   hex), and — see "The raw-palette boundary is literal" below — **never
   bridged into a general-purpose component-facing utility either**. Their
   only consumer is Layer 2.
2. **Semantic theme tokens** — the values below (`--background`, `--surface`,
   `--foreground-muted`, `--brand`, `--ring`, `--status-success`, …), each
   defined twice: once under `:root` (the light-theme values, the CSS
   baseline) and once under `.dark` (dark-theme overrides). `.dark` is the
   class `next-themes` applies to `<html>` for the dark theme (see section 3);
   which selector lists which theme's values is a CSS-authoring convention
   only and is unrelated to which theme is active by default — dark remains
   the default active theme per section 3 regardless of `:root` holding the
   light values.
3. **Tailwind namespace bridge** — a single `@theme inline` block mapping the
   semantic tokens above into the Tailwind-recognized `--color-*`, `--font-*`
   and `--radius-*` namespaces, so utility classes actually generate. This is
   the layer the original draft of this ADR under-specified: Tailwind v4's
   utility generation is namespace-driven, so a plain `--surface` custom
   property does not itself produce a `bg-surface` utility — only a
   `--color-surface` entry in `@theme` (or `@theme inline`) does. The bridge
   below is a **complete base contract** for shadcn/ui's standard component
   vocabulary and its Sidebar primitive specifically, not a partial mapping —
   every slot shadcn's generated component source is written to expect
   resolves to a Dolved semantic token; none is left undefined for a browser
   default to silently fill in.
4. **Component variants** — shadcn component source and Dolved-authored
   components (`Button`, `StatusBadge`, `Heading`, etc.) consume only the
   Tailwind utilities Layer 3 generates, or a bounded semantic prop
   (`<StatusBadge status="success">`) that internally maps to those utilities.
   No component reaches into Layer 1 or writes a raw hex/hsl value; see
   section 9 for the component inventory this layer covers.

**The raw-palette boundary is literal, not a guideline with an exception.**
Layer 1 primitives are never consumed directly by components, full stop —
including via a general-purpose bridged utility. An earlier draft of this
ADR bridged the five slate-ramp steps directly into component-usable
utilities (`--color-slate-900`…`--color-slate-50`) as a "narrow exception."
That exception is withdrawn: a component reaching for `bg-slate-700` is
indistinguishable, in practice, from a component reaching for a raw hex — it
still bypasses the semantic layer, and every future contributor who sees a
`slate-*` utility available will reach for it exactly where a semantic token
should have been used instead. The slate ramp remains Layer 1 input used
**only** to define the Layer 2 semantic tokens below (`--foreground-muted`,
`--border`, and so on already derive from specific ramp steps); it is not
separately exposed. If a genuine structural role turns up during
implementation that the semantic vocabulary below doesn't yet name — a
divider that needs to sit between `--border` and `--surface-raised` in
weight, for instance — the correct fix is to add a new named semantic token
for that role (`--divider`, or whatever the role is called), not to reach
past Layer 2 for a raw ramp step.

**Layer 2 — semantic theme tokens, `:root` (light theme values):**

| Token | Value | Role |
|---|---|---|
| `--background` | `#F3F6F8` | root/canvas |
| `--sidebar` | `#FFFFFF` or near-white | sidebar/chrome |
| `--surface` | white/near-white, one step below sidebar | raised surface (cards, panels) |
| `--surface-raised` | a further step up in elevation from `--surface` | elevated surface (popovers, dialogs) |
| `--surface-hover` | a subtle step between `--surface` and `--surface-raised`, exact value left to R21-S01 tuning | hover/active background for menu items, list rows and similar non-brand-colored interactive feedback (see the shadcn `--accent` note below) |
| `--border` | a step of the slate ramp appropriate for light-mode contrast (not the dark theme's `#242B31`) | borders/separators |
| `--foreground` | `#1F2A35` | primary text |
| `--foreground-muted` | `#455766` | secondary text |
| `--foreground-subtle` / `--foreground-faint` | further slate-ramp steps, lighter than `--foreground-muted` | tertiary text/placeholders, disabled/least-emphasis text |
| `--brand` | the same brand relationship (`#007A57` family), contrast-checked against the light surfaces independently — dark-theme contrast passing does not imply light-theme contrast passes | primary action / brand accent |
| `--brand-foreground` | a foreground tuned for contrast against `--brand` in this theme | text/icon color on `--brand` |
| `--ring` | equal to `--brand` in this theme, a common and defensible starting point for a visible, on-brand focus indicator; independently tunable if contrast against `--background`/`--surface` demands it | keyboard-focus ring color |

**Layer 2 — semantic theme tokens, `.dark` (dark theme overrides, the default active theme):**

| Token | Value | Role |
|---|---|---|
| `--background` | `#000000` | root/canvas |
| `--sidebar` | `#050607` | sidebar/chrome |
| `--surface` | `#101316` | raised surface (cards, panels) |
| `--surface-raised` | `#15191D` | elevated surface (popovers, dialogs) |
| `--surface-hover` | a subtle step between `--surface` and `--surface-raised`, exact value left to R21-S01 tuning | hover/active background for menu items, list rows and similar non-brand-colored interactive feedback |
| `--border` | `#242B31` | borders/separators |
| `--foreground` | `#F3F6F8` | primary text |
| `--foreground-muted` | `#C9D4DC` | secondary text |
| `--foreground-subtle` | `#9EACB7` | tertiary text/placeholders |
| `--foreground-faint` | `#7F93A1` | disabled/least-emphasis text |
| `--brand` | `#007A57` | primary action / brand accent (restrained dark emerald) |
| `--brand-foreground` | `#F3F6F8` | text/icon color on `--brand` |
| `--ring` | equal to `--brand` in this theme, per the same rationale as the light theme's `--ring` row above | keyboard-focus ring color |

The approved five-step deep-slate ramp (`#1F2A35` / `#455766` / `#7F93A1` /
`#C9D4DC` / `#F3F6F8`) underlies both tables above as Layer 1 primitives —
`--foreground`, `--foreground-muted`, `--foreground-subtle` and
`--foreground-faint` are each defined *from* a specific ramp step. It is not
separately exposed as a component-facing utility; see "The raw-palette
boundary is literal" above.

**Why `--brand`, not `--accent`, is Dolved's own token name.** shadcn's
generated component source already uses `--accent`/`--accent-foreground` for
a *different* concept than "brand color": a subtle background used for
hovered/focused menu items, selected list rows and similar non-primary
interactive feedback — not the main call-to-action color. If Dolved's brand
emerald were also named `--accent`, the Tailwind bridge would face a genuine
naming collision (which "`--accent`" does a component mean?), and the
straightforward-looking fix — pointing shadcn's `--color-accent` at Dolved's
brand green — would make every hovered dropdown item, selected tab and
focused menu row render emerald-tinted, which is not the intended design.
This ADR avoids the collision by naming Dolved's brand token `--brand` /
`--brand-foreground` throughout, reserving Tailwind's `--color-accent` for
shadcn's original hover-highlight role, bridged to the new `--surface-hover`
token above instead. Dolved's brand color bridges to shadcn's `--primary`/
`--primary-foreground` namespace instead, which is what shadcn's own `Button`
"default" variant and other primary-action components already consume.

The reference image's brighter, gradient-lit green button is explicitly **not**
a literal requirement — production controls use a flat, accessible `#007A57`.

**Hover, active and disabled states are computed at the component-variant
layer, not stored as separate named tokens.** An earlier draft of this ADR
referenced `--brand-hover`, `--brand-active` and `--brand-disabled` in prose
without ever defining them as resolvable tokens in the bridge — exactly the
"listing a token the architecture can't actually resolve" problem to avoid.
Rather than adding three more named tokens per color role (which would mean
`--brand-hover`, `--secondary-hover`, `--destructive-hover`, `--brand-active`,
`--secondary-active`… multiplying without bound as roles grow), every
interactive state derives from its single base semantic token using
Tailwind v4's built-in opacity-modifier syntax on the generated `--color-*`
utility (`bg-primary/90` for hover, `bg-primary/80` for pressed) or, where a
non-opacity shift is genuinely needed, `color-mix()` computed inline in the
component's own bounded CSS (`hover:bg-[color-mix(in_oklab,var(--brand)_92%,black)]`)
— one consistent strategy, chosen once, applied by every component variant in
section 9. `--ring` is the one exception that remains its own real Layer 2/3
token (see the tables above and the bridge below): a focus indicator is an
independent role from the color it happens to currently match, since
accessibility requirements can force it to diverge from `--brand` even where
the two start out equal. Disabled controls use standard reduced-opacity
styling on the same base token (`disabled:opacity-50` or equivalent) —
still no separate `--brand-disabled` token — combined with `disabled`/
`aria-disabled` semantics so the state is conveyed structurally, not only
visually.

**Contrast is verified per pairing, not assumed to transfer between
pairings.** `#007A57` participates in several genuinely different contrast
checks, each independently required before R21-S01 is accepted, since
passing one does not imply passing another:

- `--brand-foreground` text/icons rendered *on* a `--brand` button background
  must meet the applicable WCAG 2.2 AA text-contrast requirement for their
  actual rendered size (4.5:1 normal text, 3:1 large text/bold ≥19px).
- Brand-colored **non-text** controls — a filled icon button, a focus
  indicator, an active-state rail or selection indicator — must meet the
  3:1 non-text/UI-component contrast requirement against the background
  immediately adjacent to them.
- If brand emerald is ever used as normal-sized **link text** directly on a
  `--background`/`--surface` color (rather than as a button fill), that
  specific text-foreground/page-background pairing must independently meet
  the 4.5:1 normal-text requirement — passing the button-background check
  above does not prove this reversed pairing passes, since the two checks
  compare `--brand` against different roles (foreground-on-brand vs.
  brand-as-foreground-on-background).
- Disabled controls follow WCAG 2.2's exemption for inactive user-interface
  components (no numeric contrast ratio is mandated for a control that
  cannot currently be operated) but must still remain visibly and
  semantically distinguishable as disabled — via the `opacity`/`disabled`
  treatment above, never via a contrast level so low the control reads as
  simply missing.

If any literal value fails its specific required check, the token is
adjusted (lightened, or given a distinct value per pairing if one pairing
fails and another doesn't) rather than the requirement waived — the semantic
role (`brand`) stays stable even if its exact hex is tuned once during
implementation. `#007A57` remains the approved starting point throughout.

Both themes share the same *semantic hierarchy* (background → sidebar →
surface → raised surface, foreground → muted → subtle) so a component never
needs theme-conditional logic — it just references the token and the active
theme supplies the value.

**Layer 3 — the Tailwind namespace bridge**, one `@theme inline` block (using
`inline`, not a plain `@theme`, specifically because the mapped values must
resolve against whichever of `:root`/`.dark` is active at render time, not be
baked to a single static value at build time):

```css
@theme inline {
  /* Structural surfaces */
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-border: var(--border);
  --color-input: var(--border);
  --color-ring: var(--ring);

  /* shadcn's complete standard base contract, bridged onto Dolved's semantic
     tokens so shadcn-generated component source and hand-written Dolved
     components resolve through the same mapping rather than two parallel
     systems. Every slot shadcn's standard components reference is present —
     none is left for a browser default to silently fill in. */
  --color-card: var(--surface);
  --color-card-foreground: var(--foreground);
  --color-popover: var(--surface-raised);
  --color-popover-foreground: var(--foreground);
  --color-primary: var(--brand);
  --color-primary-foreground: var(--brand-foreground);
  --color-secondary: var(--surface-raised);
  --color-secondary-foreground: var(--foreground);
  --color-muted: var(--surface);
  --color-muted-foreground: var(--foreground-muted);
  --color-accent: var(--surface-hover);
  --color-accent-foreground: var(--foreground);
  --color-destructive: var(--status-destructive);
  --color-destructive-foreground: var(--status-destructive-foreground);

  /* shadcn's Sidebar primitive contract — this ADR specifically selects the
     Sidebar component (section 5), so its full token set is mapped rather
     than left to inherit the base contract's defaults by accident. Reusing
     Dolved's existing semantic roles (no new primitives introduced). */
  --color-sidebar: var(--sidebar);
  --color-sidebar-foreground: var(--foreground);
  --color-sidebar-primary: var(--brand);
  --color-sidebar-primary-foreground: var(--brand-foreground);
  --color-sidebar-accent: var(--surface-hover);
  --color-sidebar-accent-foreground: var(--foreground);
  --color-sidebar-border: var(--border);
  --color-sidebar-ring: var(--ring);

  /* Dolved's own extended semantic vocabulary, for roles shadcn's default
     slot set doesn't name */
  --color-surface: var(--surface);
  --color-surface-raised: var(--surface-raised);
  --color-surface-hover: var(--surface-hover);
  --color-foreground-muted: var(--foreground-muted);
  --color-foreground-subtle: var(--foreground-subtle);
  --color-foreground-faint: var(--foreground-faint);
  --color-brand: var(--brand);
  --color-brand-foreground: var(--brand-foreground);
  --color-status-success: var(--status-success);
  --color-status-success-foreground: var(--status-success-foreground);
  --color-status-warning: var(--status-warning);
  --color-status-warning-foreground: var(--status-warning-foreground);
  --color-status-destructive: var(--status-destructive);
  --color-status-destructive-foreground: var(--status-destructive-foreground);
  --color-status-info: var(--status-info);
  --color-status-info-foreground: var(--status-info-foreground);
  --color-status-pending: var(--status-pending);
  --color-status-pending-foreground: var(--status-pending-foreground);
  --color-status-unavailable: var(--status-unavailable);
  --color-status-unavailable-foreground: var(--status-unavailable-foreground);

  /* Typography */
  --font-sans: var(--font-source-sans-3), ui-sans-serif, system-ui,
    -apple-system, "Segoe UI", Roboto, sans-serif;
  --font-display: var(--font-wordmark), ui-sans-serif, sans-serif;

  /* Radius, derived from one primitive per shadcn convention */
  --radius-sm: calc(var(--radius) - 4px);
  --radius-md: calc(var(--radius) - 2px);
  --radius-lg: var(--radius);
  --radius-xl: calc(var(--radius) + 4px);
}
```

Note deliberately absent: no `--color-slate-*` entries — per "The raw-palette
boundary is literal" above, the slate ramp stops at Layer 2 and is never
bridged into a component-facing utility. `--color-secondary`/
`--color-secondary-foreground` (missing from the ADR's previous draft of this
bridge) reuse the existing `--surface-raised`/`--foreground` semantic tokens
rather than introducing a new primitive — a secondary button is visually a
raised surface with ordinary foreground text, distinct from both `--primary`
(brand-filled) and `--card`/`--popover` (non-interactive surfaces). The
Sidebar-specific block is this ADR's best-documented mapping of shadcn's
publicly known Sidebar variable contract; R21-S01 verifies it against the
actual generated `components/ui/sidebar.tsx` source once installed, per the
token-completeness acceptance requirement in "Verification and acceptance"
below — if the installed component references a slot this bridge missed, that
slot is added before the component ships, not left to a default.

With this bridge, `--color-border: var(--border)` is what makes the
`border-border` utility exist and resolve correctly in both themes —
`border-default`, used loosely in an earlier draft of this ADR, is not a name
Tailwind v4 would generate from any token defined here and has been corrected
throughout. Typography's size scale, spacing and breakpoints are **not**
given a bespoke bridge — Tailwind v4's built-in `--text-*`, `--spacing` and
`--breakpoint-*` primitives are adopted unmodified (see sections 4 and 11);
inventing a parallel scale where the defaults already suffice would itself be
the "second parallel styling system" this ADR elsewhere warns against.
`--radius` (the one base primitive the bridge derives from) is defined once
in Layer 1/2 CSS, outside the `@theme inline` block, exactly like every other
primitive and semantic token above.

Status colors are **independent tokens**, never aliases of `--brand`:
`--status-success`, `--status-warning`, `--status-destructive`,
`--status-info`, `--status-pending`, `--status-unavailable`. Reusing the
brand emerald as "success" would make every primary button read as a status
signal and vice versa; keeping them distinct (even if the success token
happens to be a nearby green) preserves the distinction the user asked for.
**Each status token has its own paired foreground** —
`--status-success-foreground`, `--status-warning-foreground`,
`--status-destructive-foreground`, `--status-info-foreground`,
`--status-pending-foreground`, `--status-unavailable-foreground` — defined
per theme alongside its base color and bridged above. A status badge or
notice always resolves its text/icon color from its own paired foreground
token, never from `--brand-foreground` merely because the two might currently
look similar; brand and status roles remain independently tunable, including
by a future change to only one of them. Every status token also ships with a
paired icon (Lucide `CheckCircle2`, `AlertTriangle`, `XCircle`, `Info`,
`Clock`, `HelpCircle` or equivalent) and a text label — color is never the
only channel carrying status meaning, satisfying both the "no colour-only
meaning" accessibility requirement and the "status meaning must not depend on
colour alone" instruction directly.

Contrast verification is a concrete, repeatable check, not a design opinion:
every foreground/background token pairing actually used for body text, muted
text, disabled controls, badge text-on-fill, and table content is checked
against WCAG 2.2 AA (4.5:1 normal text, 3:1 large text/UI components) in
**both** themes as part of R21-S04 acceptance, with results recorded as
evidence (see "Verification and acceptance").

### 3. Theme behaviour

Adopt `next-themes` (the shadcn/Next.js-recommended theme library), evaluated
directly against this repository's Next.js 16/React 19/App Router pinning and
found compatible — it is class-attribute driven (`class` on `<html>`),
framework-agnostic about rendering strategy, and is the mechanism shadcn's own
theming documentation is written against, so adopting it keeps the component
layer and the theme layer speaking the same convention rather than requiring
a hand-rolled bridge.

Decisions, not just library selection:

- **Default theme is `dark`** on first visit (no stored preference yet).
- **Explicit toggle**, not automatic OS-preference inference as the default —
  the user asked for "a light/dark toggle with remembered preference," not a
  `prefers-color-scheme` auto-switch. `next-themes`' system-mode option is
  therefore **not enabled** (`enableSystem={false}`); the toggle is a
  deliberate two-state (light/dark) control, matching the explicit
  instruction not to introduce a third system-mode choice without concrete
  reason.
- **Remembered preference** persists via `next-themes`' `localStorage`
  mechanism, read before paint.
- **No flash of incorrect theme.** `next-themes` requires
  `suppressHydrationWarning` on `<html>` and an inline, blocking script
  (which it injects automatically) that sets the theme class before React
  hydrates — this is the standard, verified mechanism for avoiding a
  light-flash on a dark-default app and is adopted as-is rather than
  reimplemented.
- Correct `color-scheme` (`color-scheme: dark` / `color-scheme: light` on
  `<html>`) is set alongside the theme class so native form controls,
  scrollbars and UA-drawn chrome match the active theme, not just
  app-rendered surfaces.
- The toggle lives in the persistent account/settings area of the shell (see
  section 5) and is additionally available on authentication pages, since
  those pages render before any authenticated shell exists.

### 4. Typography and wordmark

Two distinct typographic roles, deliberately not the same font:

**Interface typography — Source Sans 3.** Verified directly: SIL Open Font
License 1.1 (free for commercial and non-commercial use, redistributable,
embeddable), hosted on Google Fonts, ships as a variable font (weight axis),
and is loadable through `next/font/google` with no runtime `<link>` tag, no
FOUT/layout-shift risk (Next's font loader self-hosts the font file and
reserves layout metrics at build time), and a defined `font-display`
strategy. It is a highly readable humanist sans with the Calibri-adjacent
warmth and open counters the user described, without depending on Calibri
itself being installed — Calibri is not open-licensed, is not universally
available outside Windows/Office installations, and cannot be a web delivery
dependency. Fallback stack: `"Source Sans 3", ui-sans-serif, system-ui,
-apple-system, "Segoe UI", Roboto, sans-serif`. Tabular numerals (Source Sans
3 supports the `tnum` OpenType feature) are applied to operational/usage
figures — document sizes, counts, latency numbers, usage tables — so digits
align in columns; proportional figures remain the default for prose.

**Product wordmark — Sora ExtraBold is the leading R21-S01 implementation
candidate.** The approved visual reference's wordmark weight and character
(very bold, geometric, rounded, single-story forms, tight tracking) was
specifically modelled on the "Coliner" specimen the user supplied. Coliner
itself is licensed under the 1001Fonts Free-For-Personal-Use terms —
**verified directly, not assumed** — and is not usable in a shipped commercial
product without purchasing a commercial or corporate license from its
foundry (Jetsmax). This ADR does not recommend depending on it. Instead, of
the open-license (Google Fonts / SIL OFL) shortlist evaluated, **Sora**
(ExtraBold/Black, variable) is the leading candidate: its geometric, rounded,
single-story character sits closest among the shortlist to Coliner's rendered
weight and form, it is OFL-licensed, available via `next/font/google`, and
supports letter-spacing tight enough to match the approved reference.
**Space Grotesk** (Bold) and **Archivo** (Black) remain fallback candidates,
used only if the rendered Sora wordmark materially misses the approved
reference at R21-S01's live visual review. Selecting a leading candidate now
does not make the final weight/tracking pick an architectural question —
it remains a visual-tuning decision explicitly left to R21-S01's live
verification, not an unresolved blocker. The licensing constraint (must be
open, self-hostable, commercially usable without a purchased license, no
Coliner dependency) is fixed now regardless of which of the three ships, so
implementation cannot accidentally reach for Coliner or an equivalently
restricted commercial face.

**For V1, the wordmark implementation is live text — unambiguously, not one
option among several.** It is real DOM text (`dolved`) styled with the
selected typeface, weight and letter-spacing via CSS, colored through the
same semantic tokens as everything else (`text-foreground` or `text-brand`
depending on placement), never a raster screenshot or PNG export, and never
text converted to SVG path outlines. An earlier draft of this ADR listed
"inline SVG built from live text/paths" as an equivalent alternative; it is
not — text converted to SVG `<path>` outlines is no longer real text: it is
not selectable, not copyable, and is not read as text by assistive
technology in the way live DOM text reliably is. Live text is the only
choice that is simultaneously crisp at every zoom level, themeable through
color tokens, selectable, and correctly announced by a screen reader without
extra `aria-label` scaffolding — because it doesn't need any; it already is
the string it needs to announce. A future, separate decorative icon/logo
mark, distinct from the text wordmark, may use SVG if one is introduced in a
later phase, governed by the redundant-versus-sole accessible-naming rule
below — but no such mark is required or introduced by this ADR. V1's
wordmark is text, exclusively.

**The wordmark glyph and the product's proper name are two distinct casing
requirements, not one.** The *visual wordmark* — the styled brand mark
rendered by the shell, the auth pages and anywhere else the brand mark itself
appears — is always the exact lowercase string `dolved`, no trailing period.
The *product's proper name*, used in prose, page `<title>` metadata, email
copy, error messages and any accessible name that stands in for the product
(as distinct from the wordmark glyph itself), remains the capitalized
`Dolved`, exactly as `apps/web/src/app/layout.tsx`'s `metadata.title`
template already renders it today (`"%s · Dolved"`). Neither requirement
dilutes the other: a page correctly renders a lowercase `dolved` wordmark in
its header while its `<title>` reads, for example, "Overview · Dolved."

This corrects a conflation in the ADR's own first draft, which described the
lowercase wordmark as "matching the `branding.test.ts` guardrail's literal
string requirement" — that is not accurate. The existing test's literal-string
assertion checks source files for the capitalized `"Dolved"`, which is a
**different** requirement from the wordmark glyph's lowercase rendering, not
the same requirement restated. The corrected test boundary, to be implemented
alongside the R21-S01 shell consolidation:

- assert that the shared wordmark component's rendered output contains the
  exact visible text `dolved` (lowercase, no trailing period);
- assert that page metadata (`metadata.title`) and any accessible name
  standing in for the product retain the proper name `Dolved`;
- continue rejecting the historical legacy brand (`Make Time`/`maketime`)
  repository-wide, unchanged from today;
- follow the consolidated shared shell/wordmark/`AuthForm` components R21-S01
  introduces, rather than requiring the same literal string duplicated across
  every individual page source file the current `brandedSurfaces` list
  enumerates — once the wordmark renders from one shared component, asserting
  against that one component is the correct, non-redundant check, not a
  weakening of it.

This is a correction to the test's *design* precision, not a loosening of
what it guarantees: legacy-name rejection stays repository-wide and
unconditional, and both the lowercase-wordmark and proper-name requirements
remain independently enforced — just against the surfaces that actually carry
each one, instead of one conflated string check.

If a purely decorative brand graphic (an icon-only mark, independent of the
text wordmark) is introduced in a later phase, it follows the ordinary
accessible-naming rule for redundant versus sole content: **where it appears
alongside the live wordmark text**, it is `aria-hidden="true"` — the adjacent
text already carries the accessible name, so the graphic would otherwise
announce as a redundant, unlabelled duplicate; **where it stands alone as the
sole brand mark** with no adjacent text (for example a favicon-scale mark),
it carries its own accessible name, `"Dolved"` — the proper name, not the
lowercase wordmark string, since an accessible name is prose, not a
transcription of the visual glyph. A decorative graphic never carries a
second, competing accessible name alongside adjacent live wordmark text.

Typographic hierarchy (heading/body/label/caption) is defined as a bounded
Tailwind type scale (a fixed set of `text-*` utility combinations mapped to
semantic use — page title, section heading, body, label, caption, code/mono)
rather than ad hoc per-component font sizing, closing the gap where today's
`globals.css` hand-tunes `clamp()` font sizes per rule with no shared scale.

### 5. One adaptive application shell

One responsive shell (`AppShell`) wraps chat, documents, workspace
administration and platform operations, replacing today's two independent,
nested sidebar grids (`WorkspaceSwitcher` + `ChatWorkspace`'s internal
`.conversation-sidebar`) and the separate top-nav-only layout used for
`/app/operations`.

**Desktop**: one left sidebar, expanded by default at desktop widths,
collapsible to an icon rail via an accessible, keyboard-operable trigger
(button with `aria-expanded`, `aria-controls`) — matching the reference
image's collapse chevron. Persistent contents, top to bottom: the `dolved`
wordmark; a workspace selector; the stable primary navigation region; a
scrollable contextual region; account controls pinned at the bottom (avatar,
name, email, theme toggle, logout) — both regions defined precisely below.
Main content is inset with its own bounded header (page title + page-specific
actions), never edge-to-edge with the sidebar. There is exactly one permanent
left sidebar in the DOM at any time — the current app's second, nested
conversation sidebar becomes the sidebar's own "recent conversations" region
in chat context (see below), not a second independent column.

**Mobile**: the same navigation renders as an off-canvas sheet/drawer (a
themed shadcn `Sheet`, built on Radix `Dialog` primitives, so focus-trapping,
`Escape`-to-close and return-focus-to-trigger are inherited behaviour, not
hand-rolled). Main content never sits squeezed beside a desktop-width sidebar
on a small viewport — it is full-width with the drawer overlaying, not
pushing, content. Touch targets meet a 44×44px minimum baseline.

**Stable primary region versus contextual region** — not "normal context"
versus "administration context" each carrying their own independent
navigation-item set, since `Documents` cannot simultaneously be an ordinary
top-level destination and an administration subsection without producing
ambiguous active-state (which region "owns" the highlighted item when the
same route is reachable from two places in the shell). The shell has exactly
one navigation model with two regions instead:

*Stable primary region* — rendered identically regardless of whether the user
is in chat or administration context, each item subject to its own
authorization gate: the `dolved` wordmark; the workspace selector; "New
conversation"; `Documents`; `Administration` (present only if the current
membership grants any administration capability per ADR-0025 — hidden, not
shown disabled, for a `member` with no administrative capability at all,
since a `member` cannot even view the member directory today); `Platform
operations` (present only for users with live platform-administrator access
per ADR-0026, rendered as a **visually distinct** item within the stable
region, never nested under `Administration`, so it never reads as ordinary
workspace administration — matching ADR-0026's explicit statement that
platform administration is a separate authority plane from workspace roles in
both directions). Account controls (avatar, name, email, theme toggle,
logout) are also stable, pinned at the shell's bottom regardless of context.

*Contextual region* — the one part of the shell whose *contents* (not its
existence) change by destination, rendered below the stable primary region:
in **chat/conversation context**, a "Recent" list of conversation links
(replacing `ChatWorkspace`'s current internal sidebar, same active-state
semantics — `aria-current="page"` plus a visual active treatment); in
**administration context**, `Administration overview`; `People & roles`;
`Invitations`; `Usage`.

**`Documents` is exclusively a stable-primary-region item.** It is never
repeated inside the contextual region's administration list — there is one
`Documents` navigation entry, one route, and therefore one unambiguous
active-state source regardless of which context the user is currently in.
Clicking `Documents` while inside administration context does not "enter an
administration Documents subsection"; it navigates to the same top-level
`Documents` destination it always does, and the shell's active-state
indicator moves to the stable-region `Documents` item exactly as it would
from chat context. If administration's overview needs to reference document
counts or failures, it renders a summary figure and a deep link to the
top-level `Documents` destination — never a second `Documents` list with its
own, subtly different content. This is the precise, stable resolution to
"one destination, not two" from section 6: not just one *route*, but one
*navigation entry*, living in one *region*.

Entering administration context is a navigation to the first administration
destination (`Administration overview`), not a modal or in-place expansion,
with a "Back to chat" affordance in the contextual region for returning to
the workspace's chat context — see section 6 for the full administration
information architecture.

**Route hierarchy and ownership.** An earlier draft of this ADR described the
shell's regions and destinations without fixing the concrete route tree
underneath them, leaving R21-S01/R21-S03 to invent it. The route hierarchy is
fixed here instead:

| Route | Destination |
|---|---|
| `/app/workspaces/{workspace}` | Workspace chat landing / new-conversation state |
| `/app/workspaces/{workspace}/conversations/{conversation}` | A selected durable conversation |
| `/app/workspaces/{workspace}/documents` | The single `Documents` destination (section 6) |
| `/app/workspaces/{workspace}/documents/{document}` | A single document's detail/source view (section 7) |
| `/app/workspaces/{workspace}/administration` | `Administration overview` |
| `/app/workspaces/{workspace}/administration/people` | `People & roles` |
| `/app/workspaces/{workspace}/administration/invitations` | `Invitations` |
| `/app/workspaces/{workspace}/administration/usage` | `Usage` |
| `/app/platform/operations` | Platform Operations — an explicit platform-level prefix outside the workspace-administration hierarchy entirely, protected by platform-administrator authority per ADR-0026, not by workspace role. This renames today's `/app/operations` for the same reason `Platform operations` is never nested under `Administration` in the shell (section 5, above): the URL hierarchy should say the same thing the navigation already does. |

The ownership rule this hierarchy establishes: **the route, not client-only
component state, is the authoritative owner of the currently-selected
workspace and the currently-selected durable conversation.** Concretely:

- The sidebar's "Recent" conversation list (section 5, above) renders real
  `<Link>` entries to `/app/workspaces/{workspace}/conversations/{conversation}`,
  never buttons that only mutate in-memory state — so a conversation can be
  refreshed, bookmarked, and deep-linked, and ordinary browser back/forward
  navigation moves between conversations exactly as it would on any other
  routed page. Route-aware active state and `aria-current` (section 5's
  "Route-aware active state" paragraph, below) derive from the current
  route's `conversation` segment, not from a separately-tracked "selected
  conversation" variable that could drift out of sync with the URL.
- **New-conversation navigation.** Creating a new conversation begins on the
  workspace chat landing route (`/app/workspaces/{workspace}`, no
  `conversation` segment yet). Once the durable conversation identity exists
  server-side, the client navigates via `router.replace` (not `push`) to that
  conversation's canonical URL — `replace`, specifically, so the landing
  state does not remain in browser history as a distinct back-button
  destination that could invite a duplicate submission if the user
  navigated back to it and resubmitted. The exact moment the durable
  identity is created (on the "New conversation" action itself vs. on first
  message submission) is an R21-S03 implementation detail; the navigation
  *mechanic* — landing route first, `replace` to the canonical conversation
  URL once an identity exists, never a second POST triggered by that
  navigation — is fixed by this ADR.
- **Switching conversations must not disturb an independently running
  `GenerationRun`.** ADR-0024 already guarantees connection-independent
  execution and a resumable SSE projection — a run in flight continues
  server-side regardless of which conversation route is currently mounted.
  Navigating away from and back to a conversation with an in-flight run must
  **reconnect to that same run's stream** (keyed by the run's own identity,
  correlated with the conversation route param), never start, cancel or
  duplicate a run as a side effect of navigation. This is a correctness
  requirement on how the streaming subscription is keyed in R21-S03's
  implementation, not a new guarantee ADR-0024 doesn't already make — this
  ADR is only making explicit that route-driven navigation must not
  accidentally violate it.
- **Invalid, deleted, inaccessible or cross-workspace conversation and
  document identifiers fail through the existing tenant-safe not-found
  behaviour** — the same `404`, not `403`, concealment rule ADR-0006
  establishes ("cross-tenant requests return `404`... so a workspace's
  existence is not revealed to a party without access to it"), already
  implemented today via `app/app/workspaces/[workspacePublicId]/not-found.tsx`
  and extended by this ADR to the new `conversations/{conversation}` and
  `documents/{document}` dynamic segments on exactly the same terms — never a
  distinguishable error that would disclose whether a given identifier
  exists in a workspace the requester cannot access.
- **`ChatWorkspace` may remain a presentation/orchestration component, but it
  is no longer the authoritative owner of durable conversation selection.**
  The selected conversation's identity comes from the route (the page
  component reads the `conversation` route param and passes it down),
  exactly like any other routed data-fetching component; `ChatWorkspace`'s
  own local state is scoped to genuinely client-local concerns — composer
  draft text, in-flight streaming/provisional state — not to "which
  conversation is currently shown," which the URL already answers.
- **No conversation branching.** This route hierarchy adds no
  branch-selection affordance and does not touch ADR-0024's reserved future
  branching seam; a conversation route resolves to exactly one linear
  conversation, unchanged from ADR-0024's accepted model.

**Not introduced**: Search, bookmarks, reactions (thumbs up/down), sharing, or
a standalone conversation-history icon-button distinct from the sidebar's own
recent-conversations list — all present in the mock-up's chrome, none backed
by real application behaviour today. Where the reference shows one of these,
this phase either omits the control entirely or, if omitting it would create
an obviously broken-looking gap in an otherwise-faithful header
reconstruction (for example the header's icon cluster), the control is left
out of the built interface rather than rendered disabled-with-a-tooltip,
because "coming soon" chrome for features with no committed implementation
date reads as clutter, not honesty. If a future phase implements one of these
capabilities, the shell has room reserved for it structurally (a header
action-icon region already exists for the answer's citation/evidence
actions), but no dead affordance ships in R21.

**Route-aware active state, landmarks and role-aware visibility**: navigation
items use semantic `<nav>` landmarks with `aria-label`s distinguishing
"Primary" from "Account", active-route detection drives `aria-current="page"`
plus a visual treatment (not color alone — an accent-colored left rail/
indicator plus bold weight), and every icon-only control (collapse trigger,
mobile menu trigger) carries an accessible name via `aria-label` or adjacent
visually-hidden text. **Hidden navigation is a usability convenience only.**
Every route it points to still performs its own server-side authorization
check independently (as `/app/operations` already does today, redirecting on
`unauthorized`/`forbidden`) — the shell hiding a link is not a security
control and this ADR does not treat it as one, consistent with ADR-0006's
"hidden convenience must never carry security meaning."

### 6. Administration information architecture

Administration becomes route-backed, addressable sections instead of a fixed
stack of `<details>` widgets, built strictly against ADR-0025's real
capability model — no simplified or invented permission set. The concrete
routes (`/app/workspaces/{workspace}/administration`,
`.../administration/people`, `.../administration/invitations`,
`.../administration/usage`) are fixed in section 5's route hierarchy table,
above.

Structure: `Administration overview` (the currently-missing summary view —
workspace name, role, and member/document/invitation counts at a glance,
composed from the same list/count data the `People & roles`, `Invitations`
and top-level `Documents` destinations already fetch — not a new analytics
endpoint — with a deep link to the top-level `Documents` destination for
anything document-related, replacing today's bare welcome heading with
something that actually orients an admin); `People & roles` (member
directory, promote/demote, ownership transfer, removal — all owner/admin-gated
exactly as ADR-0025 specifies); `Invitations` (issue, view, revoke,
expiry/already-accepted state); `Usage` (the existing `WorkspaceUsage`
content, still withheld from `member`-role users, per ADR-0025's
tenant-scoped-usage-visibility decision — not opened up further by this
phase). `Documents` is deliberately **not** one of administration's
contextual-region sections; see "One adaptive application shell" (section 5)
for why it lives solely in the shell's stable primary region.

**Documents is one destination, not two — and it lives solely in the shell's
stable primary region.** The brief asks this ADR to critique whether product
Documents and administration Documents should be separate. Verified against
the actual capability model (`DocumentAdministration.tsx` today renders
unconditionally for every workspace member, with retry/delete driven
per-document by a `capabilities: { retry, delete }` object the API already
returns) — there is no clean line between "ordinary document library" and
"administrative document actions" in the real data model; a member sees the
same document list an owner does, differing only in which per-document action
buttons the API says are available to them. Duplicating this into two routes
— or even into two *navigation entries* pointing at the same route from two
different shell regions — would produce exactly the ambiguous "which one am I
looking at, and which region owns my active state" failure the brief warns
against. **Decision: one `Documents` route
(`/app/workspaces/{workspace}/documents`, section 5), one
stable-primary-region navigation entry, reachable identically from chat and
administration context**, with role-aware actions rendered from the real
capability object, never a duplicated page or a second navigation entry.

Every capability boundary ADR-0025 defines is made **visible**, not just
enforced: promote-to-admin and transfer-ownership controls render only for an
`owner`; an `admin` sees member removal and invitation issuance (as `MEMBER`
only — the invite form's role selector never offers `ADMIN` to anyone but an
`owner`, mirroring the capability matrix exactly) but never sees a promote
control on another admin or an owner; a `member` reaching `/administration`
sees an explicit, honest notice ("Only workspace owners and administrators can
view this section") rather than an empty or broken-looking page — this is the
same substantive constraint the current implementation already enforces
server-side (member directory fetched as `null` for members), now given an
explicit, legible interface state instead of a silently-empty disclosure.

**Hidden vs. disabled is a deliberate per-control decision, not a single
rule**: a control the current actor's role can *never* satisfy (a `member`
promoting another member) is hidden — showing it disabled would misrepresent
"maybe with different data" as the actual reason. A control that is
*conditionally* blocked for a reason the actor can understand and potentially
resolve (revoking an already-accepted invitation, retrying a document that is
mid-deletion, removing the sole remaining owner) is shown disabled with an
inline explanation, because disabled-with-reason is genuinely informative
there in a way hiding it would not be.

Every destructive operation (document deletion, member removal, ownership
transfer) requires an explicit confirmation step — a themed shadcn
`AlertDialog`, not a bare `window.confirm()` — that names the specific target
and consequence rather than a generic "Are you sure?".

Asynchronous states already modeled server-side get first-class interface
treatment rather than being folded into a generic "processing" label:
document `deleting` (in-progress, distinct from `deleted`), retry `queued`
vs. `processing` vs. `failed` (with failure category and message, as the API
already returns), invitation `pending` vs. `expired` vs. `already accepted`
vs. `revoked`, and usage `unavailable`/partial figures rendered with an
explicit "Unavailable" state (using the `--status-unavailable` token) rather
than a blank cell or a fabricated zero — directly satisfying the "no action
shown as usable when it isn't, no unavailable data misrepresented as zero"
requirement.

### 7. Chat and evidence presentation

ADR-0024 is preserved exactly; this section is presentation only. Every state
ADR-0024 defines gets a designed interface treatment:

conversation history and new-conversation (sidebar "Recent" list plus "New
conversation," per section 5); `USER`/`ASSISTANT` message turns (avatar +
role label + timestamp, restrained bubble-free-or-minimal layout per the
reference rather than the current oversized colored-bubble treatment);
streaming progress text (the existing stage-label mechanism —
"Understanding your question…" etc. — retained, restyled); provisional
accepted answer parts (a visually distinct "still streaming" treatment, not a
dashed border reused from today but a themed, clearly-labelled provisional
state); final authoritative answer reconciliation (the moment provisional
parts are replaced by the durable, reconciled answer — visually settles from
the provisional treatment to the final one, not a jarring swap); citations and
evidence (compact citation chips — `[1]`, `[2]` — inline in answer text,
each keyboard-focusable and operable, expanding to an evidence/source card
following the reference's evidence-card layout, sourced from `EvidenceSnapshot`
data ADR-0023 already defines plus a bounded, presentation-only contract
extension defined precisely below — this ADR does not invent new *evidence
authority* or provenance fields, only a browser-facing representation of
data Laravel already holds); clarification (now a **visually distinct** turn type —
a clarification question is not styled identically to a completed answer,
closing today's gap where it renders through the same path); controlled
no-answer (`INSUFFICIENT_EVIDENCE`/`RETRIEVAL_NO_ANSWER`, an honest "no
grounded answer" state, not an error state — uses `--status-info`, not
`--status-destructive`); timeout (a **distinct** message from generic
failure, closing today's gap where timeout falls through the same
`run_failed` handling as every other failure); retry of the same
session/run where allowed (existing retry action, restyled); cancellation
(existing composer "Cancel" action, restyled); retraction after a rejected
stream (ADR-0024 guarantees fail-closed retraction and a typed terminal
failure when a candidate stream becomes untrustworthy — it does **not**
guarantee that corrected content will subsequently appear, and an earlier
draft of this ADR overclaimed that with "retracted, corrected below" wording.
The corrected treatment: provisional content is visibly retracted; the run
ends with the appropriate failure/retry presentation, same as any other
failed run; the user may retry where allowed; a corrected replacement answer
appears only if a later, separate successful run actually produces one, and
the interface never promises that outcome in advance);
reconnect/delivery interruption (existing "interrupted, retrying" message,
restyled with a clear reconnecting indicator rather than a static line);
deleted conversations (an explicit empty/removed state if a conversation
referenced in history no longer resolves, rather than a broken navigation);
bounded remembered conversation context (no interface change required — this
is a backend context-window property, not something the UI represents
directly, beyond conversations behaving consistently with it); no branching
in V1 (no branch-selector UI is built, preserving the future seam ADR-0024
already reserves).

**Citation presentation-contract extension (bounded, presentation-only).**
The approved citation-card design, including a genuine "View source" action,
is retained — but its contract is defined honestly rather than assumed. This
ADR permits a bounded extension to the browser-facing conversation
presentation contract, letting an authorised citation expose the
presentation metadata the card actually needs:

- stable citation/evidence identity (already part of ADR-0023's model);
- the document's public identity, present only when the live document
  remains available;
- a safe display name and a safe media/type label, derived server-side from
  the document record — never a raw storage path or raw MIME string passed
  through unfiltered;
- file size, when known;
- an explicit removed/unavailable state;
- the existing cited excerpt and provenance fields ADR-0023 already defines,
  carried through unchanged;
- an authorised source destination — a `source_route` field, present **only**
  when Laravel has confirmed one genuinely exists (the document is still
  live in this workspace, the requesting user retains read access, and the
  document's type/storage state supports a detail view).

The browser never constructs a storage URL and never infers whether a
destination exists from having a document id alone — it renders exactly what
`source_route` says, and nothing when that field is absent. Laravel remains
solely responsible for tenancy, authorisation and this browser-facing
representation; the extension changes what the API is permitted to *tell the
browser about a citation it can already validate*, not what counts as valid
evidence.

The canonical destination, when one exists, is
`/app/workspaces/{workspace}/documents/{document}` (section 5) — an
authorised document-detail/source route inside the single `Documents`
product area (section 6), not a second `Documents` navigation destination.
That route may offer preview or download, but only when the document's type,
storage state and existing authorisation actually support it; it is a
bounded, authorized detail page, never raw object-storage access. The
citation card's label describes the real action: it renders "View source"
only when `source_route` is present, and otherwise shows the safe
historical/removed state with no source affordance at all — it must never
say "View source" over only unavailable metadata, and must never emit a dead
link, fabricated file metadata, a raw object-storage URL or a cross-workspace
identifier.

This preserves ADR-0025's deletion semantics exactly: the durable
`EvidenceSnapshot` already carries the cited excerpt and provenance
independently of the live document's lifecycle (that durability is the point
of a snapshot), so those fields remain available from an answer regardless of
whether the underlying document is later deleted. Ordinary document deletion
under ADR-0025 does not touch a citation's historical validity — it only
removes the *live* document, which is exactly the condition `source_route`'s
absence already covers.

This entire extension is presentation-only: it does not alter evidence
authority, citation validation, or generation ownership as ADR-0023/ADR-0024
define them — it is a new, bounded shape for data Laravel already
authoritatively holds, exposed to the browser under the same tenancy and
authorization rules as every other citation field. The Laravel API/resource
extension and the `/app/workspaces/{workspace}/documents/{document}` route
are owned by **R21-S03** ("Implement Complete Interface States"), alongside
the rest of that session's real-interface-state work — not left as unowned
scope for whichever session happens to touch citations first.

Citations remain keyboard-operable (each chip is a real focusable, activatable
element, not a hover-only affordance) and screen-reader-understandable (an
accessible name conveying "citation 1, opens source" rather than a bare
bracketed number). Provisional citation references are visually and
semantically marked provisional (a distinct treatment, `aria-live`-appropriate
announcement only at meaningful transitions — see section 10) and are never
presented with the same visual finality as a reconciled citation before
ADR-0024's reconciliation completes, so a user never mistakes an in-flight,
possibly-revised reference for a settled one.

**Adopted from the mock-up**: restrained message layout, numbered/structured
answer hierarchy, compact citation chips, an evidence/source card pattern, a
grounded-status line, a persistent bottom composer with attach/settings icon
buttons (only if document-attach-to-message and per-message settings are real
committed features — otherwise the composer ships with only the controls the
product actually has: text input and send, plus whatever the current app
already implements). **Not adopted**: the header's bookmark, history, share
and overflow-menu icons, and the answer's thumbs-up/down/copy reaction row —
none are backed by real behaviour today, and per the standing instruction
they do not become decorative or dead controls in the shipped interface.

### 8. Authentication experience

The current authentication information architecture and flows are preserved
exactly: login, registration, logout, forgot/reset password, email
verification, invitation acceptance — same routes, same server-side redirect
logic (already-authenticated → `/app` or `/verify-email`; 409 handling;
token/email query-param handling for reset and invitation flows). Only the
component implementation and visual system change, via the shared `AuthForm`
component (already the single implementation point for login/register/
forgot/reset) re-themed with shadcn `Form`/`Input`/`Button`/`Alert`
primitives, the new palette, Source Sans 3, and the `dolved` wordmark.

Applied: password-manager-friendly field semantics (correct `autocomplete`
values — `email`, `current-password`, `new-password` — preserved or
introduced where currently missing, since password managers rely on these
being correct, not decorative); accessible error/validation association
(`aria-describedby` linking each field to its error text, a pattern shadcn's
`Form` primitives provide by convention); the theme toggle available on
authentication pages specifically because no authenticated shell exists yet
to host it elsewhere. No redirect, verification-token or invitation-token
handling behaviour changes — this phase does not touch
`src/lib/auth-cookies.ts`, `src/lib/api.ts`'s auth calls, or any server
action logic beyond what re-theming the rendered markup requires. A bounded
correction to the current flow is permitted only if R21-S01/S04 verification
surfaces a genuine, specific usability defect (for example a real contrast
failure or a real keyboard trap) — not as license for general flow rework.

### 9. Component system

Minimum shared vocabulary, each specified with default/hover/
focus-visible/pressed-or-selected/disabled/loading/error-where-applicable
states before it is considered done, per the user's explicit per-state
requirement:

application shell (sidebar + mobile drawer, as section 5); workspace
selector; account menu; theme toggle; buttons and icon buttons; inputs,
textareas, selects, checkboxes; a form field/error/help composite pattern;
cards/surfaces; a table/data-list pattern (with a defined mobile
transformation, not raw overflow — see section 11); tabs, used only where a
destination genuinely has parallel, equally-weighted sub-views (administration
sections are routes, not tabs, per section 6 — tabs are reserved for
finer-grained in-page groupings, if any prove necessary during R21-S02/S03,
not for top-level navigation); dialog/alert dialog; dropdown menu; tooltip;
badge/status indicator (wired to the six status tokens from section 2); a
notice/alert component (info/success/warning/destructive variants); skeleton;
spinner/progress; empty state; unavailable/partial-data state (a distinct,
reusable pattern — not each surface inventing its own "N/A" treatment);
pagination (used only where real paginated data exists — the document list
already paginates today); citation chip; evidence/source item; chat composer;
streaming/provisional state treatment; a destructive-confirmation pattern
(the `AlertDialog` usage from section 6, generalized as a shared component
rather than reimplemented per destructive action).

Composition over page-specific duplicated markup: today's pattern of each
page hand-rolling its own card/list/status markup in `globals.css` is
retired in favour of these shared components consuming shared tokens: one
`StatusBadge`, not five separate `.status-*` classes; one `EmptyState`, not a
bespoke `.empty-workspace`/`.chat-empty` per surface.

### 10. Accessibility baseline

**Target: WCAG 2.2 AA**, made testable rather than aspirational:

Automated (CI-checkable, part of R21-S04's regression suite): color contrast
of every token pairing in both themes (a script/test validating the actual
token hex values, not a one-time manual spot-check); presence of accessible
names on every icon-only control; correct heading hierarchy and landmark
structure per page; correct form label/error association;
`eslint-plugin-jsx-a11y` (already active) continuing to gate new component
code; automated axe-core-style checks integrated into the Vitest suite where
practical for shared components.

Manual (required at R21-S04, not automatable): a full keyboard walkthrough of
each primary flow (send a message, upload a document, promote a member,
delete a document, accept an invitation) with no mouse; visible-focus
verification at every interactive element (shadcn/Radix components provide
this by default — verified, not assumed, per component); a screen-reader
pass over the chat streaming experience specifically, since that is the
surface most likely to over- or under-announce; reduced-motion verification
(`prefers-reduced-motion` respected for the provisional-to-final answer
transition and any drawer/dialog open/close animation); zoom/reflow check at
200% browser zoom with no horizontal scroll or content loss.

**Live-region strategy for streaming**: a single, deliberately bounded
`aria-live="polite"` region announces *stage transitions* (e.g. "Finding
eligible evidence" → "Preparing a grounded answer" → "Answer complete") and
terminal outcomes (failure, no-answer, clarification), **not every streamed
token** — token-by-token announcement would make the feature unusable with a
screen reader, which is precisely why this is specified explicitly here
rather than left to implementation discretion.

Tables (the document list, the member directory, the usage/activity lists)
either remain genuinely tabular with proper `<table>` semantics at desktop
widths or transform into an accessible list/card representation at narrow
widths — never a `<table>` left to horizontally overflow the viewport with no
alternative, addressed concretely in section 11. Minimum touch-target policy:
44×44px for any control usable on a touch surface, including compact-density
icon buttons — density is achieved through spacing and typography, not by
shrinking hit targets below this floor.

### 11. Responsive behaviour and density

Compact-but-comfortable density, matching the reference's information
density rather than today's generously-padded card layout or a cramped
alternative. Representative layouts, defined by content behaviour rather than
named devices alone:

- **Wide desktop** (~1440px+): sidebar expanded, main content with a bounded
  max content width so long-form answers and tables don't stretch
  uncomfortably wide.
- **Laptop/smaller desktop** (~1024–1439px): sidebar expanded by default,
  user-collapsible; content reflows within the narrower available width.
- **Tablet** (~640–1023px): sidebar defaults to collapsed/icon-rail or
  off-canvas depending on final R21-S01 breakpoint tuning; touch-target
  floor applies.
- **Mobile** (<640px): sidebar is exclusively the off-canvas drawer; single-
  column content; composer remains pinned to the viewport bottom.

These map onto Tailwind's default breakpoint scale (`sm`/`md`/`lg`/`xl`) as
the mechanical implementation, but the design decision is the described
content behaviour at each range, not the breakpoint pixel values in
isolation — Tailwind's defaults are adopted rather than a bespoke scale
invented, since there is no repository-specific reason to diverge from them.

Tables transform rather than overflow: the document list and member
directory already render as card-per-row (`.document-card`) at narrow widths
today (via the current CSS's single `max-width: 800px` block) — this
behaviour is preserved and generalized into the shared `DataList` component
so every tabular surface, including ones introduced later (Usage's
provider/request table), gets the same card-collapse treatment for free
rather than each needing its own responsive CSS. Administration remains
genuinely usable on mobile — every destructive-action confirmation, every
form, every status indicator works with touch input and at mobile width —
even though desktop remains the primary expected surface for administrative
work.

### 12. Migration strategy

This is an incremental migration of a working, tested application — not a
rewrite.

**R21-S01** establishes the foundation: Tailwind v4 + shadcn/ui installed and
configured against the pinned Next.js 16/React 19 versions; the semantic
token set (section 2) defined in `@theme`; Source Sans 3 wired via
`next/font/google`; the wordmark shortlist trialled and one candidate
selected; `next-themes` installed and configured (dark default, explicit
toggle, no-flash, `color-scheme`); Lucide React installed as the icon
dependency; the shared primitive component set from section 9 built and
themed; the one adaptive `AppShell` from section 5 built and wired into
`src/app/app/layout.tsx`, replacing the current top-nav-only shell and the
two nested sidebar grids; the `dolved` wordmark migrated into the shell;
a development-only component reference surface (section 13) established.
`branding.test.ts`'s `brandedSurfaces` list is updated to match wherever the
wordmark now actually renders. The route hierarchy itself (section 5's
table) is also established here: the `workspaces/{workspace}/conversations/
{conversation}`, `.../documents`, `.../administration` (and its four
sub-routes) segments exist as real Next.js routes, `/app/operations` is
renamed to `/app/platform/operations`, and the tenant-safe not-found
behaviour is extended to the new `conversation`/`document` dynamic segments —
so R21-S02/S03 build real interface states onto an already-fixed route
boundary rather than inventing one mid-session.

**R21-S02** applies the R21-S01 system to administration and introduces the
route-backed information architecture from section 6 — `Administration
overview`, `People & roles`, `Invitations`, `Usage` as addressable
destinations inside the `AppShell`'s administration contextual region,
replacing the `<details>`-stack pattern. `Documents` is not introduced as a
fifth administration destination here — it is already R21-S01's existing
stable-primary-region destination, reused as-is with its role-aware actions;
administration's overview links to it rather than duplicating it.

**R21-S03** completes every real interface state across chat, documents,
administration, authentication and platform operations — the full state
inventory from sections 6, 7 and the states IMPLEMENTATION_GUIDE.md's Stage
21.3 already lists (loading, empty, success, partial data, unavailable
usage/cost, failed ingestion, deleting, permission loss mid-session,
expired invitations) — using the shared components rather than one-off
per-page markup. This session also owns the two pieces of newly-explicit
behaviour section 5 and section 7 fix the architecture for but leave to
implementation: the route-driven conversation-selection/new-conversation-
navigation/`GenerationRun`-continuity behaviour (section 5's route-hierarchy
subsection), and the citation presentation-contract API/resource extension
together with the `/app/workspaces/{workspace}/documents/{document}`
detail/source route (section 7's citation presentation-contract subsection)
— both genuine new surface area, not left unowned.

**R21-S04** is live visual, responsive, theme and accessibility acceptance
against the running application with real data (see section 14).

During migration: legacy `globals.css` may coexist only behind a bounded,
temporary seam (unmigrated pages continue reading their existing classes
until their turn in R21-S02/S03; new components never add further ad hoc
global selectors to that file); dead legacy styles are removed once their
last consumer migrates, page by page, rather than left indefinitely
("permanent mixture of old and new button/form/card systems" is explicitly
the failure mode this phasing avoids); every existing Vitest test file listed
in the frontend inventory continues to pass — component tests are updated to
match new markup/class structure as each component migrates, but the
*behaviour* they assert (retry semantics, upload concurrency, streaming
event handling, capability-gated rendering) does not regress; no API/domain
client (`src/lib/api.ts`, `src/lib/server-api.ts`,
`src/lib/conversation-stream.ts`, etc.) is rewritten for styling reasons —
only for the ordinary reason a component's data-fetching call site moves as
part of a genuine restructuring (e.g. the workspace page splitting into
route-backed administration sections); and no interface control ships
without a genuine, authorized, working action behind it — the same
"no dead controls" instruction that governs the mock-up's interpretation
governs every new surface this migration produces.

### 13. Design-system source of truth

The **executable source of truth** is the code itself: the `@theme` token
definitions and the owned `src/components/ui/` component set in `apps/web`.
No separate design tool, Figma file or documentation site is authoritative
over what the token values or component behaviour actually are — if
documentation and code disagree, the code is correct and the documentation is
stale.

A concise, repository-committed usage/accessibility reference (a markdown
document under `apps/web/docs/` or equivalent, not a duplicate of this ADR)
records the token names and their intended semantic use, the required states
per component, and the accessibility expectations from section 10 — a quick
reference for whoever builds the next screen, not a second source of truth
for values that live in code.

A **development/test-only component reference route** is adopted as
sufficient for V1's needs — it lets R21-S01 verify every component's states
render correctly in isolation and gives R21-S04 a fixed surface to
screenshot for regression evidence, without the ongoing maintenance cost of a
full Storybook installation. Its deployment boundary is a single rule, not
an either/or: **the route exists in development and test only.** In a
production build it resolves through the framework's ordinary not-found
behaviour, exactly like any other route that doesn't exist there — it is
never conditionally gated behind platform-administrator authorization and
never becomes a production Platform Operations page merely because the
person viewing it happens to hold that authority. Platform Operations
(`/app/platform/operations`, section 5) is its own accepted, authorized,
production surface with its own accepted scope (ADR-0026); the component
reference route is not folded into it under any circumstance. During
implementation and visual review, direct clickable local URLs (e.g.
`http://localhost:3000/…` against the development server) may be supplied to
the reviewer — that is how the route is actually used, not through any
production deployment path. **Storybook is
explicitly not introduced**: this repository's component surface area is
currently modest (the section 9 inventory), the team is a single developer
learning the stack, and Storybook's addon ecosystem, build pipeline and
version-upgrade maintenance would be disproportionate overhead for what a
committed, tested, in-app reference route already provides. This can be
revisited if the component surface grows substantially or a second frontend
developer joins and needs an isolated authoring environment Storybook is
specifically good at — not a permanent rejection, a right-sized-for-now
decision.

This reference surface is **never publicly reachable in production** as a
"design system playground" — per the single boundary fixed above, it simply
does not resolve in a production build, full stop. It is not an
authorization question (a route platform administrators can reach in
production) at all; it is a build-environment question (a route that exists
only in development and test), which is a stricter and simpler guarantee
than gating it behind ADR-0026's platform-administrator authority would be.

Visual acceptance screenshots (section 14) are **evidence that the system was
verified at a point in time**, not the source of truth for what the system
currently is — a screenshot can go stale the moment code changes; the token
definitions and component source cannot silently drift the same way. The
approved mock-up image itself remains permanently a **design-direction
reference** cited by this ADR, never a production asset copied into the
running application.

### 14. Verification and acceptance

An explicit acceptance matrix, checked at R21-S04, crossing: chat, documents,
workspace administration, platform operations, authentication; desktop and
mobile; dark and light; owner/admin/member/platform-administrator
authorization (each role's actually-visible navigation and actually-available
actions, verified against ADR-0025/0026's real matrices, not an assumed
simplification); and the full interface-state list from section 7/12
(loading, empty, success, partial, unavailable, failure, deleting, revoked,
expired).

Required evidence: component regression tests (Vitest) for every shared
primitive and every migrated page-level component, at minimum matching
today's coverage (the twenty existing test files enumerated during
inspection) and extended to cover newly-split administration routes;
accessibility checks per section 10 (automated + the manual walkthrough,
recorded as evidence, not just asserted); a keyboard-only walkthrough
transcript/recording of the primary flows; contrast verification results for
every token pairing in both themes; representative desktop and mobile
screenshots (at minimum the four representative widths from section 11) for
chat, documents, administration and authentication, **in both themes** for
the key surfaces (chat conversation view, administration overview, login);
verification performed against the live running application with real
workspace/conversation/document data, not only the static mock-up — no
provider (OpenAI/Voyage) calls are made merely to produce a visual
screenshot, reusing existing fixture/seed data or already-completed
conversations instead; confirmation that the pre-existing, currently-passing
Vitest/ESLint/TypeScript checks (`make test-web`, `make lint`, `make
typecheck`) still pass at each session boundary; and an explicit statement
in the R21-S04 journal confirming no inaccessible or non-functional ("dead")
control was introduced anywhere in the migrated surfaces.

Representative viewport sizes for screenshot evidence: 1440×900 (wide
desktop), 1280×800 (laptop), 834×1194 (tablet, portrait), 390×844 (mobile) —
chosen as realistic, commonly-available device/browser presets rather than
arbitrary numbers, while the actual layout *behaviour* being verified is the
content-driven breakpoint logic from section 11, not these exact pixel
values in isolation.

**Token-completeness acceptance requirement, verified at R21-S01** (before
any later session builds further screens on top of an incomplete contract):

- no installed shadcn component (including the Sidebar primitive) references
  a theme variable this ADR's bridge (section 2) leaves undefined, in either
  theme;
- both `:root` and `.dark` resolve every semantic token the bridge maps —
  no token defined for one theme and silently missing from the other;
- no component source introduced during R21-S01–S03 contains an
  unauthorized raw hex/HSL/RGB color literal outside the Layer 1/2 token
  definitions themselves;
- no reference to the old orange/forest/off-white identity (`--ink`,
  `--muted`, `--paper`, `--card`, `--line`, `--accent`, `--accent-dark`,
  `--forest`, `--mint`, or their literal hex values) remains in any migrated
  component;
- the Sidebar token contract (section 2) resolves correctly in expanded,
  icon-rail-collapsed and mobile-drawer states alike;
- every interactive and status foreground/background pair from section 2's
  corrected contrast requirements passes its specific required check, not
  merely "looks fine."

This is implemented as CSS/token contract tests plus component rendering
tests during R21-S01 — this ADR does not prescribe exact test filenames,
only the guarantee itself. Any shadcn component generated into the
repository **after** R21-S01 (a later addition during R21-S02/S03, or a
future phase) undergoes the same token-contract review before it ships:
verify its references against the bridge, extend the bridge for any
genuinely new slot, and add it to this same completeness check — the
contract is not a one-time audit that decays as new components arrive.

### 15. ADR necessity and boundaries

This ADR is justified for the reasons ADR index policy requires: it
establishes a foundational technology selection (Tailwind v4, shadcn/ui,
Radix primitives, Lucide, `next-themes`) with real alternatives and
trade-offs; it sets a durable visual/token identity that every future screen
must draw from; it makes a product-wide navigation and information-
architecture decision (one adaptive shell, route-backed administration,
Documents-as-one-destination) that would be expensive to reverse once dozens
of pages depend on it; it sets an explicit, testable accessibility baseline;
and it establishes design-system governance (source of truth, migration
boundary, Storybook-vs-in-app-reference) that later phases must not
re-litigate per page.

It deliberately does **not** become a page-by-page implementation
specification — exact spacing values, exact component prop APIs and exact
per-page layout decisions remain implementation detail for R21-S01–S04, not
ADR content — and it does not reopen any accepted backend, domain, contract
or authorization decision from ADR-0006, ADR-0023, ADR-0024, ADR-0025 or
ADR-0026.

### Acceptance record

Accepted on 2026-08-21 after the implementation-readiness review confirmed
the concrete route and state-ownership model, the bounded Laravel-owned
citation presentation contract, and stable repository-owned visual-reference
lineage. The ADR index, Phase 21 implementation guide, project roadmap and
R21 tracker were reconciled in the same acceptance boundary.

## Alternatives considered

**Retain handwritten CSS.** Rejected as the sole approach going forward:
the current 1,217-line single-file stylesheet with nine tokens and twenty-plus
untokenized literals is precisely the "manually organised, generic-reading"
state the user wants to leave, and hand-rolled accessible interactive
primitives (dialogs, dropdowns, comboboxes) are expensive and error-prone to
build correctly from scratch compared to adopting audited, composable
primitives.

**Tailwind without shadcn.** A real, lighter-weight option — utility classes
alone would already retire the token sprawl. Rejected as insufficient on its
own because it leaves every accessible interactive pattern (dialog focus
trapping, dropdown keyboard nav, combobox ARIA wiring) to be reimplemented by
hand, which is exactly the kind of "the details will be wrong" risk shadcn's
Radix foundation removes at low cost, given the components are still owned
and themed, not black-boxed.

**A compiled third-party component library** (MUI, Chakra, Ant Design,
Mantine). Rejected: each ships its own opinionated visual identity and
theming API layered on top of the library rather than component source the
repository owns outright, making it harder to fully erase "generic AI-product
styling" and harder to evolve components in lockstep with product-specific
needs (citation chips, evidence cards, streaming-state treatments) that no
general-purpose library anticipates.

**shadcn/ui with repository-owned components** — selected, for the reasons in
section 1.

**Multiple permanent sidebars** (today's actual state: workspace switcher
plus nested conversation sidebar). Rejected: it already reads as two
different navigational systems stacked on top of each other and is the
opposite of "reading as one product."

**One adaptive sidebar** — selected, section 5.

**Dark-only interface.** Rejected: the user explicitly asked for a
first-class light theme with a toggle, not a dark-only product; a
dark-only interface would also fail users who need or prefer light mode for
legibility/environment reasons, with no accessibility escape hatch.

**System-default theme** (`prefers-color-scheme` driven, no explicit
toggle). Rejected per explicit instruction: the user asked for a dark
default with an explicit toggle, not automatic OS-following behaviour; a
system default would also make "dark as the first-visit default" untrue for
users whose OS is set to light.

**Dark-default with explicit toggle** — selected, section 3.

**Keep all administration on one page.** Rejected: it is the specific,
named problem this phase (and Stage 21.2) exists to fix — a single page
concatenating overview/documents/people/invitations/usage as disclosure
widgets does not scale, does not deep-link, and does not read as
"purposeful sections."

**Route-backed administration sections** — selected, section 6.

**Preserve the current visual identity** (orange/forest/off-white). Rejected
per explicit user direction: the current palette is not the desired product
identity and reads as generic.

**Adopt the approved new visual direction** — selected, throughout.

**Use Calibri directly.** Rejected: not open-licensed, not guaranteed present
on a user's device, and not embeddable/self-hostable for reliable web
delivery — exactly the dependency the brief warns against.

**Use an open web-safe humanist alternative** (Source Sans 3) — selected,
section 4.

**Mix icon libraries** (keep some hand-styled glyphs, add Lucide only for new
components). Rejected: produces a visually inconsistent icon language exactly
like the "second parallel styling system" the migration strategy is designed
to avoid; every icon, old and new, migrates to Lucide as its surface is
touched.

**Standardize on Lucide** — selected, section 1.

**Add Storybook.** Rejected for V1, for the reasons in section 13 — genuine
overhead disproportionate to current team size and component-surface size;
revisitable later, not foreclosed.

**Use a smaller internal reference surface** — selected, section 13.

**Redesign and migrate everything in one large replacement.** Rejected: this
is a working, tested, in-production-shaped application; a single large
replacement risks a long period with no verifiable state, makes review and
rollback harder, and contradicts the explicit "incremental migration that
avoids breaking working functionality" instruction.

**Staged migration across R21-S01–S04** — selected, section 12.

## Consequences

### Positive

- A single, owned, themeable component and token system replaces a
  1,217-line hand-written stylesheet with nine tokens and twenty-plus
  untokenized color literals — every future screen inherits consistent,
  accessible primitives instead of hand-rolling them.
- One adaptive shell replaces two independent nested sidebar grids and a
  separate top-nav-only layout, so chat, documents, administration and
  platform operations finally read as one product rather than three loosely
  related page templates.
- Administration becomes addressable, deep-linkable route structure instead
  of a fixed stack of disclosure widgets, directly enabling Stage 21.2's
  goal without inventing a simplified permission model.
- A genuine dark/light theme system with no-flash hydration replaces zero
  existing theme infrastructure, and the current Geist-loaded-but-unused /
  Arial-actually-rendered inconsistency is resolved by construction.
- An explicit, testable WCAG 2.2 AA baseline exists where none was previously
  defined, with a concrete live-region strategy that prevents the streaming
  chat experience from becoming unusable with assistive technology.
- Every accepted domain/backend ADR (0006, 0023, 0024, 0025, 0026) is
  preserved untouched; this phase changes presentation only.

### Negative

- A genuinely large surface-area migration: every existing page is touched,
  and all twenty existing Vitest test files must keep passing throughout —
  but only those coupled to markup, routes or shared-shell structure this
  phase intentionally changes (chat, the sidebar-dependent components,
  administration, the wordmark/branding surfaces) actually require editing;
  behavioural assertions are never weakened merely to make the redesign pass,
  and tests over untouched surfaces (for example `lib/api.test.ts`,
  `lib/env/schema.test.ts`) are expected to keep passing unmodified. This is
  still real, bounded implementation cost across four sessions, not free.
- The wordmark's exact final typeface is not fixed by this ADR — a real (if
  small) risk that R21-S01's shortlist doesn't produce a result the user is
  happy with, requiring a further iteration before the identity is fully
  settled.
- Adopting Radix-based shadcn primitives introduces a real, if modest, new
  runtime dependency surface (`@radix-ui/react-*` packages, `next-themes`,
  `lucide-react`, Tailwind's build tooling) where today there is none —
  ongoing maintenance (version bumps, occasional breaking changes in the
  shadcn CLI's generated component source) becomes a standing cost.
- Consolidating two independent sidebar systems into one adaptive shell is an
  architecturally significant frontend refactor of `src/app/app/layout.tsx`
  and the workspace page, carrying real regression risk to `ChatWorkspace`'s
  well-tested streaming behaviour if the migration is not done carefully
  component-by-component as section 12 specifies.
- A temporary period (spanning R21-S01 through S03) where legacy
  `globals.css` and the new token/component system coexist is an accepted,
  bounded cost, but any slippage in retiring legacy styles as pages migrate
  would leave the exact "permanent mixture of old and new" state this ADR
  explicitly commits to avoiding.
