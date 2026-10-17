<?php

namespace Base\Bridge\Symfony;

use Omnibridge\Twig\Contract\Translations;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * omnibridge/twig's Translations under Symfony: the application's `translator`
 * (omnibase's chain: the texts rewritten in the back office, then
 * Base\Service\Translator - "@domain.key" references, politeness - then
 * Symfony's, ICU catalogues), what Twig's |trans answers, the same strings.
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
}
