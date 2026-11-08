<?php

/*
 * Base\Service\Trading where florianv/swap is not installed (plan "omnibase agnostic", step S): run by docker/bin/bare
 * for tests/BareTest.php on the core alone (var/bare-alone/vendor), which has neither florianv/swap nor
 * symfony/http-client. omnisense/omnibridge's Omnibridge\Container, built from the core's whole declaration and
 * Base\Discovery's registries, declares no Trading; `trading_market` and TradingInterface are NullTrading (the
 * registries' `fallbacks`), on which the |format_currency logic (Base\View\Money) formats an amount in its own currency.
 */

use Base\Discovery;
use Base\Service\TradingInterface;
use Base\Services;
use Base\View\Money;
use Omnibridge\Container;

$definitions = Services::definitions();
$applying = array_map(Services::id(...), array_values(array_filter($definitions, static fn (array $definition): bool => Services::applies($definition))));
$container = Container::fromDefinitions($definitions, [], [], Discovery::registries());
$trading = $container->get('trading_market');

return [
    'installed' => ['florianv/swap' => class_exists('Swap\Builder'), 'symfony/http-client' => class_exists('Symfony\Component\HttpClient\Psr18Client')],
    'declared' => \in_array('Base\Service\Trading', $applying, true),
    'trading_market' => $trading::class,
    'contract' => $container->get(TradingInterface::class) === $trading,
    'formatted' => (new Money($trading))->formatCurrency(123456, 'EUR', [], 'en') === Money::currency(1234.56, 'EUR', [], 'en'),
    'converted' => $trading->convert(12.5, 'EUR', 'EUR'),
];
