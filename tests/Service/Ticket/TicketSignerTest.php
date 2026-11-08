<?php

namespace Tests\Base\Service\Ticket;

use Base\Service\Ticket\TicketSigner;
use PHPUnit\Framework\TestCase;

/** The tickets' format (Base\Service\Ticket\TicketSigner): what the services read, and what they refuse. */
class TicketSignerTest extends TestCase
{
    public function testAFixedPayloadSignsToAFixedTicket(): void
    {
        // The vector the services' own verifiers are checked against: base64url of the JSON, a dot, the hex HMAC.
        $ticket = TicketSigner::sign(['uid' => 2, 'room' => 'lobby', 'exp' => 2000000000], 'secret');
        $body = rtrim(strtr(base64_encode('{"uid":2,"room":"lobby","exp":2000000000}'), '+/', '-_'), '=');
        self::assertSame($body.'.'.hash_hmac('sha256', $body, 'secret'), $ticket);
    }

    public function testATicketVerifiesForItsRoomWithItsSecret(): void
    {
        $ticket = TicketSigner::sign(['uid' => 7, 'room' => 'lobby', 'exp' => time() + 60], 'secret');
        self::assertSame(7, TicketSigner::verify($ticket, 'secret', 'lobby')['uid'] ?? null);
    }

    public function testAnotherSecretAnotherRoomAnExpiryOrNoSecretAreRefused(): void
    {
        $ticket = TicketSigner::sign(['room' => 'lobby', 'exp' => time() + 60], 'secret');
        self::assertNull(TicketSigner::verify($ticket, 'other', 'lobby'));
        self::assertNull(TicketSigner::verify($ticket, 'secret', 'other-room'));
        self::assertNull(TicketSigner::verify($ticket, '', 'lobby'));
        self::assertNull(TicketSigner::verify(TicketSigner::sign(['room' => 'lobby', 'exp' => time() - 1], 'secret'), 'secret', 'lobby'));
        self::assertNull(TicketSigner::verify('no-dot', 'secret', 'lobby'));
    }

    public function testATamperedPayloadIsRefused(): void
    {
        $ticket = TicketSigner::sign(['uid' => 1, 'room' => 'lobby', 'exp' => time() + 60], 'secret');
        [$body, $mac] = explode('.', $ticket, 2);
        $forged = rtrim(strtr(base64_encode((string) json_encode(['uid' => 99, 'room' => 'lobby', 'exp' => time() + 60])), '+/', '-_'), '=');
        self::assertNull(TicketSigner::verify($forged.'.'.$mac, 'secret', 'lobby'));
    }
}
