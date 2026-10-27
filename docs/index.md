---
title: omnisense/omnibase
order: 0
---

# omnisense/omnibase

This is the manual shipped by **omnisense/omnibase** itself (formerly
base-bundle, then glitchr/omnibase and omniscient/omnibase), the framework core
of the omnibase sites. It describes what the core provides to every
application built on it, and to the `omnibase/*` plugins.

## How this manual is organised

- [Getting started](10-getting-started.md): requirements, installation in a
  Symfony application, the `Base\` / `App\` convention.
- [Scaffolding a site](scaffold.md): what a new site copies, the demonstration,
  the first start.
- [Architecture](20-architecture/index.md): what the core requires, entities
  and repositories, the contracts of the Foundation, the Doctrine mapping,
  settings, time, politeness, the forms and their guard, account security, the
  demo environment, sealed fields, errors.
- [Operations](30-operations/deployment.md): deployment, backups, the Docker
  harness.
- [Shared bricks](40-commons/index.md): opening hours, JSON-LD, redirections,
  the sitemap, calendars, invitations, downloads and QR codes, signatures, PDF,
  e-mails, comments, pages and texts, the front end.
- [Reference](50-reference/index.md): the configuration tree, the console
  commands, the events, the Twig functions and filters, the form types, the
  routes and the voters.
- [Decisions](decisions/router-scheme.md): the decisions recorded, with their
  reasons.

Pages are markdown files on disk, not database rows, so they are versioned
alongside the code they describe and reviewed in the same diff.

## How this manual is assembled

The manual reads from several *roots*, declared lowest priority first in
`config/packages/wikidoc.yaml`. A page is identified by its path inside a
root, so an application can replace any base page by creating a file at the
same path:

```
vendor/omnisense/omnibase/docs/30-operations/deployment.md   <- base
docs/30-operations/deployment.md                              <- wins
```

The path is the file's, numeric prefix included (omnibase/docs'
`DocumentationRegistry::pathFor()` strips only `.md` and a final `index`):
the prefix orders the pages, it is also part of their identity.
This is the same override rule Symfony uses for bundle templates. A page that
is overriding a base one is marked in the sidebar and in its header.

## Writing a page

- A directory becomes a section; its `index.md`, if present, becomes that
  section's own page.
- The title comes from front matter, else the first `# heading`, else the
  filename.
- Ordering comes from front matter `order:`, else a numeric filename prefix
  (`10-`), else the title.
