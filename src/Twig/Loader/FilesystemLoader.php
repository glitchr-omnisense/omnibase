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
     * @param string|null  $rootPath The root path common to all relative paths (null for getcwd())
     */
    public function __construct(BaseService $baseService, Environment $twig)
    {
        // Setup custom loader, to prevent the known issues of the default symfony TwigLoader
        // 1/ Cannot override <form_div_layout class="html twig">
        // 2/ Infinite loop when using {%use%} (Actually this needs to be fixed.. TODO)

        $useCustomLoader = $baseService->getParameterBag("base.twig.use_custom_loader");
        if($useCustomLoader) {

            $rootPath = $baseService->getParameterBag("base.twig.default_path");
            $paths = $baseService->getParameterBag("base.twig.form_themes");

            parent::__construct($paths, $rootPath);

            $mainLoader = $twig->getLoader();
            if($mainLoader instanceof ChainLoader) {

                $loaders = $twig->getLoader()->getLoaders();
                array_unshift($loaders, $this);

            } else {

                $loaders = [$this];
            }

            $chainLoader = new ChainLoader($loaders);
            $twig->setLoader($chainLoader);
        }

        // Add @Twig, @Assets and @BaseLayout variables
        $projectDir = $baseService->getProjectDir();
        $this->addPath($projectDir . "/vendor/symfony/twig-bridge/Resources/views", "Twig");
        $this->addPath($projectDir . "/vendor/xkzl/base-bundle/templates/layout");

        $this->addPath($projectDir . "/assets", "Assets");

        $this->addPath($projectDir . "/src", "App");
        $this->addPath($projectDir . "/src/Controller", "Controller");

        // Add additional @Namespace variables
        $paths = $baseService->getParameterBag("base.twig.paths");
        foreach($paths as $entry) {

            $namespace = $entry["namespace"] ?? self::MAIN_NAMESPACE;

            $path = $entry["path"] ?? null;
            if (empty($path))
                throw new Exception("Missing path variable for @".$namespace." in \"base.twig.paths\"");

            $this->addPath($path, $namespace);
        }

        // Add base service to the default variables
        $twig->addGlobal("base", $baseService);
    }
}
