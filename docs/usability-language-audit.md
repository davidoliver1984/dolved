# Product usability and language audit

Date: 25 November 2026

## Product language standard

The interface should describe the user’s task, not the implementation. Technical domain names remain in APIs, audit records and specialist platform tools, but routine screens use familiar language.

| Internal concept | Everyday interface wording | Use help text when |
| --- | --- | --- |
| Document family | Document | Explaining that shared details span multiple versions |
| Current authority | Current version | Explaining which version answers use on a given date |
| Governance status | Approval status | Explaining draft, approved, scheduled or withdrawn states |
| Applicability | Locations / Where this applies | Explaining that this is not an access permission |
| Effective from | Effective from | Explaining that this is the date the version starts being used |
| Frozen membership | Documents selected when the action started | Explaining why later filter changes do not alter an action |
| Eligible | Ready / available | Explaining why a document cannot be included |
| Promotion | Indexing / add to library | Never in an everyday workflow |
| Evidence | Sources / source material | “Evidence” remains acceptable in specialist review tools |
| Tenant | Workspace | “Tenant” remains acceptable in platform administration only |

## Journey findings

### Ask a question

- Put the question first and keep the location filter secondary.
- Describe answer progress in plain language: finding documents, preparing sources and writing the answer.
- Label supporting material “Sources used”. Do not expose retrieval or claim-level terminology.
- Keep source limitations accurate and actionable: invite the user to open the source text.

### Find and manage documents

- Present one “Document” with its versions, not a “document family”.
- Use “Current version”, “Versions”, “Document details” and “Locations”.
- Keep effective dates, approval and withdrawal explicit because they affect which version is used.
- Offer “Upload new version” on the document page and inherit existing details by default.

### Import documents

- Ask one meaningful question: is this a new document or a new version?
- Review the owner, category, dates and locations in one modal.
- Save once; indexing continues automatically.
- Hide preflight, promotion, checksum and immutable-decision language unless an error needs a precise explanation.

### Change several documents

- Call these “Bulk actions”.
- Explain that the selected documents are fixed when the action starts without using “frozen membership”.
- Use “Ready”, “Excluded”, “Succeeded” and “Needs attention”.
- Keep exclusions visible before confirmation and preserve the detailed audit record after completion.

### Workspace administration

- Use task labels such as People & roles, Invitations, Usage, Question review and Locations.
- Describe notification categories as document updates and reminders, not governance events.
- Use concise help for role impact, review dates and location choices.

### Platform administration

- Technical terms are acceptable because this is a specialist surface, but each metric needs a one-sentence definition and an empty state that distinguishes “no traffic” from “telemetry unavailable”.
- Keep platform-wide workspace management distinct from an individual workspace’s administration.

## Help-text rule

Use a question-mark help tip only when a reasonable user may understand the words but not their consequence. Good candidates are Effective from, Review due, Current version, Locations and approval states. Do not add tips to self-explanatory buttons such as Save, Cancel, Search or Upload.

Help tips must be keyboard-focusable, have an accessible label and supplement rather than replace visible labels.

## Release approach

Changes should be released in small, rollback-safe groups:

1. Core journeys and shared terminology.
2. Import and bulk-action simplification.
3. Workspace administration guidance.
4. Specialist platform metric definitions and empty states.

Every group should update tests that assert user-facing language and should be checked in both light and dark themes at desktop and narrow widths.
