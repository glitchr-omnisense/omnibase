---
title: Deployment
order: 30
---

# Deployment

> This is the **base** deployment page. An application that deploys
> differently should override it by creating `docs/operations/deployment.md`.

## Generic flow

1. Run the test suite.
2. Build assets.
3. Run migrations.
4. Warm the cache.

## Cache

Warm the cache *before* switching traffic. Several warmers touch the database,
so a cold cache on the first request after a deploy is both slow and risky.
