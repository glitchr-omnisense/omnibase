<?php

namespace Base\Contract;

/**
 * What the application runs as (plan "omnibase agnostique", C4b): its
 * environment's name ("prod", "dev", "test" - the vault a secured entity is
 * sealed for), whether it runs in debug, and its secret (the salt of the
 * hashed identifiers a token carries). An entity asks it instead of
 * BaseTrait's getEnvironment() and BaseService's isDebug() and getSalt().
 *
 * The Fondation's own contract, reached by Runtime::environment(). Under
 * Symfony, Base\Bridge\Symfony\KernelEnvironment: the kernel's environment,
 * debug and secret (kernel.environment, kernel.debug, kernel.secret). The
 * application's other parameters are Base\Service\ParameterBagInterface,
 * reached by Runtime::parameters().
 */
interface Environment
{
    /** The environment's name: "prod", "dev", "test"... */
    public function name(): string;

    public function isDebug(): bool;

    /** The application's secret, which salts what it hashes. */
    public function secret(): string;
}
