<?php

namespace Base\Bridge\Symfony;

use Omnibridge\Contract\Translations;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * omnibase's Translations under Symfony (omnibridge/twig's, and exists() for
 * the Fondation, C4b): the application's `translator` (omnibase's chain: the
 * texts rewritten in the back office, then Base\Service\Translator -
 * "@domain.key" references, politeness - then Symfony's, ICU catalogues),
 * what Twig's |trans answers, the same strings; and whether a key is there as
 * Base\Service\Translator::transExists() says (the catalogue of the locale,
 * its fallbacks), or a plain translator's catalogue when it is not omnibase's.
 */
final class TranslatorTranslations implements Translations
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
    {
        if (method_exists($this->translator, 'transExists')) {
            return $this->translator->transExists($id, $domain, $locale);
        }
        if (!$this->translator instanceof TranslatorBagInterface) {
            return false;
        }

        if (str_starts_with($id, '@') && str_contains($id, '.')) {
            [$domain, $id] = explode('.', substr($id, 1), 2);
        }

        return $this->translator->getCatalogue($locale)->has($id, $domain ?? 'messages');
    }
}
