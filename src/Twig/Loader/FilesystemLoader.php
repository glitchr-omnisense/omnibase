<?php

namespace Base\Twig\Loader;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Loader\LoaderInterface;

class FilesystemLoader extends \Twig\Loader\FilesystemLoader
{
    protected bool $useCustomLoader = false;
    protected \Twig\Loader\FilesystemLoader $inner;

    public function __construct(
        \Twig\Loader\FilesystemLoader $inner,               // the decorated loader
        ParameterBagInterface $parameterBag,
        string $projectDir
    ) {
        // Setup custom loader, to prevent the known issues of the default symfony TwigLoader
        // 1/ Cannot override <form_div_layout class="html twig">
        // 2/ Infinite loop when using {%use%}
        $this->inner = $inner;
        $this->useCustomLoader = $parameterBag->get('base.twig.use_custom');

        $bundlePath = $parameterBag->get('base.twig.default_path');
        // A path that is not there is the package's own templates: an
        // application that still names vendor/glitchr/omnibase/templates (the
        // package's old name, before omniscient/omnibase) keeps its pages.
        if (!\is_string($bundlePath) || !is_dir($bundlePath)) {
            $bundlePath = \dirname(__DIR__, 3).'/templates';
        }
        parent::__construct([], $bundlePath);
        // Each namespace with ITS paths: getPaths() without a namespace is the
        // main one's (the app's templates/), which made @Base/x resolve to an
        // app override of x first - an override extending its original
        // extended itself, an endless render. Prepended last to first, so
        // they keep the inner loader's order: an app's bundles/<Name>Bundle/
        // override before the bundle's own template.
        foreach($this->inner->getNamespaces() as $namespace) {
            foreach(array_reverse($this->inner->getPaths($namespace)) as $path) {
                $this->prependPath(trim($path), $namespace);
            }
        }
    
        // @Base, this bundle's templates, declared here at Twig's level rather
        // than left to Symfony's bundle path (src/Resources/views, a link that a
        // copy of the package loses: Composer's path repository with
        // symlink: false, the harness). An application's override
        // (templates/bundles/BaseBundle/) first, then the templates; @!Base the
        // templates alone. Where the link is there, nothing is added twice.
        $this->declare('Base', [$projectDir . '/templates/bundles/BaseBundle', $bundlePath]);
        $this->declare('!Base', [$bundlePath]);

        // Add @Twig, @Assets and @Layout variables
        $this->prependPath($bundlePath . '/notifier');
        $this->prependPath($bundlePath);

        $this->prependPath($projectDir . '/src', 'App');
        $this->prependPath($projectDir . '/src/Controller', 'Controller');
        $this->prependPath($projectDir . '/public', 'Public');

        $this->prependPath($projectDir . '/vendor/symfony/twig-bridge/Resources/views', 'Twig');
        $this->prependPath($projectDir . '/templates');

        // Allow to override the default bundle path for any bundle but base-bundle
        $bundlesPath = $bundlePath . '/bundles';
        if (is_dir($bundlesPath)) {

            $directories = scandir($bundlesPath);
            foreach ($directories as $directory) {
                if ($directory === '.' || $directory === '..' || $directory === 'BaseBundle') {
                    continue;
                }

                $fullPath = $bundlesPath . '/' . $directory;
                if (is_dir($fullPath)) {
                    $namespace = str_replace('Bundle', '', $directory);
                    $this->prependPath($fullPath, $namespace);
                }
            }
        }
        
        // Add additional @Namespace variables
        $paths = $parameterBag->get('base.twig.paths') ?? [];
        foreach ($paths as $entry) {
            
            $namespace = $entry['namespace'] ?? self::MAIN_NAMESPACE;

            $path = $entry['path'] ?? null;
            if (empty($path)) {
                throw new \Exception('Missing path variable for @' . $namespace . ' in "base.twig.paths"');
            }

            $this->prependPath($path, $namespace);
        }
    }

    /**
     * Gives a namespace each of these directories it does not have yet (an
     * existing one found by its real path, a link counting as what it leads
     * to), after the ones it has.
     *
     * @param list<string> $directories
     */
    protected function declare(string $namespace, array $directories): void
    {
        $known = array_filter(array_map('realpath', $this->getPaths($namespace)));
        foreach ($directories as $directory) {
            $real = realpath($directory);
            if (false === $real || !is_dir($real) || \in_array($real, $known, true)) {
                continue;
            }
            $this->addPath($directory, $namespace);
            $known[] = $real;
        }
    }

    /**
     * ORDER OF RESOLUTION:
     *  1. custom loader
     *  2. default Symfony loader (and TwigComponent delegated loaders)
     */
    public function getSourceContext(string $name): \Twig\Source
    {
        if ($this->useCustomLoader && parent::exists($name)) {
            return parent::getSourceContext($name);
        }
        return $this->inner->getSourceContext($name);
    }

    public function exists(string $name): bool
    {
        return ($this->useCustomLoader && parent::exists($name)) || $this->inner->exists($name);
    }

    public function getCacheKey(string $name): string
    {
        if ($this->useCustomLoader && parent::exists($name)) {
            return parent::getCacheKey($name);
        }
        return $this->inner->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        if ($this->useCustomLoader && parent::exists($name)) {
            return parent::isFresh($name, $time);
        }
        return $this->inner->isFresh($name, $time);
    }
}