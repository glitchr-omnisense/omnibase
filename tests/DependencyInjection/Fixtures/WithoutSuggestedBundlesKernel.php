<?php

namespace Tests\Base\DependencyInjection\Fixtures;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Yaml\Yaml;

/**
 * The host application (the harness, or the application whose suite this runs in) without
 * the bundles omniscient/omnibase only suggests since 4.0 (plan "omnibase agnostique", C7) -
 * all of them since C7b: scheb/2fa (Base\Entity\User declares its interfaces through
 * Base\Contract\TwoFactorUser, by the package, not the bundle) and league/flysystem-bundle
 * (Base\Service\Flysystem no longer extends its LazyFactory; omnibase's local storages
 * without it) were needed de facto before. The packages stay installed: what is proven is the core's wiring, a container that
 * compiles and pages that render without those bundles registered. Their configuration
 * files (config/packages/<alias>.yaml) are left out with them, as an application without
 * the bundle would not have them.
 */
final class WithoutSuggestedBundlesKernel extends Kernel
{
    use MicroKernelTrait;

    /** The bundles the core suggests and can do without. */
    public const LEFT_OUT = [
        'ApiPlatform\\Symfony\\Bundle\\ApiPlatformBundle',
        'Google\\GoogleBundle',
        'HWI\\Bundle\\OAuthBundle\\HWIOAuthBundle',
        'League\\FlysystemBundle\\FlysystemBundle',
        'Nelmio\\CorsBundle\\NelmioCorsBundle',
        'Nelmio\\SecurityBundle\\NelmioSecurityBundle',
        'Scheb\\TwoFactorBundle\\SchebTwoFactorBundle',
        'Symfony\\UX\\TwigComponent\\TwigComponentBundle',
        'Symfony\\WebpackEncoreBundle\\WebpackEncoreBundle',
        'Webauthn\\Bundle\\WebauthnBundle',
        'Well\\Known\\WellKnownBundle',
    ];

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir().'/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if (\in_array($class, self::LEFT_OUT, true)) {
                continue;
            }
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    public function getProjectDir(): string
    {
        return \dirname((new \ReflectionClass('App\\Kernel'))->getFileName(), 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.'_without_suggested';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    /** @return list<string> the configuration files left out, their extension not registered */
    public static array $leftOutFiles = [];

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $configDir = $this->getConfigDir();
        $aliases = array_keys($builder->getExtensions());
        self::$leftOutFiles = [];

        foreach ([$configDir.'/packages', $configDir.'/packages/'.$this->environment] as $dir) {
            foreach (glob($dir.'/*.{php,yaml}', \GLOB_BRACE) ?: [] as $file) {
                if (str_ends_with($file, '.yaml') && !self::readable($file, $aliases)) {
                    self::$leftOutFiles[] = basename($file);
                    continue;
                }
                $container->import($file);
            }
        }

        $container->import($configDir.'/{services}.yaml');
        $container->import($configDir.'/{services}_'.$this->environment.'.yaml');
    }

    /** The application's routes, but for the files importing a bundle left out (hwi_oauth_routing.yaml...). */
    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $configDir = $this->getConfigDir();
        $bundles = array_keys($this->getBundles());

        foreach ([$configDir.'/routes/'.$this->environment, $configDir.'/routes'] as $dir) {
            foreach (glob($dir.'/*.{php,yaml}', \GLOB_BRACE) ?: [] as $file) {
                preg_match_all('~@([A-Za-z0-9]+Bundle)\b~', (string) file_get_contents($file), $named);
                if (array_diff($named[1], $bundles)) {
                    self::$leftOutFiles[] = 'routes/'.basename($file);
                    continue;
                }
                $routes->import($file);
            }
        }

        $routes->import(is_file($configDir.'/routes.yaml') ? $configDir.'/routes.yaml' : $configDir.'/routes.php');
    }

    /** Whether every extension a YAML file configures (at its root or under when@env) is registered. */
    private static function readable(string $file, array $aliases): bool
    {
        $keys = [];
        foreach ((array) Yaml::parseFile($file, Yaml::PARSE_CUSTOM_TAGS | Yaml::PARSE_CONSTANT) as $key => $value) {
            if (str_starts_with((string) $key, 'when@')) {
                $keys = array_merge($keys, array_keys((array) $value));
            } else {
                $keys[] = $key;
            }
        }

        foreach ($keys as $key) {
            if (!\in_array($key, ['services', 'parameters', 'imports'], true) && !\in_array($key, $aliases, true)) {
                return false;
            }
        }

        return true;
    }
}
