<?php

namespace Base\Twig\Loader;

use Twig\Loader\ChainLoader;
use Twig\Environment;

use Base\Service\BaseService;
use Base\Twig\AppVariable;
use Base\Twig\Variable\RandomVariable;
use Exception;

/**
 * Loads template from the filesystem.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FilesystemLoader extends \Twig\Loader\FilesystemLoader
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @param string|array $paths    A path or an array of paths where to look for templates
     * @param string|null  $bundlePath The root path common to all relative paths (null for getcwd())
     */
    public function __construct(\Twig\Loader\FilesystemLoader $defaultLoader, Environment $twig, AppVariable $appVariable, RandomVariable $randomVariable, BaseService $baseService)
    {
        $this->twig = $twig;

        // Add base service to the default variables
        $this->twig->addGlobal("server", $_SERVER);

        $this->twig->addGlobal("random", $randomVariable);
        $this->twig->addGlobal("base",   $baseService);
        $this->twig->addGlobal("app" ,   $appVariable);

        // Setup custom loader, to prevent the known issues of the default symfony TwigLoader
        // 1/ Cannot override <form_div_layout class="html twig">
        // 2/ Infinite loop when using {%use%}
        $projectDir = $baseService->getProjectDir();
        $useCustomLoader = $baseService->getParameterBag("base.twig.use_custom");
        if($useCustomLoader) {

            $bundlePath = $baseService->getParameterBag("base.twig.default_path");
            parent::__construct([], $bundlePath);

            $chainLoader = $this->twig->getLoader();
            if(!$chainLoader instanceof ChainLoader) $loaders = [$this];
            else {

                $loaders = $chainLoader->getLoaders();
                $loaders[] = $this;

                // Override EA from default loader.. otherwise @EasyAdmin bundle gets priority
                if(($mainLoader = $loaders[0]) )
                    $mainLoader->prependPath($bundlePath."/easyadmin", "EasyAdmin");
            }

            if($baseService->getRouter()->isProfiler()) array_unshift($loaders, $defaultLoader);
            else $loaders[] = $defaultLoader;

            $chainLoader = new ChainLoader($loaders);
            $twig->setLoader($chainLoader);

            // Add @Twig, @Assets and @Layout variables
            $this->prependPath($bundlePath."/inspector", "WebProfiler");
            $this->prependPath($bundlePath."/easyadmin", "EasyAdmin");
            $this->prependPath($bundlePath);
        }


        $this->prependPath($projectDir . "/src", "App");
        $this->prependPath($projectDir . "/src/Controller", "Controller");
        $this->prependPath($projectDir . "/public", "Public");
        $this->prependPath($projectDir . "/vendor/symfony/twig-bridge/Resources/views", "Twig");
        $this->prependPath($projectDir . "/templates");

        // Add additional @Namespace variables
        $paths = $baseService->getParameterBag("base.twig.paths") ?? [];
        foreach($paths as $entry) {

            $namespace = $entry["namespace"] ?? self::MAIN_NAMESPACE;

            $path = $entry["path"] ?? null;
            if (empty($path))
                throw new Exception("Missing path variable for @".$namespace." in \"base.twig.paths\"");

            $this->prependPath($path, $namespace);
        }
    }
}
