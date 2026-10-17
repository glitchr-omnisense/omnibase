<?php

namespace Base\Screen;

/**
 * What the view models share to give their props: a screen, a list of them,
 * anything else as it is.
 *
 * @internal
 */
abstract class Shape
{
    public static function export(mixed $value): mixed
    {
        if ($value instanceof Screen) {
            return $value->toArray();
        }
        if (\is_array($value)) {
            return array_map(self::export(...), $value);
        }

        return $value;
    }

    /**
     * A moment as the view models carry it: ISO 8601 in UTC, as omnibase keeps
     * it in the database (lesringostars' Frame::moment()); null stays null.
     */
    public static function moment(\DateTimeInterface|string|null $moment): ?string
    {
        if (null === $moment || '' === $moment) {
            return null;
        }
        $moment = \is_string($moment) ? new \DateTimeImmutable($moment, new \DateTimeZone('UTC')) : \DateTimeImmutable::createFromInterface($moment);

        return $moment->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM);
    }
}
