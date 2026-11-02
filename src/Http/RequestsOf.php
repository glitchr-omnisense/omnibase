<?php

namespace Base\Http;

use Omnibridge\Contract\Requests;

/**
 * The request contract (Omnibridge\Contract\Requests) out of what a
 * constructor was given: the contract itself, or - deprecated until 6.0 -
 * Symfony's RequestStack, wrapped in omnibridge/symfony's StackRequests
 * (named as a string: the core never loads the bridge itself). Null stays
 * null: a service built outside any host reads no request.
 */
final class RequestsOf
{
    public const SYMFONY = 'Omnibridge\Symfony\Contract\StackRequests';

    public static function of(?object $requests): ?Requests
    {
        if (null === $requests || $requests instanceof Requests) {
            return $requests;
        }
        if (is_a($requests, 'Symfony\Component\HttpFoundation\RequestStack')) {
            $class = self::SYMFONY;
            if (!class_exists($class)) {
                throw new \LogicException(sprintf('%s is the request contract of Symfony\'s RequestStack: install omnibridge/symfony, or give an %s.', $class, Requests::class));
            }

            return new $class($requests);
        }

        throw new \InvalidArgumentException(sprintf('Expected %s (or Symfony\'s RequestStack, deprecated), got %s.', Requests::class, get_debug_type($requests)));
    }
}
