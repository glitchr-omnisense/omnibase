<?php

namespace Base\Request;

use Base\Entity\User;
use Base\Request\Rule\Locale;
use Base\Service\LocalizerInterface;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Access;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The language menu's choice (/locale/{_locale}), written once for every host (plan "omnibase agnostic", step
 * F5e, slice 5; formerly LocalizerController::Locale(), which omnibridge/laravel's SwitchLocale wrote again):
 * a language the site speaks, remembered in the LANG cookie (Rule\Locale reads it) and as a member's own -
 * not while impersonating -, then the page the visitor came from on this site, at its address in that
 * language when it has one; a language the site does not speak, the index; none given, the choice forgotten.
 */
final class LocaleSwitch
{
    public const COOKIE = Locale::COOKIE;
    public const ROUTE = '_switch_locale';
    public const MESSAGE = '@controllers.locale_changeto.action';

    public function __construct(
        private readonly LocalizerInterface $localizer,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?Access $access = null,
        private readonly ?UnitOfWork $unitOfWork = null,
    ) {
    }

    /** The locale chosen (as the localizer writes it), or null when the site does not speak this language. */
    public function choose(?string $requested): ?string
    {
        $available = array_merge($this->localizer::getAvailableLocaleLangs(), $this->localizer::getAvailableLocales());

        return \in_array($requested, $available, true) ? $this->localizer->getLocale($requested) : null;
    }

    /** The choice kept as a member's own (not while impersonating); what the visitor is told. */
    public function remember(string $requested, string $locale): string
    {
        $user = $this->access?->getUser();
        if ($user instanceof User && !$this->access->isGranted('IS_IMPERSONATOR')) {
            $user->setLocale($locale);
            $this->unitOfWork?->flush();
        }

        return (string) $this->translator?->trans(self::MESSAGE, [$this->localizer->getLocaleLangName($requested)]);
    }

    /** The path the visitor came from, when it is one of this site's (no host, or the request's). */
    public static function referred(?string $referer, string $host): ?string
    {
        if (null === $referer || '' === $referer) {
            return null;
        }
        $from = parse_url($referer, \PHP_URL_HOST);

        return !$from || $from === $host ? (parse_url($referer, \PHP_URL_PATH) ?: '/') : null;
    }

    /**
     * The routes to go back to, the first the host can generate: the referring page's route in the chosen
     * language ("name.en"), then its own name; none for no route, or the menu's own.
     *
     * @param array<string, mixed>|null $match the referring path's route as the host matched it (_route, _canonical_route, parameters)
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public function back(?array $match, string $requested): array
    {
        $name = $match['_canonical_route'] ?? $match['_route'] ?? null;
        if (!\is_string($name) || '' === $name || str_starts_with($name, self::ROUTE)) {
            return [];
        }
        foreach ($this->localizer::getAvailableLocaleLangs() as $available) {
            $name = str_ends_with($name, '.'.$available) ? substr($name, 0, -\strlen('.'.$available)) : $name;
        }
        $parameters = array_filter($match, static fn ($key) => !str_starts_with((string) $key, '_'), \ARRAY_FILTER_USE_KEY);

        return [[$name.'.'.$this->localizer->getLocaleLang($requested), $parameters], [$name, $parameters]];
    }
}
