The architecture for ADR-0010 has now been reviewed and agreed.

Please update the ADR using the review feedback below.

### Final architectural decisions

- Preserve the existing ADR structure and writing style.
- Remain implementation agnostic.
- Do not introduce Python class definitions, schemas or parser-specific details.
- Keep the ADR focused on architectural reasoning rather than implementation.

### Confirmed architectural decisions

- Every extractor produces a canonical immutable `ExtractedDocument`.
- Downstream stages depend only on the canonical contract.
- Extraction, normalisation and chunking each have a single responsibility.
- Extraction preserves semantic structure rather than simplifying documents.
- Lossy transformations are deferred until later pipeline stages.
- The canonical model is centred around a common `Element` abstraction.
- The initial semantic element types are:

    - HeadingElement
    - ParagraphElement
    - ListElement
    - TableElement
    - CodeBlockElement
    - QuoteElement
    - HyperlinkElement
    - ImageCaptionElement
    - HorizontalRuleElement
    - FootnoteElement

- The architecture must remain open for future element types without downstream redesign.
- Preserve reading order.
- Preserve document hierarchy.
- Preserve page numbering.
- Preserve provenance.
- Preserve extraction confidence where available.
- Preserve document metadata.
- Every semantic element has a stable identifier.
- All extraction failures are audited.
- Permanent failures contain both machine-readable failure codes and human-readable user messages.
- Distinguish permanent failures from transient failures.
- `ExtractedDocument` is immutable. Pipeline stages never mutate previous representations; they create new derived representations.

### Architectural principles

Please retain these principles prominently within the ADR.

> Preserve as much semantic information as possible during extraction. Defer any lossy transformation until a later stage where there is sufficient context to make an informed decision.

> Extraction is a loss-minimisation stage, not a simplification stage.
>
> The responsibility of extraction is to preserve the document's semantic structure as faithfully as practical. Decisions that intentionally discard information belong to later pipeline stages where there is sufficient context to evaluate the trade-offs.

Please also include this architectural observation where appropriate:

> Information can always be discarded later. Information cannot be recreated later.

### Existing review feedback

Please retain the existing improvements already made:

- Link this ADR architecturally to previous ADRs where appropriate.
- Keep "workspace" terminology consistent with ADR-0006.
- Keep the discussion around contracts intentionally deferred until cross-language boundaries actually exist.
- Retain the discussion of rejected alternatives, including the decision not to use a generic untyped `Block` model.

Unless something materially improves the architecture, this ADR should now be considered the accepted version ready for implementation.