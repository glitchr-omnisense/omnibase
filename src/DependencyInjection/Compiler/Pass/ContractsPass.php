<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Contract\Access;
use Base\Contract\Files;
use Base\Contract\Locales;
use Base\Contract\Urls;
use Omnibridge\Twig\Contract\Translations;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The locator Base\Contract\Runtime reaches the contracts of the Fondation
 * through (plan "omnibase agnostique", C4), handed to it by
 * BaseBundle::boot(): each contract by its interface's name, the service the
 * container binds to that name - omnibase's adapters (Base\Bridge\Symfony\*,
 * config/services/core.php), the framework's clock, event dispatcher and
 * mailer, or an application's own binding, which wins as any alias does.
 *
 * Only the contracts whose service exists are listed (no clock without
 * symfony/clock, no mailer without symfony/mailer configured): the registry
 * says it has none rather than failing when the service is built. A locator
 * holds closures: nothing is constructed before a contract is asked.
 */
final class ContractsPass implements CompilerPassInterface
{
    public const SERVICE = 'base.contracts';

    /** @var list<class-string> */
    public const CONTRACTS = [
        Urls::class,
        Translations::class,
        Access::class,
        Locales::class,
        Files::class,
        ClockInterface::class,
        EventDispatcherInterface::class,
        MailerInterface::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        $contracts = [];
        foreach (self::CONTRACTS as $contract) {
            if ($container->has($contract)) {
                $contracts[$contract] = new Reference($contract);
            }
        }

        $container->register(self::SERVICE, ServiceLocator::class)
            ->setPublic(true)
            ->addTag('container.service_locator')
            ->setArguments([$contracts]);
    }
}
