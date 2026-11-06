<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\LocalizerInterface;
use Base\Service\TranslatorInterface;
use Base\Services;
use Omnibridge\Contract\Locales;

/**
 * What the translation keys' tasks share (plan "omnibase agnostic", step F5e, slice 3): the languages listed (the
 * site's, or the one asked, refused when the site has not got it), a key's line - found, or "no translation found" -
 * and where the core's and the application's classes are.
 */
abstract class TranslationTask
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        protected readonly LocalizerInterface $localizer,
        protected readonly Locales $locales,
    ) {
    }

    /**
     * The language asked (normalised) and the site's languages.
     *
     * @return array{0: ?string, 1: list<string>}
     *
     * @throws \Exception the language asked is not one of the site's
     */
    protected function languages(?string $locale): array
    {
        $locale = $locale ? $this->localizer->getLocale($locale) : null;
        $available = $this->locales->getAvailableLocales();
        if ($locale && !\in_array($locale, $available)) {
            throw new \Exception('Locale not found in the list of available locale: ['.implode(',', $available).']');
        }

        return [$locale, $available];
    }

    /** A key's part of a line: "<prefix>@<domain>[<locale>].<path>" and its translation, or "no translation found". */
    protected function key(string $key, string $shown, string $locale, string $missing = ''): string
    {
        $translation = $this->translator->trans($key, [], null, $locale);

        return $translation == $key
            ? '<warning>'.$shown.'</warning><red> = "no translation found"</red>'.$missing
            : '<warning>'.$shown.' </warning>= "'.$translation.'"';
    }

    /** The core's src/ folder (where Base\ is). */
    protected static function core(): string
    {
        return \dirname((new \ReflectionClass(Services::class))->getFileName());
    }

    /** A new report printed by the sink given, if any. */
    protected static function report(?\Closure $sink): Report
    {
        return new Report($sink);
    }
}
