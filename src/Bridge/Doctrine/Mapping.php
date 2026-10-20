<?php

namespace Base\Bridge\Doctrine;

use Base\Service\FlysystemInterface;
use Base\Attributes\AttributeReader;
use Base\Database\Entity\EntityExtension;
use Base\Database\Entity\EntityHydrator;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Mapping\NamingStrategy;
use Base\Database\Events as BaseEvents;
use Base\DatabaseSubscriber\AttributeSubscriber;
use Base\DatabaseSubscriber\InverseCollectionCacheSubscriber;
use Base\DatabaseSubscriber\IntlSubscriber;
use Base\DatabaseSubscriber\TrackingPolicySubscriber;
use Base\EntitySubscriber\ExtensionSubscriber;
use Base\EntitySubscriber\TrasheableSubscriber;
use Base\EntitySubscriber\VersionableSubscriber;
use Base\Service\ArrayParameterBag;
use Base\Service\LocalizerInterface;
use Base\Service\Model\ClassAliases;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\AttachEntityListenersListener;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;

/**
 * omnibase's Doctrine mapping on an entity manager built by hand - no kernel,
 * no container, no DoctrineBundle (plan "omnibase agnostique", C6):
 *
 *     $entityManager = Omnibridge\Doctrine\EntityManagers::create($connection, $paths, configure: Base\Bridge\Doctrine\Mapping::configure(...));
 *     Base\Bridge\Doctrine\Mapping::register($entityManager, ['project_dir' => ..., 'environment' => 'prod']);
 *
 * before the first metadata is read. It does what DoctrineBundle and the
 * bundle do under Symfony:
 *
 * - the class convention (Base\Service\Model\ClassAliases: App\Entity\X is
 *   Base\Entity\X where the application has none);
 * - the column types (Base\Bridge\Doctrine\Types): the core's, the
 *   enumerations of the core and of the project (src/Enum), those given;
 * - the configuration: omnibase's naming strategy, the filters and the DQL
 *   functions (Base\Bridge\Doctrine\Configuration), the mapping driver wrapped
 *   (Base\Bridge\Doctrine\MappingDriver: the discriminator maps, the
 *   `resolveDiscriminator` event);
 * - the repositories built from a registry (Base\Bridge\Doctrine\Registry,
 *   RepositoryFactory): configure() sets the factory on the configuration
 *   before the entity manager exists (omnibridge/doctrine's `configure:`), and
 *   the entity manager's own getRepository() hands them out;
 * - the services the mapping reads through: the ClassMetadataManipulator
 *   (the completors: aliases, hierarchies), the AttributeReader (#[Alias],
 *   #[DiscriminatorEntry], #[Hierarchify], #[Cache], #[OrderColumn]...) with
 *   the parameters given and none of the framework's services;
 * - the listeners, in the order DoctrineBundle gives them: AttributeSubscriber
 *   and IntlSubscriber (the translations' mapping, their discriminators, the
 *   walker of a translatable's query), the targets resolved, the entity
 *   listeners attached, the tracking
 *   policy, the extensions' index, then the soft deletion, the revisions and
 *   the inverse collections' cache on flush;
 * - the trash and vault filters enabled, the vault for the environment.
 *
 * Options: project_dir (the project's src/Enum and src/Attribute; the current
 * directory by default), environment ("prod"), cache_dir (the reader's and
 * the manipulator's caches; a folder of the system's temporary one by default),
 * parameters (the application's, by full name: base.attributes.use_custom,
 * base.extension.max_revisions...), types (by name, a bundle's enumerations),
 * enumerations (more folders of enumerations), attribute_paths (more folders
 * of attributes: a bundle's src/Attribute), resolve_target_entities
 * (interface => class), entity_listeners (entity class => list of
 * {event, class, method}: what a bundle tags doctrine.orm.entity_listener), localizer (a LocalizerInterface, none by default),
 * flysystem (omnibase's Base\Service\FlysystemInterface over the host's storages, none by default: the
 * attributes that read a stored file - #[Uploader]'s public address of an upload, an article's audio -
 * read it through the reader's; without it they cannot answer), services (a closure giving the host's service of a
 * type, or null: what a repository takes beside the registry, a site's cache pool - RepositoryFactory::services()),
 * filters (true: enabled).
 */
final class Mapping
{
    private function __construct(
        public readonly EntityManagerInterface $entityManager,
        public readonly Registry $registry,
        public readonly ClassMetadataManipulator $classMetadataManipulator,
        public readonly AttributeReader $attributeReader,
        public readonly ArrayParameterBag $parameters,
        public readonly RepositoryFactory $repositories,
    ) {
    }

    /** @param array<string, mixed> $options */
    public static function register(EntityManagerInterface $entityManager, array $options = []): self
    {
        $projectDir = rtrim($options['project_dir'] ?? (string) getcwd(), '/');
        $environment = $options['environment'] ?? 'prod';
        $cacheDir = $options['cache_dir'] ?? sys_get_temp_dir().'/omnibase-doctrine-'.md5($projectDir).'/'.$environment;
        @mkdir($cacheDir, 0777, true);

        // App\Entity\X is Base\Entity\X where the application has none of its own: associations name them.
        ClassAliases::register();

        Types::register($options['types'] ?? [], array_merge(
            [Types::coreEnumerationDirectory(), $projectDir.'/src/Enum'],
            $options['enumerations'] ?? []
        ));

        $parameters = ['base.attributes.use_custom' => true, 'base.database.use_custom' => true];
        foreach (array_values(array_merge([$projectDir.'/src/Attribute'], $options['attribute_paths'] ?? [])) as $i => $path) {
            $parameters['base.attributes.paths.'.$i] = $path;
        }
        $parameters = new ArrayParameterBag(($options['parameters'] ?? []) + $parameters);

        $configuration = $entityManager->getConfiguration();
        $configuration->setNamingStrategy(new NamingStrategy());
        Configuration::apply($configuration);
        $configuration->setMetadataDriverImpl(new MappingDriver($configuration->getMetadataDriverImpl(), $entityManager));

        // The repository factory configure() set, and its registry; else a factory of our own, which only
        // getRepository() (deprecated) reaches: the entity manager keeps the one it was built with.
        $factory = $configuration->getRepositoryFactory();
        $repositories = $factory instanceof RepositoryFactory ? $factory : null;
        $registry = $repositories?->registryFor($entityManager) ?? new Registry($entityManager);
        $repositories ??= new RepositoryFactory($registry);
        if (($services = $options['services'] ?? null) instanceof \Closure) {
            $repositories->services($services);
        }

        $manipulator = new ClassMetadataManipulator($registry, $entityManager, $cacheDir);
        $hydrator = new EntityHydrator($entityManager, $manipulator);
        $flysystem = $options['flysystem'] ?? null;
        $reader = new AttributeReader(null, null, $entityManager, $parameters, $flysystem instanceof FlysystemInterface ? $flysystem : null, null, null, $hydrator, $manipulator, $projectDir, $environment, $cacheDir);

        $attributes = new AttributeSubscriber($entityManager, $manipulator, $reader);
        $localizer = $options['localizer'] ?? null;
        $intl = new IntlSubscriber($entityManager, $localizer instanceof LocalizerInterface ? $localizer : null);

        $events = $entityManager->getEventManager();
        $events->addEventListener([
            Events::loadClassMetadata, BaseEvents::resolveDiscriminator, BaseEvents::preQuery, BaseEvents::onQuery, BaseEvents::postQuery,
            Events::postLoad, Events::preFlush, Events::onFlush, Events::postFlush, Events::prePersist, Events::preUpdate,
            Events::preRemove, Events::postPersist, Events::postUpdate, Events::postRemove,
        ], $attributes);
        $events->addEventListener([
            Events::loadClassMetadata, BaseEvents::resolveDiscriminator, BaseEvents::onQuery, Events::postLoad,
            Events::prePersist, Events::preFlush, Events::onFlush,
        ], $intl);

        if ($targets = $options['resolve_target_entities'] ?? []) {
            $resolver = new ResolveTargetEntityListener();
            foreach ($targets as $interface => $className) {
                $resolver->addResolveTargetEntity($interface, $className, []);
            }
            $events->addEventSubscriber($resolver);
        }

        if ($entityListeners = $options['entity_listeners'] ?? []) {
            $attach = new AttachEntityListenersListener();
            foreach ($entityListeners as $entityClass => $listeners) {
                foreach ($listeners as $listener) {
                    $attach->addEntityListener($entityClass, $listener['class'], $listener['event'] ?? null, $listener['method'] ?? null);
                }
            }
            $events->addEventListener([Events::loadClassMetadata], $attach);
        }

        $events->addEventListener([Events::loadClassMetadata], new TrackingPolicySubscriber($manipulator));

        $extensions = new ExtensionSubscriber($entityManager, new EntityExtension());
        $events->addEventListener([Events::loadClassMetadata], $extensions);

        $events->addEventListener([Events::onFlush, Events::postFlush], new TrasheableSubscriber($parameters->get('base.extension.empty_trash') ?? '+7 days'));
        $events->addEventListener([Events::onFlush, Events::postFlush], new VersionableSubscriber($entityManager, (int) ($parameters->get('base.extension.max_revisions') ?? 5)));
        $events->addEventListener([Events::onFlush, Events::postPersist], $extensions);
        $events->addEventListener([Events::onFlush], new InverseCollectionCacheSubscriber());

        if ($options['filters'] ?? true) {
            Configuration::enableFilters($entityManager, $environment);
        }

        return new self($entityManager, $registry, $manipulator, $reader, $parameters, $repositories);
    }

    /**
     * What an entity manager built by hand needs set before it exists: the
     * repository factory (Doctrine keeps the one its configuration had at
     * `new EntityManager`). Given to omnibridge/doctrine's EntityManagers::create()
     * as its `configure:` closure; then the entity manager's getRepository()
     * builds omnibase's ServiceEntityRepository and the applications' over it
     * from the registry, as the bundle would.
     */
    public static function configure(\Doctrine\ORM\Configuration $configuration): void
    {
        $configuration->setRepositoryFactory(new RepositoryFactory());
    }

    /**
     * The repository of an entity as the bundle would hand it out: omnibase's ServiceEntityRepository and the
     * applications' over it are built from the ManagerRegistry (Base\Bridge\Doctrine\Registry).
     *
     * @deprecated since step C6's follow-up: build the entity manager with configure() (omnibridge/doctrine's
     *             `configure:`) and ask its own getRepository() - the same repository. Kept for an entity manager
     *             built without it, whose own factory is Doctrine's default.
     */
    public function getRepository(string $className): EntityRepository
    {
        return $this->repositories->getRepository($this->entityManager, $className);
    }
}
