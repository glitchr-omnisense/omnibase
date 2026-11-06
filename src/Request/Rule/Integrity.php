<?php

namespace Base\Request\Rule;

use Base\Database\Attribute\Vault;
use Base\Request\Decision;

/**
 * The session's integrity (plan "omnibase agnostic", step F5e, slice 2; formerly omnibridge/symfony's
 * IntegritySubscriber, which no configuration registers): the session keeps the application's secret sealed
 * by the vault and a checksum of the database's connection; a session whose secret or connection no longer
 * matches, or whose account the host finds damaged, is signed out (told so), its remember-me cookie cleared,
 * and sent to the rescue sign-in, which itself is never checked.
 */
final class Integrity
{
    public const SESSION_SECRET = '_integrity/secret';
    public const SESSION_DATABASE = '_integrity/doctrine';
    /** The rescue sign-in (omnibridge/symfony's RescueFormAuthenticator::LOGIN_ROUTE): never checked, and where a broken session is sent. */
    public const ROUTE = 'security_rescue';
    public const REMEMBER_ME = 'REMEMBERME';

    private readonly Vault $vault;

    public function __construct(private readonly ?string $secret = null, ?Vault $vault = null)
    {
        $this->vault = $vault ?? new Vault();
    }

    /** The secret as the session keeps it. */
    public function seal(): mixed
    {
        return $this->vault->seal($this->vault->getMarshaller(), $this->secret);
    }

    public function secretIntact(mixed $sealed): bool
    {
        if (null === $this->secret || null === ($marshaller = $this->vault->getMarshaller())) {
            return true;
        }

        return $sealed && $this->secret == $this->vault->reveal($marshaller, $sealed);
    }

    /** @param array<string, mixed> $parameters the connection's (driver, user, host, port, dbname, charset) */
    public static function checksum(array $parameters): string
    {
        if (!($host = $parameters['host'] ?? '')) {
            return '';
        }
        $part = static fn (string $key, string $before = '', string $after = '') => ($parameters[$key] ?? null) ? $before.$parameters[$key].$after : '';

        return md5($part('driver', '', '://').$part('user', '', '@').$host.$part('port', ':').$part('dbname', '/').$part('charset', ' (', ')'));
    }

    public static function databaseIntact(bool $signedIn, ?string $kept, string $checksum): bool
    {
        return !$signedIn || ($kept && $checksum == $kept);
    }

    public function decide(bool $intact, ?object $user, ?string $route): Decision
    {
        if ($intact || self::ROUTE === $route) {
            return Decision::continue();
        }
        $decision = Decision::redirect(self::ROUTE)->withSignOut()->withCookie(self::REMEMBER_ME, null);

        return null !== $user ? $decision->withNotice('integrity', [$user], 'danger') : $decision;
    }
}
