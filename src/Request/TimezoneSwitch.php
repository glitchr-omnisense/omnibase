<?php

namespace Base\Request;

use Base\Entity\User;
use Omnibridge\Contract\Access;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The visitor's time zone chosen (/timezone/{_timezone}), written once for every host (plan "omnibase agnostic", step
 * F5e, slice 5b; formerly LocalizerController::Timezone(), which read a `_locale` its path never carried: it only
 * ever went to the index, after a deprecated setcookie(null) and a match of an empty referrer). A zone PHP knows
 * ("Europe/Paris", "UTC") is remembered in LOCATION/TIMEZONE - the cookie the page's script writes and
 * UserInfoTrait reads - and as a member's own (not while impersonating), then the visitor goes back to the page
 * they came from on this site; a zone it does not know, the index, nothing changed; none given, the choice
 * forgotten.
 */
final class TimezoneSwitch
{
    public const COOKIE = User::__TIMEZONE_IDENTIFIER__;
    public const ROUTE = '_switch_timezone';

    public function __construct(
        private readonly ?Access $access = null,
        private readonly ?UnitOfWork $unitOfWork = null,
    ) {
    }

    /** The zone chosen, or null when PHP knows no such zone. */
    public static function choose(?string $requested): ?string
    {
        return null !== $requested && \in_array($requested, \DateTimeZone::listIdentifiers(), true) ? $requested : null;
    }

    /** The zone kept as a member's own (not while impersonating). */
    public function remember(string $timezone): void
    {
        $user = $this->access?->getUser();
        if ($user instanceof User && !$this->access->isGranted('IS_IMPERSONATOR')) {
            $user->setTimezone($timezone);
            $this->unitOfWork?->flush();
        }
    }

    /** Where the visitor goes back to: the path they came from on this site (LocaleSwitch::referred()), else null (the index). */
    public static function back(?string $referer, string $host): ?string
    {
        $path = LocaleSwitch::referred($referer, $host);

        return null !== $path && !str_starts_with($path, '/timezone') ? $path : null;
    }
}
