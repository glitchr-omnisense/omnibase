<?php

namespace Base\Field\Behaviour;

use Omnibridge\Form\FieldAssets;
use Omnibridge\Form\FieldBehaviour;

/**
 * A behaviour's plain parts: its defaults under the given options, no vars,
 * the value as it is both ways, no front end, no route. The generic binder
 * (Base\Form\Definition\FormBinder) completes a value a behaviour leaves as a
 * string by its kind's rule (a date, a number).
 */
abstract class AbstractBehaviour implements FieldBehaviour
{
    /** @return array<string, mixed> */
    abstract protected function defaults(): array;

    public function options(array $given): array
    {
        return array_replace($this->defaults(), $given);
    }

    public function vars(array $options, mixed $shown, string $id): array
    {
        return [];
    }

    public function transform(mixed $data, array $options): mixed
    {
        return $data;
    }

    public function reverse(mixed $submitted, array $options): mixed
    {
        return $submitted;
    }

    public function assets(array $options): FieldAssets
    {
        return new FieldAssets(\is_string($options['webpack_entry'] ?? null) ? $options['webpack_entry'] : null, $this->controller());
    }

    public function endpoints(array $options): array
    {
        return [];
    }

    /** The prefix of the data attributes the widget's controller reads; null for none. */
    protected function controller(): ?string
    {
        return null;
    }

    /** The language of the locale being answered (fr of fr-FR), as omnibase's Localizer::getLocaleLang() says it. */
    protected static function language(): string
    {
        $locales = \Omnibridge\Runtime::find(\Omnibridge\Contract\Locales::class);
        $locale = $locales instanceof \Omnibridge\Contract\Locales ? $locales->getLocale() : \Locale::getDefault();

        return strtolower((string) preg_split('/[-_]/', $locale)[0]);
    }

    /** A text in the host's catalogues, the key itself without any. @param array<string, mixed> $parameters */
    protected static function trans(string $key, array $parameters = [], ?string $domain = null): string
    {
        $translations = \Omnibridge\Runtime::find(\Omnibridge\Contract\Translations::class);

        return $translations instanceof \Omnibridge\Contract\Translations ? $translations->trans($key, $parameters, $domain) : $key;
    }
}
