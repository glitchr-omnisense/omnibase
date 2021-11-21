<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Base\Twig\Loader;

use Twig\Error\LoaderError;
use Twig\Environment;
use Twig\Source;

use Base\Service\BaseService;
use Exception;
use Twig\Loader\ChainLoader;

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
    public function __construct(BaseService $baseService, Environment $twig)
    {
        $this->twig = $twig;

        // Add base service to the default variables
        $this->twig->addGlobal("base", $baseService);
        $this->twig->addGlobal("base.settings", $baseService->getSettings()->get("base.settings"));
        $this->twig->addGlobal("app.settings",  $baseService->getSettings()->get("app.settings"));

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

        $this->prependPath($bundlePath."/easyadmin", "EasyAdmin");
        $this->prependPath($bundlePath, "Base");
        $this->prependPath($bundlePath);
        
        // Add additional @Namespace variables
        $paths = $baseService->getParameterBag("base.twig.paths");
        foreach($paths as $entry) {

            $namespace = $entry["namespace"] ?? self::MAIN_NAMESPACE;

            $path = $entry["path"] ?? null;
            if (empty($path))
                throw new Exception("Missing path variable for @".$namespace." in \"base.twig.paths\"");

            $this->prependPath($path, $namespace);
        }
    }
}
