<?php

/*
 * The whole graph of the core's services where there is no framework (plan "omnibase agnostic", step F5e slice 4):
 * run by docker/bin/bare for tests/BareTest.php, with Composer's autoloader alone. Omnibridge\Container is built from
 * every definition of Base\Services (the declaration Symfony's container and Laravel's read), with the plain-PHP host
 * of container-without-framework.php (its parameters, its contracts) and what a host gives that this one has no use
 * for until a service works: the persistence's repositories, the request's contracts, an event dispatcher, an HTTP
 * client - stand-ins here, each method refusing aloud. Then every service the container defines is built.
 *
 * A definition the core declares as requiring a framework's class (`requires`: Twig, symfony/validator, the Symfony
 * router, the request stack, Doctrine's entity manager... - what its constructor cannot do without) is not
 * registered here, and the result lists them; every other one must be built.
 */

use Base\Services;
use Omnibridge\Container;
use Omnibridge\Container\Declarations;

require __DIR__.'/container-without-framework.php';
['values' => $values, 'services' => $services] = $GLOBALS['omnibase_plain_host'];

/** A stand-in for an interface this host has no use for: a class whose every method refuses aloud. */
function omnibase_stand_in(string $interface): object
{
    static $made = [];
    if (!isset($made[$interface])) {
        $name = 'StandIn'.count($made);
        $methods = [];
        foreach ((new ReflectionClass($interface))->getMethods() as $method) {
            $parameters = [];
            foreach ($method->getParameters() as $parameter) {
                $parameters[] = ($parameter->hasType() ? $parameter->getType().' ' : '').($parameter->isPassedByReference() ? '&' : '').($parameter->isVariadic() ? '...' : '').'$'.$parameter->getName()
                    .($parameter->isDefaultValueAvailable() ? ' = '.($parameter->isDefaultValueConstant() ? '\\'.$parameter->getDefaultValueConstantName() : var_export($parameter->getDefaultValue(), true)) : '');
            }
            $return = $method->hasReturnType() ? ': '.$method->getReturnType() : '';
            $methods[] = ($method->isStatic() ? 'public static' : 'public').' function '.$method->getName().'('.implode(', ', $parameters).')'.$return.' { throw new LogicException("A stand-in: '.$interface.'::'.$method->getName().'()."); }';
        }
        eval('final class '.$name.' implements \\'.$interface.' { '.implode("\n", $methods).' }');
        $made[$interface] = $name;
    }

    return new $made[$interface]();
}

$resolved = Declarations::resolve(Services::definitions(), $values['kernel.environment'] ?? null);
$values += [
    'kernel.environment' => 'prod',
    'kernel.debug' => false,
    'kernel.project_dir' => sys_get_temp_dir().'/omnibase-bare-graph',
    'kernel.cache_dir' => sys_get_temp_dir().'/omnibase-bare-graph/cache',
];
// The base.* options a configuration that says nothing gives, and the environment's defaults (env(COLLAB_*),
// env(BASE_TRADING_LIVE)): the core's declaration of its options (Base\Options, step F5e slice 4b).
$values += Base\Options::parameters() + Base\Options::environmentParameters();

// What the host gives that this one has no use for until a service works: each interface a constructor of the graph asks
// (by type, or by an argument's id) that the declaration does not define and the host did not give, as a stand-in.
$given = $services;
$defined = [];
foreach ($resolved as $id => $definition) {
    $defined[$id] = true;
    foreach ($definition['contracts'] as $contract => $options) {
        $defined[$contract] = true;
    }
}
$asked = [];
foreach ($resolved as $definition) {
    $arguments = [$definition['arguments'], $definition['bind'], $definition['calls']];
    array_walk_recursive($arguments, static function (mixed $value) use (&$asked): void {
        if (is_string($value) && str_starts_with($value, '@') && !str_starts_with($value, '@@')) {
            $asked[] = ltrim(substr($value, 1), '?!');
        }
    });
    if (!Declarations::loadable($definition['class'])) {
        continue;
    }
    foreach ((new ReflectionClass($definition['class']))->getConstructor()?->getParameters() ?? [] as $parameter) {
        $type = $parameter->getType();
        foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : [$type] as $named) {
            if ($named instanceof ReflectionNamedType && !$named->isBuiltin()) {
                $asked[] = $named->getName();
            }
        }
    }
}
foreach (array_unique($asked) as $id) {
    if (!isset($given[$id]) && !isset($defined[$id]) && interface_exists($id)
        && preg_match('~^(?:Base\\\\Repository\\\\|Base\\\\Database\\\\Repository\\\\|Omnibridge\\\\Contract\\\\|Omnibridge\\\\Persistence\\\\|Psr\\\\EventDispatcher\\\\|Symfony\\\\Contracts\\\\HttpClient\\\\|Omniauth\\\\Contract\\\\)~', $id)) {
        $given[$id] = omnibase_stand_in($id);
    }
}
$given['Symfony\Component\String\Slugger\SluggerInterface'] = new Symfony\Component\String\Slugger\AsciiSlugger();
// The parameters' bag with the defaults too.
$given[Base\Service\ParameterBagInterface::class] = new Base\Service\ArrayParameterBag($values);
$given['flysystem'] = $given[Base\Service\Flysystem::class] = $services[Base\Service\Storages::class];
// A cache pool and a notifier of plain PHP (the core's memory pool, symfony/notifier without a channel).
$given['Psr\Cache\CacheItemPoolInterface'] = new Base\Cache\Pool\MemoryPool();
$given['Symfony\Contracts\Translation\TranslatorInterface'] = $services[Symfony\Component\Translation\Translator::class];
$given['Symfony\Component\Notifier\NotifierInterface'] = new Symfony\Component\Notifier\Notifier([]);
$given['Symfony\Component\Notifier\Channel\ChannelPolicyInterface'] = new Symfony\Component\Notifier\Channel\ChannelPolicy([]);

// What is discovered among them (Base\Discovery, step F5e slice 4c): the autoconfigured tags, the collections, the fallbacks.
$container = Container::fromDefinitions(Services::definitions(), $values, $given, Base\Discovery::registries());

$built = [];
$failed = [];
$parents = [];
foreach (array_keys($resolved) as $id) {
    if (!$container->has($id) && Declarations::loadable($resolved[$id]['class']) && (new ReflectionClass($resolved[$id]['class']))->isAbstract()) {
        $parents[] = $id; // an abstract class declared for its children (Symfony's parent definitions of them)
        continue;
    }
    if (!$container->has($id)) {
        $failed[$id] = 'not a service here';
        continue;
    }
    try {
        $built[$id] = get_class($container->get($id));
    } catch (Throwable $e) {
        $failed[$id] = get_class($e).': '.$e->getMessage();
    }
}

$left = [];
foreach (Services::definitions() as $definition) {
    if (!empty($definition['abstract']) && Services::applies($definition, $values['kernel.environment'])) {
        $parents[] = Services::id($definition); // a parent only (Symfony's abstract definitions)
    } elseif (!isset($resolved[Services::id($definition)]) && (empty($definition['abstract']) || !Services::applies($definition, $values['kernel.environment']))) {
        $left[Services::id($definition)] = $definition['requires'] ?? [$definition['environment'] ?? '?'];
    }
}

// What the plain container discovered (step F5e slice 4c), to compare with what Symfony's container discovers on a site
// (tests/Services/discovered-by-symfony.php): each collecting service it builds with the services it was handed, in their
// order; the admin's links where no back office is installed; the core's enumerations.
$collected = [];
foreach (Base\Discovery::registry('collect') as $collection) {
    if (!isset($built[$collection['service']])) {
        continue;
    }
    $service = $container->get($collection['service']);
    $handed = match ($collection['method']) {
        'addCompression' => $service->getCompressions(),
        'addAdapter' => (fn () => $this->adapters)->call($service),
        'addProvider' => (fn () => $this->providers)->call($service),
        default => [],
    };
    $collected[$collection['service']] = array_values(array_map(get_class(...), is_array($handed) ? $handed : iterator_to_array($handed)));
}

return [
    'discovered' => [
        'collect' => $collected,
        'fallbacks' => [Base\Routing\AdminUrlGeneratorInterface::class => get_class($container->get(Base\Routing\AdminUrlGeneratorInterface::class))],
        'enumerations' => Base\Discovery::enumerations($values['kernel.project_dir']),
        'autoconfigured' => array_keys(array_filter(
            Omnibridge\Container\Discovery::autoconfigure($resolved, Base\Discovery::registry('autoconfigure')),
            static fn (array $definition, string $id): bool => $definition['tags'] !== $resolved[$id]['tags'],
            ARRAY_FILTER_USE_BOTH,
        )),
    ],
    'declared' => count(Services::definitions()),
    'built' => count($built),
    'parents' => $parents,
    'failed' => $failed,
    'left out, with what they require' => count($left),
    'left' => $left,
];
