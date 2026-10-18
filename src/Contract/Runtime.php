<?php

namespace Base\Contract;

use Base\Exception\MissingContractException;
use Base\Service\ParameterBagInterface;
use Omnibridge\Twig\Contract\Translations;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The registry through which omnibase's framework-free code reaches the
 * contracts of the Fondation (plan "omnibase agnostique", C4): an entity's
 * getter, an attribute, a value object asks Runtime::urls(), ::access()...
 * instead of the services Base\Traits\BaseTrait hands out (the router, the
 * authorization checker, the Localizer, typed with Symfony's classes).
 *
 * Each contract is asked by its interface's name. A framework fills the
 * registry once, at boot:
 *
 * - with a PSR-11 container whose ids are those names (setContainer()): under
 *   Symfony, omnibase's bundle hands its `base.contracts` locator in
 *   BaseBundle::boot() (Base\DependencyInjection\Compiler\Pass\ContractsPass:
 *   only the contracts whose service exists, nothing built before it is
 *   asked); under Laravel, the application itself, its bindings named by the
 *   same interfaces;
 * - or one service at a time (set()), which wins over the container's.
 *
 * The contracts: Environment (its name, debug, the secret, C4b), the
 * application's parameters (Base\Service\ParameterBagInterface, C4b), Urls
 * (path, url, asset), Routes (a route's declared path, the route of an
 * address, a route's icons, C4b), omnibridge/twig's Translations (trans, ICU)
 * and omnibase's, which asks exists() too (C4b), Nouns (what an entity is
 * called, the rule of Base\Service\Model\EntityNouns), Access (isGranted, the
 * user), Sessions (a user signed out, C4b), Locales (current, default, available), Files (public URL, local path
 * of a stored file), Media (the image route's addresses, C4b), Pictures (an
 * image's address at a size), Texts (a stored text rendered, its table of
 * contents) and Tokens (a form's token for an intent) for the screens (C5),
 * PSR-20's clock,
 * PSR-14's event dispatcher, symfony/mailer's MailerInterface (a standalone
 * component, allowed in the Fondation by D1; Laravel's own mail is built on
 * it). A contract asked and not filled throws MissingContractException,
 * except the clock, which is the machine's (SystemClock) when none was given;
 * find() answers null instead, for a caller that can do without.
 *
 * A different container means a different kernel: what was resolved from the
 * previous one is forgotten (as BaseCommonTrait::setRuntime() does), the
 * services set by hand are kept.
 */
final class Runtime
{
    private static ?ContainerInterface $container = null;

    /** @var array<string, object> set by hand, ahead of the container */
    private static array $given = [];

    /** @var array<string, object> resolved from the container, once each */
    private static array $resolved = [];

    public static function setContainer(?ContainerInterface $container): void
    {
        if ($container !== self::$container) {
            self::$resolved = [];
        }
        self::$container = $container;
    }

    public static function getContainer(): ?ContainerInterface
    {
        return self::$container;
    }

    /**
     * One contract's service, ahead of the container's; null takes it back.
     *
     * @param class-string $contract
     */
    public static function set(string $contract, ?object $service): void
    {
        if (null === $service) {
            unset(self::$given[$contract], self::$resolved[$contract]);

            return;
        }
        if (!$service instanceof $contract) {
            throw MissingContractException::notAn($contract, $service);
        }
        self::$given[$contract] = $service;
    }

    /** @param class-string $contract */
    public static function has(string $contract): bool
    {
        return isset(self::$given[$contract]) || isset(self::$resolved[$contract]) || (bool) self::$container?->has($contract);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $contract
     *
     * @return T|null
     */
    public static function find(string $contract): ?object
    {
        if (isset(self::$given[$contract])) {
            return self::$given[$contract];
        }
        if (isset(self::$resolved[$contract])) {
            return self::$resolved[$contract];
        }
        if (!self::$container?->has($contract)) {
            return null;
        }

        $service = self::$container->get($contract);
        if (!$service instanceof $contract) {
            throw MissingContractException::notAn($contract, $service);
        }

        return self::$resolved[$contract] = $service;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $contract
     *
     * @return T
     */
    public static function get(string $contract): object
    {
        return self::find($contract) ?? throw MissingContractException::for($contract);
    }

    /** Everything forgotten: no container, nothing set (a test's tearDown). */
    public static function reset(): void
    {
        self::$container = null;
        self::$given = [];
        self::$resolved = [];
    }

    public static function urls(): Urls
    {
        return self::get(Urls::class);
    }

    /** What the application runs as (C4b): its environment's name, debug, its secret. */
    public static function environment(): Environment
    {
        return self::get(Environment::class);
    }

    /**
     * The application's parameters (C4b), read by a dotted path; under Symfony
     * the container's with the settings stored in the database (parameter_bag).
     */
    public static function parameters(): ParameterBagInterface
    {
        return self::get(ParameterBagInterface::class);
    }

    /** What the routes are beyond their addresses (C4b): a route's path, an address's route, a route's icons. */
    public static function routes(): Routes
    {
        return self::get(Routes::class);
    }

    public static function translations(): Translations
    {
        return self::get(Translations::class);
    }

    /** What an entity is called (C4b): its noun, singular or plural, from the "entities" catalogues. */
    public static function nouns(): Nouns
    {
        return self::get(Nouns::class);
    }

    public static function access(): Access
    {
        return self::get(Access::class);
    }

    /** The visitor's session (C4b): a user signed out when it is the one signed in. */
    public static function sessions(): Sessions
    {
        return self::get(Sessions::class);
    }

    public static function locales(): Locales
    {
        return self::get(Locales::class);
    }

    public static function files(): Files
    {
        return self::get(Files::class);
    }

    /** The image route's addresses for a stored image (C4b): its extension, its data, its link. */
    public static function media(): Media
    {
        return self::get(Media::class);
    }

    public static function pictures(): Pictures
    {
        return self::get(Pictures::class);
    }

    public static function texts(): Texts
    {
        return self::get(Texts::class);
    }

    public static function tokens(): Tokens
    {
        return self::get(Tokens::class);
    }

    /** The framework's clock, or the machine's when none was given. */
    public static function clock(): ClockInterface
    {
        return self::find(ClockInterface::class) ?? self::$resolved[ClockInterface::class] = new SystemClock();
    }

    public static function events(): EventDispatcherInterface
    {
        return self::get(EventDispatcherInterface::class);
    }

    public static function mailer(): MailerInterface
    {
        return self::get(MailerInterface::class);
    }
}
