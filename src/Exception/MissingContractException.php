<?php

namespace Base\Exception;

/**
 * A contract of the Fondation asked of Base\Contract\Runtime when no framework
 * filled it: loud, where a getter that found no service used to return null
 * in silence (plan "omnibase agnostique", the risk of the static locator).
 */
final class MissingContractException extends \LogicException
{
    public static function for(string $contract): self
    {
        return new self(sprintf('No %s in the runtime: the framework fills it at boot (omnibase\'s bundle under Symfony), or Base\Contract\Runtime::set() / setContainer() before it is asked.', $contract));
    }

    public static function notAn(string $contract, object $service): self
    {
        return new self(sprintf('The runtime\'s %s is a %s, which does not implement it.', $contract, get_debug_type($service)));
    }
}
