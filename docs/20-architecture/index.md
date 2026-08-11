---
title: Architecture
order: 20
---

# Architecture

The bundle is organised around a few long-lived pieces:

- **Entities** — `Thread` and its subtypes carry the shared title/slug/content
  model that articles, comments and pages all reuse.
- **Settings** — a compiled snapshot of configuration, read through
  `SettingBag`.
- **Admin** — a from-scratch administration bundle (`base-bundle-admin`).
