---
title: Reference
order: 50
---

# Reference

What the core declares to the framework, listed from its code and from the
harness (`docker/`), with what each piece does:

- [Configuration reference](configuration.md): every option of `base:`, its
  type, default and reader; the parameters it becomes; what the extension
  registers (tags, prepended configuration).
- [Console commands](commands.md): the 24 commands the core registers.
- [Events](events.md): the events the core dispatches, the entity
  dispatchers, the Doctrine events of the mapping and the queries, and what the
  core listens to.
- [Twig reference](twig.md): the globals, functions, filters and tests.
- [Form types](form-types.md): the field and form types, the type extensions
  and their options.
- [Routes and access](routes-and-access.md): the routes of the core's
  controllers and its voters.

The contracts of the Foundation (`Base\Contract\*`, `Omnibridge\Contract\*`) are
in [The contracts of the Foundation](../20-architecture/contracts.md).
