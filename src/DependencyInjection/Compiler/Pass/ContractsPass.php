<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Contract\Media;
use Base\Contract\Nouns;
use Base\Contract\Pictures;
use Base\Contract\Texts;
use Base\Service\ParameterBagInterface;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Files;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Sessions;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
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
 * BaseBundle::boot() - omniscient/omnibridge's registry since A2, the host's
 * contracts (Omnibridge\Contract\*) with omnibase's own (Nouns, Media,
 * Pictures, Texts, the parameters): each contract by its interface's name, the service the
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
        Nouns::class,
        Access::class,
        Sessions::class,
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
     * The former names of the contracts that moved to omniscient/omnibridge
     * (A2), aliases of its interfaces until omnibase 4's final pass: listed in
     * the locator too, the same service as the contract's own name. The
     * registry asks by the own name first.
     *
     * @var array<string, class-string>
     */
    public const FORMER = [
        'Base\\Contract\\Urls' => Urls::class,
        'Omnibridge\\Twig\\Contract\\Urls' => Urls::class,
        'Base\\Contract\\Translations' => Translations::class,
        'Omnibridge\\Twig\\Contract\\Translations' => Translations::class,
        'Base\\Contract\\Locales' => Locales::class,
        'Base\\Contract\\Access' => Access::class,
        'Base\\Contract\\Sessions' => Sessions::class,
        'Base\\Contract\\Tokens' => Tokens::class,
        'Base\\Contract\\Files' => Files::class,
        'Base\\Contract\\Routes' => Routes::class,
        'Base\\Contract\\Environment' => Environment::class,
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
        foreach (self::FORMER as $former => $contract) {
            if (isset($contracts[$contract])) {
                $contracts[$former] = $contracts[$contract];
            }
        }

        $container->register(self::SERVICE, ServiceLocator::class)
            ->setPublic(true)
            ->addTag('container.service_locator')
            ->setArguments([$contracts]);
    }
}
