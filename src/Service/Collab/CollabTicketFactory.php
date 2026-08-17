<?php

namespace Base\Service\Collab;

/**
 * Mints the short-lived signed ticket a browser presents to the collab
 * relay (collab-relay/src/server.js) to join a room's WebSocket — the relay
 * verifies the signature itself and never talks to PHP/the session store,
 * so this is the only place the shared secret is used on this side.
 *
 * Wire format — base64url(payload) . "." . hex(hmac_sha256(payload, secret))
 * — was fixed by reading the relay's own verifyTicket() first, not designed
 * independently on each side and hoped to match.
 */
class CollabTicketFactory
{
    protected string $secret;
    protected string $wsUrl;
    protected int $ttl;

    public function __construct(string $secret, string $wsUrl = "", int $ttl = 60)
    {
        $this->secret = $secret;
        $this->wsUrl = $wsUrl;
        $this->ttl = $ttl;
    }

    /**
     * A relay may be deployed (secret set) without a public WS URL being
     * known yet, or vice versa during setup — both are required for
     * collab_live to actually work, so callers should check this rather
     * than isConfigured() alone before advertising the feature to a field.
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

        $payloadB64 = $this->base64urlEncode((string) json_encode($payload));
        $signature = hash_hmac("sha256", $payloadB64, $this->secret);

        return $payloadB64 . "." . $signature;
    }

    /**
     * Verifies a service-to-service token the relay signs itself (same
     * HMAC wire format as mint(), payload {room, exp}, no user fields)
     * when it calls back into ux_editorjs_autosave to persist a room's
     * content — there's no Symfony session to reuse for that call since
     * it originates from the relay process, not a browser, so this reuses
     * the same shared secret from the other direction instead of a second
     * one — one secret, symmetric use on both sides.
     */
    public function verifyServiceToken(string $token, string $room): bool
    {
        // Deliberately not isConfigured(): verifying a service call only
        // needs the shared secret, not the public wsUrl (which is about
        // whether collab_live is ready to be *advertised* to a browser —
        // a different, stricter condition).
        if ($this->secret === "") {
            return false;
        }

        $dot = strrpos($token, ".");
        if ($dot === false) {
            return false;
        }

        $payloadB64 = substr($token, 0, $dot);
        $signature = substr($token, $dot + 1);

        $expected = hash_hmac("sha256", $payloadB64, $this->secret);
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $payload = json_decode($this->base64urlDecode($payloadB64), true);
        if (!is_array($payload) || ($payload["exp"] ?? 0) < time()) {
            return false;
        }

        return ($payload["room"] ?? null) === $room;
    }

    protected function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    protected function base64urlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, "-_", "+/"), false);
    }
}
