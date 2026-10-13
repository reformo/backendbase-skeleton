# ADR Index

Open [index.html](index.html) to browse all numbered Architecture Decision Records.
Select a record to read its Markdown content in a wide modal.
Use search and status filters to find a decision.

The modal supports keyboard navigation, Escape, a close button, and backdrop dismissal.
Links to other ADRs and local reference documents open in the same modal.

## Source and Rebuild

Markdown files remain the source of truth.
The index includes their content so it works when opened directly from disk.
When served over HTTP, the modal requests the current Markdown file.
If that request fails, it identifies the displayed content as a saved copy.

Run from the repository root after adding, removing, renaming, or editing Markdown files:

```sh
node bin/build-adr-index.mjs
```

Check that the index matches the Markdown sources:

```sh
node bin/build-adr-index.mjs --check
```

The builder uses Node.js built-in modules.
It validates identifiers, required metadata, UTF-8, and duplicate record numbers.
It updates only the embedded document data inside the existing HTML page.
The template and reference documents remain available without appearing as ADR records.

## Markdown Rendering

The page uses a local copy of Marked 17.0.5.
It needs no external scripts or network connection for direct-file use.
Raw HTML is displayed as text.
Links and images accept only supported URL schemes.

The bundled parser retains its [MIT license](assets/marked.LICENSE).
