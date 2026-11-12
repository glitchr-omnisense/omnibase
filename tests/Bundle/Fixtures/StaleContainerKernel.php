<?php

namespace Tests\Base\Bundle\Fixtures;

use Base\Attributes\AttributeReader;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * The host application (the harness, or the application whose suite this runs in) whose container looks like one
 * compiled before omnisense/omnibase 2d4e19f5 and omnibridge/symfony 601d65e/0fcc078, which production runs as it is
 * (without debug a compiled container is never rebuilt, and cache:clear boots it first): no list of attributes
 * (AttributeReader::NAMES), no list of enumerations, Symfony's router naming its own matcher and generator, the
 * contracts' locator without the attribute reader, DoctrineBundle's metadata cache warmer. What the new code finds in
 * it is what an old var/cache/prod gives it.
 */
final class StaleContainerKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait {
        configureContainer as private configureHostContainer;
    }

    /** What the compile step of those versions adds, taken out again. */
    public const PARAMETERS = [
        AttributeReader::NAMES,
        'omnibridge.doctrine.enumerations',
        'omnibridge.router.advanced',
    ];

    public function getProjectDir(): string
    {
        return (string) realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.'_stale_test';
    }

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        // MicroKernelTrait's configureContainer() takes the configurator alone under Symfony 8, the loader and the builder too before.
        $arguments = [$container, $loader, $builder];
        $this->configureHostContainer(...\array_slice($arguments, 0, (new \ReflectionMethod($this, 'configureHostContainer'))->getNumberOfParameters()));

        $container->extension('framework', [
            'test' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
    }

    protected function build(ContainerBuilder $container): void
    {
        // Once every pass of the bundles has run, before the unused definitions are removed.
        $container->addCompilerPass($this, PassConfig::TYPE_BEFORE_REMOVING, -1024);
    }

    public function process(ContainerBuilder $container): void
    {
        foreach (self::PARAMETERS as $name) {
            if ($container->hasParameter($name)) {
                $container->getParameterBag()->remove($name);
            }
        }

        if ($container->hasDefinition('router.default') && \is_array($options = $container->getDefinition('router.default')->getArgument(2))) {
            foreach (['matcher_class', 'generator_class'] as $option) {
                if (str_starts_with((string) ($options[$option] ?? ''), 'Omnibridge\\Symfony\\Omnibase\\Routing\\')) {
                    unset($options[$option]);
                }
            }
            $container->getDefinition('router.default')->replaceArgument(2, $options);
        }

        $contracts = \Base\Discovery::registry('contracts')['service'];
        if ($container->has($contracts)) {
            $definition = $container->findDefinition($contracts);
            if (ServiceLocator::class === $definition->getClass() && \is_array($services = $definition->getArgument(0))) {
                unset($services[AttributeReader::class]);
                $definition->replaceArgument(0, $services);
            }
        }

        foreach (array_keys($container->hasParameter('doctrine.entity_managers') ? $container->getParameter('doctrine.entity_managers') : []) as $name) {
            $warmer = sprintf('doctrine.orm.%s_metadata_cache_warmer', $name);
            if ($container->hasDefinition($warmer) && class_exists($doctrine = 'Doctrine\\Bundle\\DoctrineBundle\\CacheWarmer\\DoctrineMetadataCacheWarmer')) {
                $container->getDefinition($warmer)->setClass($doctrine);
            }
        }
    }
}
