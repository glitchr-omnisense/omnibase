---
title: Base documentation
order: 0
---

# Base documentation

This is the manual shipped by **base-bundle** itself. It describes what the
framework provides to every application built on it.

Pages are markdown files on disk, not database rows, so they are versioned
alongside the code they describe and reviewed in the same diff.

## How this manual is assembled

The manual reads from several *roots*, declared lowest priority first in
`config/packages/wikidoc.yaml`. A page is identified by its path inside a
root, so an application can replace any base page by creating a file at the
same path:

```
vendor/glitchr/base-bundle/docs/operations/deployment.md   <- base
docs/operations/deployment.md                              <- wins
```

This is the same override rule Symfony uses for bundle templates. A page that
is overriding a base one is marked in the sidebar and in its header.

## Writing a page

- A directory becomes a section; its `index.md`, if present, becomes that
  section's own page.
- The title comes from front matter, else the first `# heading`, else the
  filename.
- Ordering comes from front matter `order:`, else a numeric filename prefix
  (`10-`), else the title.
