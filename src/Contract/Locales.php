<?php

namespace Base\Contract;

/**
 * The languages of the site, in omnibase's form: language and country joined
 * by a dash (fr-FR, en-GB, Base\Service\Localizer::SEPARATOR).
 *
 * The Fondation's own contract (plan "omnibase agnostique", C4): the
 * translatable entities and their getters ask it instead of the Localizer
 * service. Named in the plural, as omnibridge/twig's Urls and Translations,
 * and so as not to shadow PHP's \Locale where it is imported. Under Symfony,
 * Base\Bridge\Symfony\LocalizerLocales: the Localizer's answers.
 */
interface Locales
{
    /** The locale of the request being answered (or of the command), fr-FR. */
    public function getLocale(): string;

    /** The site's default locale, the language its texts are written in first. */
    public function getDefaultLocale(): string;

    /**
     * Every locale the site speaks, the default first.
     *
     * @return list<string>
     */
    public function getAvailableLocales(): array;
}
