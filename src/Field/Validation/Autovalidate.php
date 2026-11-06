<?php

namespace Base\Field\Validation;

use Base\Screen\Json;
use Hashids\Hashids;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Tokens;

/**
 * A field's validation asked while the form is filled (/ux/validation/{hashid}), written once for every host (plan
 * "omnibase agnostic", step F5e, slice 5b; formerly AutovalidateController): the payload - the token, the value, the
 * form's type - serialised and hidden by Hashids on the site's secret; answered "Fine" when its "validation" token is
 * the visitor's and the method the environment expects (GET in debug, POST otherwise), "Invalid request" (500)
 * otherwise. No check of the value is made yet.
 */
final class Autovalidate
{
    public const TOKEN = 'validation';

    private readonly Hashids $hashids;

    public function __construct(private readonly Environment $environment, private readonly Tokens $tokens)
    {
        $this->hashids = new Hashids($environment->secret());
    }

    /** @param array<string, mixed> $payload */
    public function encode(array $payload): string
    {
        return $this->hashids->encodeHex(bin2hex(serialize($payload)));
    }

    /** @return array<string, mixed> */
    public function decode(string $hash): array
    {
        $hex = $this->hashids->decodeHex($hash);
        $payload = $hex ? unserialize((string) hex2bin($hex), ['allowed_classes' => false]) : [];

        return \is_array($payload) ? $payload : [];
    }

    public function answer(string $hash, string $method): Json
    {
        $payload = $this->decode($hash);
        $expected = $this->environment->isDebug() ? 'GET' : 'POST';
        $token = $payload['token'] ?? null;
        if ($method === $expected && $this->tokens->isValid(self::TOKEN, \is_string($token) ? $token : null)) {
            return new Json(['status' => 'Fine']);
        }

        return new Json('Invalid request', 500);
    }
}
