<?php

namespace Base\DependencyInjection;

use Base\Attributes\AttributeInterface;
use Base\BaseBundle;
use Base\Database\Type\UtcDateTimeImmutableType;
use Base\Database\Type\UtcDateTimeType;
use Doctrine\DBAL\Types\Type;
use Base\Cache\Abstract\AbstractLocalCacheInterface;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Demo\DemoAccountProviderInterface;
use Base\EntityDispatcher\EventDispatcherInterface;
use Base\Service\Model\Currency\CurrencyApiInterface;
use Base\Service\Model\IconProvider\AbstractIconAdapter;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Service\Model\Obfuscator\CompressionInterface;
use Base\Twig\Renderer\TagRendererInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Workflow\WorkflowInterface;

use Base\Bundle\AbstractBaseExtension;
use Base\Service\Model\Sharing\SharingAdapterInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class BaseExtension extends AbstractBaseExtension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        //
        // Load service declaration (includes services, controllers,..)
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.php');
        $loader->load('services-public.php');
        $loader->load('services-fix.php');
        $loader->load('services-decoration.php');

        // Configuration file: ./config/package/base.yaml
        $processor = new Processor();
        $configuration = new BaseConfiguration();
        $config = $processor->processConfiguration($configuration, $configs);

        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->getRootNode()->getNode()->getName());

        // Override and merge form_themes.. to add some features..
        $container->setParameter('twig.form.resources', array_merge(
            $config['twig']['form_themes'],
            $container->getParameter('twig.form.resources')
        ));

        $container->registerForAutoconfiguration(AbstractIconAdapter::class)->addTag('base.service.icon');
        $container->registerForAutoconfiguration(EntityExtensionInterface::class)->addTag('base.entity_extension');
        $container->registerForAutoconfiguration(AttributeInterface::class)->addTag('base.attribute');
        $container->registerForAutoconfiguration(IconAdapterInterface::class)->addTag('base.icon_provider');
        $container->registerForAutoconfiguration(SharingAdapterInterface::class)->addTag('base.service.sharing');
        $container->registerForAutoconfiguration(AbstractLocalCacheInterface::class)->addTag('base.simple_cache');
        $container->registerForAutoconfiguration(CurrencyApiInterface::class)->addTag('currency.api');
        $container->registerForAutoconfiguration(CompressionInterface::class)->addTag('obfuscator.compressor');
        $container->registerForAutoconfiguration(TagRendererInterface::class)->addTag('twig.tag_renderer');
        $container->registerForAutoconfiguration(WorkflowInterface::class)->addTag('workflow');
        // The demonstration accounts a bundle or the application declares (docs/20-architecture/demo.md).
        $container->registerForAutoconfiguration(DemoAccountProviderInterface::class)->addTag('base.demo_account_provider');
        // omnibase's repositories are doctrine/orm's EntityRepository built from the ManagerRegistry
        // (plan "omnibase agnostique", C3b), no longer DoctrineBundle's ServiceEntityRepository, whose
        // interface DoctrineBundle tags: an application's repository extending them (App\Repository\*)
        // is tagged here, and DoctrineBundle's repository factory finds it in the container as before.
        $container->registerForAutoconfiguration(\Base\Database\Repository\ServiceEntityRepository::class)->addTag('doctrine.repository_service');

        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "preUpdate"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "postUpdate"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "prePersist"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "postPersist"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "preRemove"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "postRemove"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "postFlush"])
            // Flushed after the flush that raised their events (EntityDispatcherFlushSubscriber).
            ->addTag('base.entity_dispatcher')
            ->addTag('kernel.reset', ['method' => 'reset']);
        
    }

    public function prepend(ContainerBuilder $builder): void
    {
        // Where this package is, wherever Composer put it (vendor/omniscient/omnibase
        // since its rename from glitchr/omnibase, a path repository, a checkout):
        // what an application's configuration names instead of a vendor path -
        // wikidoc's roots ('%base.package_dir%/docs'), git's repositories.
        // Set before any extension loads, so every configuration can use it.
        $builder->setParameter('base.package_dir', \dirname(__DIR__, 2));

        $this->prependDoctrineTypes($builder);

        // symfony/ux-twig-component is a suggestion since 4.0.
        if ($builder->hasExtension('twig_component')) {
            $builder->prependExtensionConfig('twig_component', [
                'defaults' => [
                    'Base\\Twig\\Component\\' => [
                        'template_directory' => '@Base/components/',
                        'name_prefix' => 'base',
                    ],
                    'App\\Twig\\Component\\' => [
                        'template_directory' => '@App/components/',
                        'name_prefix' => 'app',
                    ],
                ],
            ]);
        }
    }

    /**
     * Registers the Enum/Set classes as doctrine.dbal.types.
     *
     * BaseBundle::boot() also calls Type::addType() for these, but that is far too
     * late: by the time boot() reaches it, entity metadata has already been built
     * and CACHED. Attributes that ask "what Doctrine type is this field?" during
     * loadClassMetadata therefore got null and silently declined - most visibly
     * OrderColumn, whose companion ordering column (e.g. User::rolesPositions)
     * never made it into the mapping. dev hid this because it rebuilds metadata
     * lazily, after boot; test and prod cached the amputated version for good, so
     * `doctrine:schema:update` created a table WITHOUT the column while the
     * running app still SELECTed it - "Unknown column 'u0_.rolesPositions'".
     *
     * Declaring them here instead makes DoctrineBundle's ConnectionFactory
     * register them while the connection is created, which necessarily precedes
     * any metadata load through that entity manager. boot()'s registration is
     * kept (it is hasType()-guarded, so it simply becomes a no-op) rather than
     * removed, because it also covers the built-in overrides.
     */
    private function prependDoctrineTypes(ContainerBuilder $builder): void
    {
        // Moments kept in UTC whatever zone PHP was put in for the visitor
        // (docs/20-architecture/time.md): there for every bundle and application.
        $types = [
            UtcDateTimeImmutableType::NAME => UtcDateTimeImmutableType::class,
            UtcDateTimeType::NAME => UtcDateTimeType::class,
        ];
        // The columns of a passkey, which the core describes (plan "omniauth", AU1).
        $types += \Base\Bridge\Doctrine\Types::passkey();

        $classList = array_merge(
            BaseBundle::getAllClasses(BaseBundle::getBundleDir() . "/src/Enum"),
            BaseBundle::getAllClasses(BaseBundle::getProjectDir() . "/src/Enum")
        );

        foreach ($classList as $className) {

            if (!is_subclass_of($className, Type::class)) {
                continue;
            }

            if (!method_exists($className, "getStaticName")) {
                continue;
            }

            $types[$className::getStaticName()] = $className;
        }

        $builder->prependExtensionConfig('doctrine', ['dbal' => ['types' => $types]]);
    }
}
