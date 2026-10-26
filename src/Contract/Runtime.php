<?php

namespace Base\Contract;

use Base\Service\ParameterBagInterface;
use Omnibridge\Runtime as HostRuntime;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The registry through which omnibase's framework-free code reaches its
 * contracts (plan "omnibase agnostique", C4): an entity's getter, an
 * attribute, a value object asks Runtime::urls(), ::access(), ::nouns()...
 * instead of the services Base\Traits\BaseTrait hands out.
 *
 * Since A2, omnisense/omnibridge's registry (Omnibridge\Runtime: the same
 * state, the same container, filled once at boot by the host) with
 * omnibase's own contracts beside the host's. From omnibridge, generic to any
 * host: Urls (path, url, asset), Translations (trans, ICU, exists()), Locales,
 * Access (isGranted, the user), Sessions (a user signed out), Tokens (a form's
 * token for an intent), Files (public URL, local path of a stored file),
 * Routes (a route's declared path, the route of an address, its icons),
 * Environment (its name, debug, the secret), PSR-20's clock (the machine's,
 * SystemClock, when none was given) and PSR-14's event dispatcher. omnibase's
 * own, here: the application's parameters (Base\Service\ParameterBagInterface,
 * C4b), Nouns (what an entity is called, the rule of
 * Base\Service\Model\EntityNouns), Media (the image route's addresses, C4b),
 * Pictures (an image's address at a size) and Texts (a stored text rendered,
 * its table of contents) for the screens (C5), and symfony/mailer's
 * MailerInterface (a standalone component, allowed in the Foundation by D1;
 * Laravel's own mail is built on it).
 *
 * Under Symfony, omnibase's bundle hands its `base.contracts` locator in
 * BaseBundle::boot() (Base\DependencyInjection\Compiler\Pass\ContractsPass);
 * under Laravel, omnibridge/laravel hands the application. A contract asked
 * and not filled throws MissingContractException; find() answers null
 * instead, for a caller that can do without.
 */
final class Runtime extends HostRuntime
{
    /**
     * The application's parameters (C4b), read by a dotted path; under Symfony
     * the container's with the settings stored in the database (parameter_bag).
     */
    public static function parameters(): ParameterBagInterface
    {
        return self::get(ParameterBagInterface::class);
    }

    /** What an entity is called (C4b): its noun, singular or plural, from the "entities" catalogues. */
    public static function nouns(): Nouns
    {
        return self::get(Nouns::class);
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

    public static function mailer(): MailerInterface
    {
        return self::get(MailerInterface::class);
    }
}
