<?php

namespace Base\Testing;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A package's bundles on the core alone, for its tests (omnibase 7.2): Symfony's framework, security and Twig,
 * omnibase's BaseBundle (omnibridge/symfony) and the bundles given, on omnibridge/eloquent and an SQLite database in a
 * folder of the system's temporary one - no application of its own (its User is omnibase's, aliased), no Doctrine
 * needed. With WithoutPackages it proves a package runs where Doctrine is not installed:
 *
 *     #[RunInSeparateProcess]
 *     #[PreserveGlobalState(false)]
 *     public function testTheFaqWithoutDoctrine(): void
 *     {
 *         WithoutPackages::install();
 *         $kernel = new PackageKernel([AdminBundle::class, FaqBundle::class], ['faq' => [...]]);
 *         $kernel->boot();
 *         $kernel->createSchema();                                   // omnibridge:schema:create
 *         $questions = $kernel->getContainer()->get(Repositories::class)->of(Question::class);
 *         ...
 *     }
 *
 * The configuration: the minimum each extension needs (framework: a secret, test, a mock session; security: omnibase's
 * users on the firewall `main`; base: persistence eloquent), merged under the configuration given by extension;
 * `routes`, a closure given a RoutingConfigurator, imports the package's routes. The entities are discovered as on a
 * site (Base\Discovery's `entities`: the core's, each bundle's src/Entity). Needs omnibridge/symfony and
 * omnibridge/eloquent installed (a package's require-dev).
 */
final class PackageKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param list<class-string>                  $packages the package's bundles, and those it requires, after omnibase's
     * @param array<string, array<string, mixed>> $config  more configuration, by extension (framework, security, base, a package's)
     * @param (\Closure(RoutingConfigurator): void)|null $routes the routes
     * @param string|null                         $name    the folder's name under the system's temporary one (one per kernel class of a test)
     */
    public function __construct(
        private readonly array $packages = [],
        private readonly array $config = [],
        private readonly ?\Closure $routes = null,
        string $environment = 'test',
        bool $debug = true,
        private readonly ?string $name = null,
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        $bundles = [
            \Symfony\Bundle\FrameworkBundle\FrameworkBundle::class,
            \Symfony\Bundle\SecurityBundle\SecurityBundle::class,
        ];
        foreach ([\Symfony\Bundle\TwigBundle\TwigBundle::class, \Twig\Extra\TwigExtraBundle\TwigExtraBundle::class] as $optional) {
            if (class_exists($optional)) {
                $bundles[] = $optional;
            }
        }
        $bundles[] = \Base\BaseBundle::class;
        foreach (array_values(array_unique([...$bundles, ...$this->packages])) as $bundle) {
            yield new $bundle();
        }
    }

    /** The folder of its cache, logs, database and empty project. */
    public function varDir(): string
    {
        return sys_get_temp_dir().'/omnibase-package-kernel/'.($this->name ?? substr(md5(serialize([$this->packages, array_keys($this->config)])), 0, 12));
    }

    /** The database: an SQLite file of the kernel's folder. */
    public function databaseUrl(): string
    {
        return 'sqlite:///'.$this->varDir().'/database.sqlite';
    }

    public function getProjectDir(): string
    {
        $project = $this->varDir().'/project';
        if (!is_dir($project.'/templates')) {
            @mkdir($project.'/templates', 0777, true);
        }

        return $project;
    }

    public function getCacheDir(): string
    {
        return $this->varDir().'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->varDir().'/log';
    }

    /**
     * The tables of the installed entities, created from their description in the empty database
     * (omnibridge:schema:create), the database emptied first.
     *
     * @return string the command's output
     */
    public function createSchema(): string
    {
        $database = $this->varDir().'/database.sqlite';
        if (is_file($database)) {
            unlink($database);
        }
        // Eloquent's SQLite connector opens an existing file only.
        @mkdir(\dirname($database), 0777, true);
        touch($database);
        if (!$this->booted) {
            $this->boot();
        }
        $application = new Application($this);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $code = $application->run(new ArrayInput(['command' => 'omnibridge:schema:create']), $output);
        if (0 !== $code) {
            throw new \RuntimeException('omnibridge:schema:create failed: '.$output->fetch());
        }

        return $output->fetch();
    }

    /** The folder emptied: cache, logs, database. */
    public function clean(): void
    {
        (new \Symfony\Component\Filesystem\Filesystem())->remove($this->varDir());
    }

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $defaults = [
            'framework' => [
                'secret' => 'omnibase-package-kernel',
                'test' => true,
                'http_method_override' => false,
                'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
                'router' => ['utf8' => true],
                'csrf_protection' => true,
            ],
            'security' => [
                'password_hashers' => ['Base\\Entity\\User' => 'plaintext'],
                'providers' => ['users' => ['id' => 'Base\\Security\\UserProvider']],
                'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users']],
            ],
            'base' => ['persistence' => 'eloquent'],
        ];
        if ($builder->hasExtension('twig')) {
            $defaults['twig'] = ['default_path' => '%kernel.project_dir%/templates'];
        }
        foreach (array_keys($defaults + $this->config) as $extension) {
            $container->extension($extension, array_replace_recursive($defaults[$extension] ?? [], $this->config[$extension] ?? []));
        }
        $container->parameters()->set('env(DATABASE_URL)', $this->databaseUrl());
        // Quiet: Symfony's default logger writes to stderr, which a test run in its own process reads as a failure.
        $container->services()->set('logger', \Psr\Log\NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        if (null !== $this->routes) {
            ($this->routes)($routes);
        }
    }
}
