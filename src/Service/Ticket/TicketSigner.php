<?php

namespace Base\Service\Ticket;

/**
 * The tickets' one format: base64url(json payload) . "." . hex(hmac_sha256(that base64url, secret)). Every service
 * that checks tickets by itself reads exactly this, so it is written once, here, rather than in each factory - two
 * copies checking a signature two slightly different ways is the bug nobody finds. The payload always names its room
 * and when it expires (`room`, `exp`, a Unix time); the rest is the factory's.
 *
 * Pure: no state, no framework. Used by Base\Contract\Tickets implementations (Base\Service\Collab\CollabTicketFactory
 * and an application's own).
 */
final class TicketSigner
{
    /** @param array<string, mixed> $payload */
    public static function sign(array $payload, string $secret): string
    {
        $body = self::base64url((string) json_encode($payload));

        return $body.'.'.hash_hmac('sha256', $body, $secret);
    }

    /**
     * A token's payload when its signature holds, it has not expired and it is for this room; null otherwise (an empty
     * secret never verifies anything).
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $token, string $secret, string $room): ?array
    {
        if ('' === $secret) {
            return null;
        }
        $dot = strrpos($token, '.');
        if (false === $dot) {
            return null;
        }
        $body = substr($token, 0, $dot);
        if (!hash_equals(hash_hmac('sha256', $body, $secret), substr($token, $dot + 1))) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($body, '-_', '+/'), false), true);
        if (!is_array($payload) || ($payload['room'] ?? null) !== $room || (int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
