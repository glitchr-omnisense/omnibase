---
title: Getting started
order: 10
---

# Getting started

## Requirements

| Component | Version |
|---|---|
| PHP | 8.4+ |
| Symfony | 8.x |
| MySQL | 8.x |

## Installation

```bash
composer install
php bin/console doctrine:migrations:migrate
npm install && npm run dev
```

## Conventions

The `Base\` namespace mirrors `App\`: a class in `Base\Entity\User` is
aliased so the application can override it at `App\Entity\User` without the
bundle knowing. This is deliberate and load-bearing — do not rename it.
