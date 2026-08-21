# Dolved design system

ADR-0027 is the architecture decision. The executable source of truth is
`src/app/globals.css` (`:root`, `.dark`, and `@theme inline`) together with
the repository-owned components under `src/components/ui/`.

## Rules

- Components use semantic utilities such as `bg-surface`, `text-foreground-muted`,
  `border-border`, and `text-brand`. They never use raw palette utilities or
  literal colour values.
- Dark is the first-visit default. The explicit two-state theme control is
  available before and after authentication and does not infer system mode.
- Source Sans 3 is interface type. The live lowercase `dolved` wordmark uses
  Sora ExtraBold. Product-name prose remains `Dolved`.
- Every interactive control has a visible focus state and a minimum 44×44px
  touch target. Icon-only controls require an accessible name.
- Status always combines a semantic colour with an icon and text label.
- Expected errors are rendered as values through `Notice` or field-error
  content; unavailable data is never fabricated as zero.
- Prefer shared components and composition. Do not add new page-specific
  global selectors or a second component/styling system.

## Shared vocabulary

Foundation components include buttons, icon buttons, inputs, textareas,
selects, checkboxes, form fields, cards, badges, status badges, notices,
dialogs, destructive alert dialogs, dropdown menus, tabs, tooltips, loading
skeletons/spinners and empty states. Product-specific chat, evidence,
data-list and partial/unavailable patterns build on these primitives during
R21-S02 and R21-S03.

## Reference surface

In development and test, open `http://localhost:3000/design-system`. The
route deliberately resolves through ordinary not-found behaviour in a
production build.

The reference surface is a review and regression aid. It is not a second
source of truth and must not introduce controls or data unsupported by the
real product.
