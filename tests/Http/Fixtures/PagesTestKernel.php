<?php

namespace Tests\Base\Http\Fixtures;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The host application (the harness, or the application whose suite this
 * runs in) with the pages a test of the core needs and no host has to
 * carry (PagesController, under /base-test/): a page at an address per
 * language and one at a single address (PageLanguageHttpTest), a page with
 * the core's emoji field (NothingFromElsewhereHttpTest). The tests asked
 * them of the harness's own App\DemoController - /welcome, /language,
 * /fields -, which an application does not have: there one path segment may
 * be anything of its own (lesringostars: a destination).
 *
 * A kernel of its own rather than a child of App\Kernel, as Demo's
 * DemoTestKernel: an application's kernel may read its configuration by
 * paths relative to its own file.
 */
final class PagesTestKernel extends Kernel
{
    use MicroKernelTrait {
        configureContainer as private configureHostContainer;
        loadRoutes as private loadHostRoutes;
    }

    /** Before the host's own routes: a host may answer any address of one or two segments. */
    public function loadRoutes(LoaderInterface $loader): RouteCollection
    {
        $collection = $this->loadHostRoutes($loader);
        $controller = PagesController::class;
        $pages = [
            'base_test_welcome' => new Route('/base-test/welcome', ['_controller' => $controller.'::welcome']),
            'base_test_fields' => new Route('/base-test/fields', ['_controller' => $controller.'::fields']),
        ];
        // A localized route, as Symfony's attribute loader makes one of #[Route(['en' => ..., 'fr' => ...])].
        foreach (['en' => '/base-test/language', 'fr' => '/base-test/langue'] as $locale => $path) {
            $pages['base_test_language.'.$locale] = new Route($path, ['_controller' => $controller.'::language', '_locale' => $locale, '_canonical_route' => 'base_test_language'], ['_locale' => preg_quote($locale)]);
        }
        foreach ($pages as $name => $route) {
            $collection->add($name, $route->setMethods(['GET']), 1);
        }

        return $collection;
    }

    public function getProjectDir(): string
    {
        // The host application's folder: the root package Composer installed (App\\Kernel's dirname(2) before).
        return (string) realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.'_pages_test';
    }

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        // MicroKernelTrait's configureContainer() takes the configurator alone under Symfony 8, the loader and the builder too before.
        $arguments = [$container, $loader, $builder];
        $this->configureHostContainer(...\array_slice($arguments, 0, (new \ReflectionMethod($this, 'configureHostContainer'))->getNumberOfParameters()));

        $container->extension('framework', ['test' => true]);
        $container->extension('twig', ['paths' => [__DIR__.'/templates' => 'BaseTestPages']]);
        $container->services()->set(PagesController::class)->autowire()->autoconfigure()->public()->tag('controller.service_arguments');
    }
}
