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
        parent::__construct([], $bundlePath);
        foreach($this->inner->getNamespaces() as $namespace) {
            foreach($this->inner->getPaths() as $path) {
                $this->prependPath(trim($path), $namespace);
            }
        }
    
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

    public function exists(string $name)
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