# Phase 21 visual design references

This directory holds the durable, repository-owned visual references cited by
`docs/adr/0027-define-the-product-interface-and-design-system.md`. Everything
here is a **design/reference artifact for the ADR and Phase 21 implementation
sessions to consult** — none of it is a runtime production asset, none of it
is bundled into `apps/web`'s build output, and none of it is a source of new
product functionality beyond what ADR-0027 itself decides.

## Approved repository-owned references

These two images are the actual approved visual direction. They were copied
into the repository from the locations the product owner originally supplied
them at, so ADR-0027 can cite a stable, repository-relative path instead of
an absolute, user-machine-specific one.

| File | SHA-256 | Description |
|---|---|---|
| `approved-chat-shell-dark.png` | `e3d9591d2015e2a29c6573ff2597d914fe9dd550c6dad2c94831cbb362ed8fb0` | The approved generated mock-up of the chat shell in the dark theme: sidebar, wordmark, workspace selector, navigation, conversation view, citations, composer. This is the **approved visual direction** — placement, density and character reference — not a pixel-perfect implementation contract and not itself a source of new product functionality. Controls it depicts that the product does not actually implement are excluded from scope; see ADR-0027 section 5/7. |
| `approved-slate-palette.png` | `9d20e1293f2ea803a8d7e7720938b483b3bdae97f1a21c270ac94e60e13dfdfa` | The approved five-step deep-slate ramp swatch (`#1F2A35` / `#455766` / `#7F93A1` / `#C9D4DC` / `#F3F6F8`). Treated as authoritative literal hex values by ADR-0027 section 2, not inspiration to re-derive. |

Checksums were computed with `shasum -a 256` at the time each file was copied
into this directory and should be re-verified if either file is ever replaced.

## Third-party inspiration references (not copied into this repository)

Two further screenshots the product owner supplied during ADR-0027's drafting
were **inspected but are not copied into this repository**, since they are
not this product's own approved assets — one is a screenshot of a third-party
admin-dashboard UI template, the other is a specimen of a third-party
commercial font. Copying either as a tracked repository asset would imply a
redistribution right that has not been established. They remain referenced
here by description only, exactly as ADR-0027's Context section describes
them.

### "Apex Dashboard" admin template screenshot

- **Original source URL**: unknown. This is a product-owner-supplied
  inspiration screenshot; its original source page was not independently
  verified during ADR-0027's drafting, and no attribution is invented here.
- **Characteristics treated as inspirational only**: the true-black canvas
  and restrained emerald-accent instinct; the sidebar's grouped, labelled
  navigation sections; a top command/search-bar placement precedent.
- **Explicitly not adopted**: its chart-and-KPI-dense analytics dashboard
  density (sparkline cards, donut charts, goal-progress bars) — ADR-0027
  section "Context" excludes this outright, since the user's brief
  specifically excludes "dashboards or charts unsupported by real data."
- Dolved must not reproduce this template's branding ("Apex"), its specific
  content, copy or page composition. Only the narrow structural
  characteristics named above inform ADR-0027's decisions.

### "Coliner" font specimen screenshot

- **Original source URL**: unknown for the specimen screenshot itself; not
  independently verified during ADR-0027's drafting, and no attribution is
  invented here.
- **The referenced typeface's licensing was independently verified**
  (separately from the screenshot's provenance): Coliner is distributed
  under the 1001Fonts Free-For-Personal-Use license family; commercial use
  requires a purchased commercial or corporate license from its foundry,
  Jetsmax (verified via `1001fonts.com` and `jetsmax.com` during ADR-0027's
  drafting). Dolved does not hold such a license and this ADR does not
  recommend acquiring one.
- **Characteristics treated as inspirational only**: the wordmark's bold,
  rounded, geometric, single-story letterforms and tight tracking.
- **Explicitly not adopted**: the Coliner typeface itself, as an asset or a
  dependency. ADR-0027 section 4 instead selects **Sora ExtraBold** (SIL
  Open Font License, freely commercially usable) as the leading open-license
  implementation candidate, chosen for sharing Coliner's stylistic character
  without its licensing restriction.
