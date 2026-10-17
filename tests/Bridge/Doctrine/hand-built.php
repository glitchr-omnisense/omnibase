<?php

/*
 * An entity manager built by hand over an omnibase site's database: no kernel,
 * no container, no DoctrineBundle - Composer's autoloader, a DBAL connection,
 * omnibridge/doctrine's EntityManagers::create() and the core's
 * Base\Bridge\Doctrine\Mapping::register() (plan "omnibase agnostique", C6).
 *
 *   php hand-built.php <options.json> <answer.json>
 *
 * The options: autoload (the application's vendor/autoload.php), connection
 * (DBAL's parameters), paths (the entities' folders), project_dir,
 * environment, parameters, types, resolve_target_entities, entity_listeners
 * (see Mapping), and
 * reads: DQL queries (dql, parameters, max) or a repository's findBy() (repository,
 * criteria, order, max), whose rows are given back, each row as its class, its id
 * and the fields asked (a getter's name each, or "getTranslations.getTitle":
 * a getter's collection, then a getter on each item, by its key).
 *
 * The answer: the metadata (Base\Bridge\Doctrine\MetadataSnapshot), the rows
 * read, and the classes of the framework PHP loaded on the way (informative).
 */

ini_set('display_errors', 'stderr');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$options = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
require $options['autoload'];

$configuration = new Doctrine\DBAL\Configuration();
$configuration->setMiddlewares([new Base\Database\Middleware\PlatformMiddleware()]);
$connection = Doctrine\DBAL\DriverManager::getConnection($options['connection'], $configuration);

// The metadata cached in memory: EntityManagers::create() without a cache takes ORMSetup's default, which
// is APCu, else a Memcached or a Redis server on 127.0.0.1 - none in an application's web container.
$entityManager = Omnibridge\Doctrine\EntityManagers::create($connection, $options['paths'], false, new Symfony\Component\Cache\Adapter\ArrayAdapter());
$mapping = Base\Bridge\Doctrine\Mapping::register($entityManager, [
    'project_dir' => $options['project_dir'],
    'environment' => $options['environment'] ?? 'prod',
    'cache_dir' => $options['cache_dir'] ?? null,
    'parameters' => $options['parameters'] ?? [],
    'types' => $options['types'] ?? [],
    'resolve_target_entities' => $options['resolve_target_entities'] ?? [],
    'entity_listeners' => $options['entity_listeners'] ?? [],
]);

$answer = ['factory' => $entityManager->getMetadataFactory()::class];
$answer += Base\Bridge\Doctrine\MetadataSnapshot::of($entityManager, $options['classes'] ?? null);

$answer['reads'] = [];
foreach ($options['reads'] ?? [] as $name => $read) {
    if (isset($read['repository'])) {
        // A repository's findBy(), built by Base\Bridge\Doctrine\RepositoryFactory.
        $repository = $mapping->getRepository($read['repository']);
        $answer['repositories'][$name] = $repository::class;
        $result = $repository->findBy($read['criteria'] ?? [], $read['order'] ?? null, $read['max'] ?? null);
    } else {
        $query = $entityManager->createQuery($read['dql']);
        foreach ($read['parameters'] ?? [] as $parameter => $value) {
            $query->setParameter($parameter, $value);
        }
        if (isset($read['max'])) {
            $query->setMaxResults($read['max']);
        }
        $result = $query->getResult();
    }

    $rows = [];
    foreach ($result as $row) {
        $values = ['class' => $entityManager->getClassMetadata($row::class)->getName(), 'id' => $row->getId()];
        foreach ($read['fields'] ?? [] as $field) {
            // "getTranslations.getTitle": a getter's collection, then a getter on each of its items, by its key.
            if (str_contains($field, '.')) {
                [$collection, $getter] = explode('.', $field, 2);
                $values[$field] = [];
                foreach ($row->$collection() as $key => $item) {
                    $values[$field][$key] = $item->$getter();
                }
                ksort($values[$field]);
                continue;
            }

            $value = $row->$field();
            $values[$field] = $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : (is_object($value) ? (method_exists($value, '__toString') ? (string) $value : $value::class) : $value);
        }
        $rows[] = $values;
    }
    $answer['reads'][$name] = $rows;
}

$framework = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation|Routing|Security|Form|Console)\\\\|Symfony\\\\(?:Bundle|Bridge|UX)\\\\|ApiPlatform\\\\|HWI\\\\|Scheb\\\\|Nelmio\\\\|Doctrine\\\\Bundle\\\\)~';
$answer['framework'] = array_values(preg_grep($framework, array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits())));
sort($answer['framework']);

file_put_contents($argv[2], json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
