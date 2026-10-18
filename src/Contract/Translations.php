<?php

namespace Base\Contract;

use Omnibridge\Twig\Contract\Translations as ViewTranslations;

/**
 * The application's texts, as omnibase's framework-free code asks for them:
 * a message in the page's language (`trans()`, omnibridge/twig's, ICU
 * placeholders and plurals), and whether the catalogues hold a key at all
 * (`exists()`), which a code that tries several keys in turn needs - an
 * entity's noun, singular or plural, at a level of politeness, for a gender
 * (Base\Service\Model\EntityNouns).
 *
 * It is omnibridge/twig's Translations under omnibase's name with that one
 * question more, as Base\Contract\Urls is its Urls (plan "omnibase
 * agnostique", C4, D8; C4b for exists()). A host that only renders pages
 * fills omnibridge/twig's; one that runs omnibase's entities fills this one,
 * which answers both. Under Symfony, Base\Bridge\Symfony\TranslatorTranslations:
 * omnibase's translator (the texts rewritten in the back office, "@domain.key"
 * references, politeness), the same strings and answers as Twig's |trans and
 * Base\Service\Translator::transExists().
 */
interface Translations extends ViewTranslations
{
    /**
     * Whether the catalogue of that locale (the page's when none is given)
     * holds the key in that domain (a "@domain." prefix of the key names it
     * too; "messages" when neither does), its fallback catalogues included.
     */
    public function exists(string $id, ?string $domain = null, ?string $locale = null): bool;
}
