<?php

namespace Base\View;

use Base\Service\LocalizerInterface;
use Base\Service\ThemizerInterface;
use Omnibridge\Contract\Locales;

/**
 * The visitor's menus of a page as data (plan "omnibase agnostic", step F4): the choices a menu
 * offers and the one the visitor made - its language (the host's Locales contract), its theme's
 * modes, filters and widths (omnibase's Themizer), its time zone and country (omnibase's
 * Localizer). Twig renders them with omnibase's dropdown templates (render_locale(),
 * render_theme_modes()..., MenuTwigExtension and LocalizerTwigExtension); Blade renders the same
 * data, a React page takes it as props.
 */
final class Menus
{
    public function __construct(
        private readonly ?Locales $locales = null,
        private readonly ?ThemizerInterface $themizer = null,
        private readonly ?LocalizerInterface $localizer = null,
    ) {
    }

    /** @return array{available_locales: list<string>, current_locale: string} */
    public function locale(): array
    {
        return [
            'available_locales' => $this->required($this->locales, Locales::class)->getAvailableLocales(),
            'current_locale' => $this->locales->getLocale(),
        ];
    }

    /** @return array{available_theme_modes: array<array-key, mixed>, current_theme_mode: array<array-key, mixed>|null} */
    public function modes(): array
    {
        return [
            'available_theme_modes' => $this->required($this->themizer, ThemizerInterface::class)->getAvailableModes(),
            'current_theme_mode' => $this->themizer->getMode(),
        ];
    }

    /** @return array{available_theme_filters: array<array-key, mixed>, current_theme_filter: array<array-key, mixed>|null} */
    public function filters(): array
    {
        return [
            'available_theme_filters' => $this->required($this->themizer, ThemizerInterface::class)->getAvailableFilters(),
            'current_theme_filter' => $this->themizer->getFilter(),
        ];
    }

    /** @return array{available_theme_widths: array<array-key, mixed>, current_theme_width: array<array-key, mixed>|null} */
    public function widths(): array
    {
        return [
            'available_theme_widths' => $this->required($this->themizer, ThemizerInterface::class)->getAvailableWidths(),
            'current_theme_width' => $this->themizer->getWidth(),
        ];
    }

    /** @return array{available_timezones: array<array-key, mixed>, current_timezone: string} */
    public function timezone(): array
    {
        return [
            'available_timezones' => $this->required($this->localizer, LocalizerInterface::class)->getAvailableTimezones(),
            'current_timezone' => $this->localizer->getTimezone(),
        ];
    }

    /** @return array{available_countries: array<array-key, mixed>, current_country: string} */
    public function country(): array
    {
        return [
            'available_countries' => $this->required($this->localizer, LocalizerInterface::class)->getAvailableLocaleCountries(),
            'current_country' => $this->localizer->getLocaleCountry(),
        ];
    }

    /**
     * @template T of object
     *
     * @param T|null          $service
     * @param class-string<T> $class
     *
     * @return T
     */
    private function required(?object $service, string $class): object
    {
        return $service ?? throw new \LogicException(sprintf('This menu needs %s, which was not given to %s.', $class, self::class));
    }
}
