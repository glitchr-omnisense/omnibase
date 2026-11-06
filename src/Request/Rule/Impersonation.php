<?php

namespace Base\Request\Rule;

use Base\Request\Decision;

/**
 * The account seen through another's eyes (plan "omnibase agnostic", step F5e, slice 2; formerly
 * omnibridge/symfony's SecuritySubscriber::onKernelRequest()): while an administrator impersonates an
 * account, every page reminds them, naming the account and the query parameter that ends it.
 */
final class Impersonation
{
    public const PARAMETER = '_switch_user';

    public static function decide(object $user, bool $impersonating, ?string $parameter = null): Decision
    {
        return $impersonating
            ? Decision::continue()->withNotice('impersonator', [$user, $parameter ?? self::PARAMETER], 'warning')
            : Decision::continue();
    }
}
