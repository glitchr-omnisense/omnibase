<?php

namespace Base\Service\Model;

use Omnibridge\Contract\Environment;

/**
 * Whether the application runs for its developers (plan "omnibase agnostique",
 * C9): BaseService::isDevelopment()'s rule, moved here so that a class that
 * needs it asks the Environment contract instead of BaseService - debug on, or
 * an environment named "dev", "local", or starting with "dev_" or "local_".
 */
final class DevelopmentEnvironment
{
    public static function is(Environment $environment): bool
    {
        return self::named($environment->name(), $environment->isDebug());
    }

    public static function named(string $name, bool $debug = false): bool
    {
        return $debug
            || $name === 'dev' || str_starts_with($name, 'dev_')
            || $name === 'local' || str_starts_with($name, 'local_');
    }
}
