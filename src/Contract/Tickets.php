<?php

namespace Base\Contract;

/**
 * Short-lived signed tickets to a service that checks them by itself (a WebSocket relay, a game server): a browser or
 * a client asks the application for one, presents it to the service, and the service verifies the signature with the
 * secret they share, never calling the application back. What it says (who, what room, what rights) is each factory's
 * own, so `mint` is not part of the contract; how it is signed is Base\Service\Ticket\TicketSigner's, the one format
 * the services read.
 *
 * Implemented by Base\Service\Collab\CollabTicketFactory (the editors' collaboration relay) and by an application's
 * own factories, one a service, each with its own secret.
 */
interface Tickets
{
    /** Whether tickets can be given: a secret and the service's public address are both set. */
    public function isConfigured(): bool;

    /** The service's public address, where a ticket is presented. */
    public function getWsUrl(): string;

    /** Whether a token the service signed itself (a report, an autosave) holds for a room or scope: signature, expiry, room. */
    public function verifyServiceToken(string $token, string $room): bool;
}
