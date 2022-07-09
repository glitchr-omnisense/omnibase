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
     * @param string|array $paths    A path or an array of paths where to look for templates
     * @param string|null  $bundlePath The root path common to all relative paths (null for getcwd())
     */
    public function __construct(Environment $twig, AppVariable $appVariable, RandomVariable $randomVariable, BaseService $baseService)
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
        $useCustomLoader = $baseService->getParameterBag("base.twig.use_custom_loader");
        if($useCustomLoader) {

            $bundlePath = $baseService->getParameterBag("base.twig.default_path");
            parent::__construct([], $bundlePath);

            $mainLoader = $this->twig->getLoader();
            if(!$mainLoader instanceof ChainLoader) $loaders = [$this];
            else {

                $loaders = $mainLoader->getLoaders();
                $loaders[] = $this;

                // Override EA from default loader.. otherwise @EasyAdmin bundle gets priority
                if(($defaultLoader = $loaders[0]) )
                    $defaultLoader->prependPath($bundlePath."/easyadmin", "EasyAdmin");
            }

            $chainLoader = new ChainLoader($loaders);
            $twig->setLoader($chainLoader);
        }

        // Add @Twig, @Assets and @Layout variables
        $this->prependPath($projectDir . "/vendor/symfony/twig-bridge/Resources/views", "Twig");
        $this->prependPath($projectDir . "/templates");

        $this->prependPath($projectDir . "/src", "App");
        $this->prependPath($projectDir . "/src/Controller", "Controller");
        $this->prependPath($projectDir . "/assets", "Assets");
        $this->prependPath($projectDir . "/public", "Public");

        $this->prependPath($bundlePath."/easyadmin", "EasyAdmin");
        $this->prependPath($bundlePath, "Base");
        $this->prependPath($bundlePath);

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
