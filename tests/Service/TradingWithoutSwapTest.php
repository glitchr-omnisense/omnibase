<?php

namespace Tests\Base\Service;

use Base\Discovery;
use Base\Service\NullTrading;
use Base\Service\Trading;
use Base\Service\TradingInterface;
use Base\Services;
use Base\Twig\Extension\TradingTwigExtension;
use Base\View\Money;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\DiscoveryPass;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Base\Service\Trading where florianv/swap is not installed (plan "omnibase agnostic", step S). Since 5.0 florianv/swap
 * is a suggestion; Trading was declared on symfony/http-client's Psr18Client alone, so a site with the HTTP client and
 * without florianv/swap (and php-http/discovery, which it brings) had Trading built at boot, and the kernel stopped on
 * Psr18Client's "no PSR-17 factories". Trading now requires florianv/swap too; without it `trading_market` and
 * TradingInterface are NullTrading (Base\Discovery's `fallbacks`), so what asks for them - the Twig filters, the money
 * field, the facade - is still built, and formats an amount in its own currency.
 *
 * Symfony's container here, from the core's declaration and its registries as omnibridge/symfony compiles them;
 * BareTest::testTheRatesServiceIsNullTradingWithoutFlorianvSwap on the core alone, where florianv/swap is not installed.
 */
final class TradingWithoutSwapTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function declaration(): array
    {
        foreach (Services::group('media') as $declaration) {
            if (Trading::class === Services::id($declaration)) {
                return $declaration;
            }
        }
        self::fail('Base\Service\Trading is declared in the group "media".');
    }

    private static function fallbacks(ContainerBuilder $container): ContainerBuilder
    {
        (new DiscoveryPass(Discovery::registries(), ['fallbacks']))->process($container);

        return $container;
    }

    public function testTradingIsDeclaredOnlyWithFlorianvSwapAndTheHttpClient(): void
    {
        $requires = self::declaration()['requires'];
        self::assertContains('Swap\Builder', $requires);
        self::assertContains('Symfony\Component\HttpClient\Psr18Client', $requires);

        // Both are installed here (the core's require-dev): declared as before, and the fallbacks leave it.
        $container = new ContainerBuilder();
        Declarations::group($container, 'media');
        self::fallbacks($container);
        self::assertTrue($container->hasDefinition(Trading::class));
        self::assertSame(Trading::class, (string) $container->getAlias('trading_market'));
        self::assertSame('trading_market', (string) $container->getAlias(TradingInterface::class));
    }

    public function testWithoutTradingWhatAsksForTheRatesIsGivenNullTrading(): void
    {
        // No Trading declared, as where florianv/swap is not installed. The Twig filters ask `trading_market` (the
        // declaration of the group "twig"), an autowired service the contract.
        $container = new ContainerBuilder();
        $container->setDefinition('filters', (new Definition(TradingTwigExtension::class, [new Reference('trading_market')]))->setPublic(true));
        $container->setDefinition('autowired', (new Definition(Money::class))->setAutowired(true)->setPublic(true));

        self::fallbacks($container);
        self::assertSame(NullTrading::class, $container->findDefinition('trading_market')->getClass());
        self::assertSame(NullTrading::class, $container->findDefinition(TradingInterface::class)->getClass());

        $container->compile();
        self::assertSame(Money::currency(1234.56, 'EUR', [], 'en'), $container->get('filters')->formatCurrency(123456, 'EUR', [], 'en'));
        self::assertSame(Money::currency(1234.56, 'EUR', [], 'en'), $container->get('autowired')->formatCurrency(123456, 'EUR', [], 'en'));
    }

    public function testAnApplicationsOwnRatesServiceIsNeverReplaced(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('trading_market', new Definition('App\Trading'));

        self::fallbacks($container);

        self::assertSame('App\Trading', $container->getDefinition('trading_market')->getClass());
        self::assertSame('trading_market', (string) $container->getAlias(TradingInterface::class));
    }

    public function testNullTradingKnowsNoRate(): void
    {
        $trading = new NullTrading();
        $trading->setRenderedCurrency('USD');

        // Formatted in its own currency, the visitor's asked for: there is no rate to it.
        self::assertSame(Money::currency(1234.56, 'EUR', [], 'en'), (new Money($trading))->formatCurrency(123456, 'EUR', [], 'en'));
        self::assertNull((new Money($trading))->applyCurrencyRate(123456, 'EUR'));
        self::assertSame([], $trading->getFallback('EUR'));
        self::assertNull($trading->getFallback('EUR', 'USD'));
        self::assertNull($trading->get('EUR', 'USD'));
        self::assertFalse($trading->isLive());

        // Converted into itself only (an order's total in the shop's currency); into another, refused as Trading refuses.
        self::assertSame(12.5, $trading->convert(12.5, 'EUR', 'EUR'));
        self::assertSame(12.5, $trading->convertFallback('12.5', 'EUR', 'EUR'));
        self::assertNull($trading->convertLatest(12.5, 'EUR', 'USD'));
        try {
            $trading->convert(12.5, 'EUR', 'USD');
            self::fail('No rate, no conversion.');
        } catch (\Exception $e) {
            self::assertStringContainsString('florianv/swap', $e->getMessage());
        }

        // A rate handed to it (omnibase/marketplace's table) is refused aloud, never dropped: the amounts would be wrong.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Require florianv/swap');
        $trading->addFallback('EUR', 'USD', 1.08, 'now');
    }
}
