<?php

namespace Base\Field\Search;

use Base\Screen\Json;
use Base\Service\ObfuscatorInterface;
use Base\Service\TradingInterface;
use Omnibridge\Contract\Tokens;

/**
 * A money field's exchange rate (/ux/autocomplete/currency/{source}/{target}/{data}), written once for every host
 * (plan "omnibase agnostic", step F5e, slice 5b; formerly AutocompleteController::Forex()): the payload the field
 * hands (obfuscated, short form) names the token and its intent, checked first; then the stored rate only - a
 * visitor's request never spends the provider's quota, whether the application's Trading is live or not.
 */
final class ExchangeRate
{
    public const ROUTE = 'ux_autocomplete_forex';

    public function __construct(
        private readonly ObfuscatorInterface $obfuscator,
        private readonly Tokens $tokens,
        private readonly TradingInterface $trading,
    ) {
    }

    public function rate(string $source, string $target, string $data): Json
    {
        $payload = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT) ?? [];
        $intent = $payload['token_name'] ?? null;
        $token = $payload['token'] ?? null;
        if (!\is_string($intent) || '' === $intent || !$this->tokens->isValid($intent, \is_string($token) ? $token : null)) {
            return new Json('Invalid token. Please refresh the page and try again', 500);
        }

        $source = strtoupper($source);
        $target = strtoupper($target);
        $rate = $this->trading->getFallback($source, $target);
        if (!\is_object($rate)) {
            return new Json('No rate stored for '.$source.'/'.$target, 404);
        }

        return new Json(['source' => $source, 'target' => $target, 'rate' => $rate->getValue(), 'date' => $rate->getDate()->format(\DATE_ATOM)]);
    }
}
