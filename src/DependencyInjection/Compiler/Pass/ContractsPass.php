<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Contract\Access;
use Base\Contract\Environment;
use Base\Contract\Files;
use Base\Contract\Locales;
use Base\Contract\Media;
use Base\Contract\Nouns;
use Base\Contract\Pictures;
use Base\Contract\Routes;
use Base\Contract\Texts;
use Base\Contract\Tokens;
use Base\Contract\Translations as BaseTranslations;
use Base\Contract\Urls;
use Base\Service\ParameterBagInterface;
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
        Routes::class,
        Environment::class,
        ParameterBagInterface::class,
        Translations::class,
        BaseTranslations::class,
        Nouns::class,
        Access::class,
        Locales::class,
        Files::class,
        Pictures::class,
        Media::class,
        Texts::class,
        Tokens::class,
        ClockInterface::class,
        EventDispatcherInterface::class,
        MailerInterface::class,
    ];

    /**
     * The contracts bound to another service than their interface's name:
     * the parameters are `parameter_bag` (HotParameterBag, the settings stored
     * in the database over the container's), what BaseTrait's getParameterBag()
     * gives - the interface's name is the plain Base\Service\ParameterBag.
     *
     * @var array<class-string, string>
     */
    public const SERVICES = [
        ParameterBagInterface::class => 'parameter_bag',
    ];

    public function process(ContainerBuilder $container): void
    {
        $contracts = [];
        foreach (self::CONTRACTS as $contract) {
            $id = self::SERVICES[$contract] ?? $contract;
            if ($container->has($id)) {
                $contracts[$contract] = new Reference($id);
            }
        }

        $container->register(self::SERVICE, ServiceLocator::class)
            ->setPublic(true)
            ->addTag('container.service_locator')
            ->setArguments([$contracts]);
    }
}
