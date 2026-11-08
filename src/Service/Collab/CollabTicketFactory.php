<?php

namespace Base\Service\Collab;

use Base\Contract\Tickets;
use Base\Service\Ticket\TicketSigner;

/**
 * This class creates a short-lived, signed ticket. A browser presents
 * this ticket to the collab relay (relay/src/server.js in editorjs-yjs), to join a
 * room's WebSocket connection. The relay checks the signature by itself.
 * The relay does not send requests to PHP. The relay does not read the
 * session store. Because of this, this class is the only place on this
 * side that uses the shared secret.
 *
 * Signed by Base\Service\Ticket\TicketSigner, the format the relay's own
 * verifyTicket() reads (base64url(payload) . "." . hex(hmac_sha256)); the
 * contract it answers is Base\Contract\Tickets.
 */
class CollabTicketFactory implements Tickets
{
    protected string $secret;
    protected string $wsUrl;
    protected int $ttl;

    /**
     * $ttl is the ticket lifetime in seconds.
     *
     * 300, not 60: a ticket is only presented on the WebSocket handshake, and
     * the client refreshes it well inside this window, so a short ttl bounds
     * exposure of a leaked ticket without any legitimate client ever needing
     * to stretch it. 60 was too tight in practice - the client's refresh timer
     * runs at 45s, and browsers clamp timers in background tabs to once a
     * minute (suspending them entirely while the machine sleeps), so the
     * refresh routinely slipped past a 60s ttl and the following reconnect
     * presented an expired ticket, which the relay rejects with 401. The
     * client now also refreshes on socket close and on tab focus, so this
     * margin is defence in depth rather than the only guard.
     */
    public function __construct(string $secret, string $wsUrl = "", int $ttl = 300)
    {
        $this->secret = $secret;
        $this->wsUrl = $wsUrl;
        $this->ttl = $ttl;
    }

    /**
     * An operator can deploy a relay with a secret value, but with no
     * public WebSocket URL yet. The opposite case can also occur during
     * setup. The `collab_live` option needs both values. Because of
     * this, a caller must check this method before it activates the
     * `collab_live` option for a field.
     */
    public function isConfigured(): bool
    {
        return $this->secret !== "" && $this->wsUrl !== "";
    }

    public function getWsUrl(): string
    {
        return $this->wsUrl;
    }

    /**
     * @param array{id: mixed, name: ?string, avatar: ?string, color: ?string} $user
     */
    public function mint(string $room, array $user): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $payload = [
            "uid"    => $user["id"] ?? null,
            "name"   => $user["name"] ?? null,
            "avatar" => $user["avatar"] ?? null,
            "color"  => $user["color"] ?? null,
            "room"   => $room,
            "exp"    => time() + $this->ttl,
        ];

        return TicketSigner::sign($payload, $this->secret);
    }

    /**
     * This method checks a service-to-service token. The relay signs
     * this token by itself. This token uses the same HMAC format as the
     * mint() method, with payload {room, exp} and with no user fields.
     * The relay sends this token when the relay calls
     * ux_editorjs_autosave, to save a room's content. The relay process
     * is not a browser. Because of this, no Symfony session exists for
     * that call. This method reuses the same shared secret from the
     * other direction, instead of a second secret. This design uses one
     * secret for symmetric use on both sides.
     */
    public function verifyServiceToken(string $token, string $room): bool
    {
        // This method does not call isConfigured() here. A check of a
        // service call needs only the shared secret. The public wsUrl
        // value controls a different, stricter condition: readiness to
        // advertise the collab_live option to a browser.
        return TicketSigner::verify($token, $this->secret, $room) !== null;
    }
}
