<?php

namespace Base\Bridge\Symfony;

use Base\Contract\Environment;

/**
 * The Environment contract under Symfony (plan "omnibase agnostique", C4b):
 * the kernel's environment, debug and secret, the container's
 * kernel.environment, kernel.debug and kernel.secret - what BaseService's
 * getEnvironment(), isDebug() and getSalt() answered.
 */
final class KernelEnvironment implements Environment
{
    public function __construct(
        private readonly string $environment,
        private readonly bool $debug,
        #[\SensitiveParameter] private readonly string $secret,
    ) {
    }

    public function name(): string
    {
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function secret(): string
    {
        return $this->secret;
    }
}
