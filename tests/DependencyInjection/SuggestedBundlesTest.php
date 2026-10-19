<?php

namespace Tests\Base\DependencyInjection;

use Base\Contract\Framework;
use Base\Contract\SecurityUserLoader;
use Base\Contract\SecurityUserProvider;
use Base\Entity\User;
use Base\Repository\UserRepository;
use Base\Security\OAuthUserProvider;
use Base\Service\Flysystem;
use Base\Service\FlysystemInterface;
use Base\Security\UserProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\DependencyInjection\Fixtures\WithoutSuggestedBundlesKernel;

/**
 * omniscient/omnibase 4.0 requires its Fondation alone (plan "omnibase agnostique", C7):
 * the framework comes with omnibridge/symfony, the bundles it works with are suggested.
 * composer.json says so, and a Symfony application without the suggested bundles the core
 * can do without still compiles its container and answers its pages.
 */
class SuggestedBundlesTest extends KernelTestCase
{
    /** What left the core's require for omnibridge/symfony (S) or its suggest (B). */
    private const NOT_REQUIRED = [
        'doctrine/doctrine-bundle', 'symfony/config', 'symfony/console', 'symfony/dependency-injection', 'symfony/doctrine-bridge',
        'symfony/dotenv', 'symfony/form', 'symfony/framework-bundle', 'symfony/http-foundation', 'symfony/http-kernel',
        'symfony/messenger', 'symfony/monolog-bundle', 'symfony/routing', 'symfony/security-bundle', 'symfony/security-core',
        'symfony/security-csrf', 'symfony/security-http', 'symfony/twig-bridge', 'symfony/twig-bundle', 'twig/extra-bundle',
        'api-platform/symfony', 'api-platform/doctrine-orm', 'hwi/oauth-bundle', 'scheb/2fa', 'web-auth/webauthn-symfony-bundle',
        'league/flysystem-bundle', 'nelmio/cors-bundle', 'nelmio/security-bundle', 'glitchr/ux-google', 'glitchr/well-known',
        'symfony/webpack-encore-bundle', 'symfony/ux-twig-component',
    ];

    protected static function getKernelClass(): string
    {
        return WithoutSuggestedBundlesKernel::class;
    }

    private static function composer(): array
    {
        return json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testTheCoreRequiresNeitherTheFrameworkNorABundle(): void
    {
        $composer = self::composer();

        $this->assertSame([], array_values(array_intersect(self::NOT_REQUIRED, array_keys($composer['require']))));
        $this->assertArrayNotHasKey('omnibridge/symfony', $composer['require'], 'a Laravel application would receive Symfony');
        foreach (['symfony/var-dumper', 'symfony/error-handler', 'web-auth/webauthn-lib', 'doctrine/common', 'omnibridge/doctrine', 'omnibridge/twig', 'doctrine/orm', 'twig/twig'] as $fondation) {
            $this->assertArrayHasKey($fondation, $composer['require'], $fondation.' is loaded by the Fondation');
        }
        foreach (['scheb/2fa', 'league/flysystem-bundle'] as $package) {
            $this->assertStringNotContainsString('de facto', $composer['suggest'][$package], $package.' is no longer required de facto (C7b)');
        }
        foreach (['omnibridge/symfony', 'scheb/2fa', 'league/flysystem-bundle', 'hwi/oauth-bundle', 'web-auth/webauthn-symfony-bundle', 'api-platform/symfony', 'symfony/webpack-encore-bundle', 'glitchr/ux-google'] as $package) {
            $this->assertArrayHasKey($package, $composer['suggest'], $package.' is suggested');
            $this->assertArrayHasKey($package, $composer['require-dev'], $package.' is there for the core\'s own suite');
        }
        $this->assertSame('4.0.x-dev', $composer['extra']['branch-alias']['dev-4.x']);
    }

    public function testTheUserProviderNamesNoInterfaceOfHwi(): void
    {
        $this->assertNotContains('HWI\\Bundle\\OAuthBundle\\Security\\Core\\User\\OAuthAwareUserProviderInterface', (new \ReflectionClass(UserProvider::class))->getInterfaceNames());
        $this->assertTrue(is_subclass_of(OAuthUserProvider::class, UserProvider::class));
        if (interface_exists('HWI\\Bundle\\OAuthBundle\\Security\\Core\\User\\OAuthAwareUserProviderInterface')) {
            $this->assertContains('HWI\\Bundle\\OAuthBundle\\Security\\Core\\User\\OAuthAwareUserProviderInterface', (new \ReflectionClass(OAuthUserProvider::class))->getInterfaceNames());
        }
    }

    /**
     * Base\Entity\User under Symfony implements every interface it did up to 4.0 (plan "omnibase
     * agnostique", C7b): Security's and scheb/2fa's, through Base\Contract\SecurityUser and
     * TwoFactorUser, the packages being installed here: installed means declared, nothing else
     * decides (C7c).
     */
    public function testTheUserImplementsSecurityAndTwoFactorInterfacesUnderSymfony(): void
    {
        $this->assertTrue(Framework::declares(
            'Symfony\\Component\\Security\\Core\\User\\UserInterface',
            'Scheb\\TwoFactorBundle\\Model\\Totp\\TwoFactorInterface',
        ), 'installed, so declared');
        $this->assertFalse(Framework::declares('Symfony\\Component\\Security\\Core\\User\\UserInterface', 'Nowhere\\Installed\\Interface'), 'one missing, none declared');
        foreach ([
            'Symfony\\Component\\Security\\Core\\User\\UserInterface',
            'Symfony\\Component\\Security\\Core\\User\\PasswordAuthenticatedUserInterface',
            'Scheb\\TwoFactorBundle\\Model\\Totp\\TwoFactorInterface',
            'Scheb\\TwoFactorBundle\\Model\\Email\\TwoFactorInterface',
            'Scheb\\TwoFactorBundle\\Model\\BackupCodeInterface',
            'Scheb\\TwoFactorBundle\\Model\\TrustedDeviceInterface',
        ] as $interface) {
            $this->assertTrue(is_subclass_of(User::class, $interface), $interface);
        }

        // Without its constructor: it reads the application's App\Enum\UserRole, not needed here.
        $user = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->assertNull($user->getTotpAuthenticationConfiguration());
        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->getTotpAuthenticationConfiguration()?->getSecret());
    }

    /**
     * Base\Repository\UserRepository under Symfony implements every interface it did up to 4.0
     * (audit "lesringostars face au master plan", R2): doctrine-bridge's user loader, Security's
     * user provider and password upgrader, through Base\Contract\SecurityUserLoader and SecurityUserProvider - the
     * packages being installed here, installed means declared, as for the user (C7b, C7c).
     */
    public function testTheUsersRepositoryImplementsSecurityInterfacesUnderSymfony(): void
    {
        $this->assertTrue(Framework::declares(
            'Symfony\\Bridge\\Doctrine\\Security\\User\\UserLoaderInterface',
            'Symfony\\Component\\Security\\Core\\User\\UserProviderInterface',
            'Symfony\\Component\\Security\\Core\\User\\PasswordUpgraderInterface',
        ), 'installed, so declared');
        foreach ([
            'Symfony\\Bridge\\Doctrine\\Security\\User\\UserLoaderInterface',
            'Symfony\\Component\\Security\\Core\\User\\UserProviderInterface',
            'Symfony\\Component\\Security\\Core\\User\\PasswordUpgraderInterface',
        ] as $interface) {
            $this->assertTrue(is_subclass_of(UserRepository::class, $interface), $interface);
        }
        $this->assertSame('Base\\Bridge\\Symfony\\SecurityUserProvider', (new \ReflectionClass(SecurityUserProvider::class))->getName(), 'the alias of the Symfony side');
        $this->assertSame('Base\\Bridge\\Symfony\\SecurityUserLoader', (new \ReflectionClass(SecurityUserLoader::class))->getName(), 'the alias of the Symfony side');
    }

    /**
     * Base\Service\Flysystem is the core's own class (C7b): no parent, built from any PSR-11
     * container of operators by name - here a locator, as the `flysystem.storage` tags give it.
     */
    public function testFlysystemIsBuiltFromAContainerOfStorages(): void
    {
        $this->assertFalse(get_parent_class(Flysystem::class));
        $root = sys_get_temp_dir().'/omnibase-flysystem-'.bin2hex(random_bytes(4));
        $flysystem = new Flysystem(new ServiceLocator([
            'local.cache' => fn () => new \League\Flysystem\Filesystem(new \League\Flysystem\Local\LocalFilesystemAdapter($root.'/cache')),
            'local.cache.public' => fn () => new \League\Flysystem\Filesystem(new \League\Flysystem\Local\LocalFilesystemAdapter($root.'/public')),
        ]));

        $this->assertTrue($flysystem->hasStorage('local.cache'));
        $this->assertFalse($flysystem->hasStorage('local.uploads'));
        $this->assertSame(['local.cache'], array_values($flysystem->getStorageNames(false)));
        $this->assertTrue($flysystem->write('a/b.txt', 'abbey road'));
        $this->assertSame('abbey road', $flysystem->read('a/b.txt'));
        $this->assertSame($root.'/cache/a/b.txt', $flysystem->get('a/b.txt'));
        (new Filesystem())->remove($root);
    }

    public function testAnApplicationWithoutTheSuggestedBundlesCompilesAndAnswers(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        // Compiled without debug, so never refreshed by itself: compiled afresh at each run.
        (new Filesystem())->remove(\dirname((new \ReflectionClass('App\\Kernel'))->getFileName(), 2).'/var/cache/test_without_suggested');
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] ??= 'phpunit';
        try {
            $kernel = self::bootKernel(['environment' => 'test', 'debug' => false]);
        } catch (InvalidConfigurationException $e) {
            // A site's own configuration may use a bundle left out (security.yaml's oauth: or
            // webauthn: firewall keys): that application needs the bundle, which is its choice.
            if (!str_contains($e->getMessage(), 'Unrecognized option')) {
                throw $e;
            }
            self::markTestSkipped('The application\'s own configuration uses a bundle left out: '.$e->getMessage());
        }
        $container = static::getContainer();

        $bundles = array_map(fn ($bundle) => $bundle::class, $kernel->getBundles());
        $this->assertSame([], array_values(array_intersect(WithoutSuggestedBundlesKernel::LEFT_OUT, $bundles)));
        $this->assertContains('Base\\BaseBundle', $bundles);

        // The provider of the firewalls, without hwi/oauth-bundle registered: the class follows the
        // package, which is installed here; the service compiles either way.
        $this->assertInstanceOf(UserProvider::class, $container->get('Base\\Security\\UserProvider'));
        // API Platform's decoration dropped with its bundle.
        $this->assertFalse($container->has('Base\\ApiPlatform\\Metadata\\Resource\\Factory\\OverriddenResourceNameCollectionFactory'));
        // ga.service (glitchr/ux-google) and Encore's lookup given as null.
        $this->assertNull((fn () => $this->gaService)->call($container->get('Base\\Subscriber\\AnalyticsSubscriber')));

        // league/flysystem-bundle left out: the `flysystem` service on omnibase's local storages.
        $flysystem = $container->get('flysystem');
        $this->assertInstanceOf(FlysystemInterface::class, $flysystem);
        $this->assertSame(['local.cache', 'local.uploads', 'local.wysiwyg'], array_values($flysystem->getStorageNames(false)));
        $this->assertSame($kernel->getProjectDir().'/var/cache/test/images/', $flysystem->getPublicRoot());
        // scheb/2fa's bundle left out: the user still declares its interfaces (the package is installed).
        $this->assertTrue(is_subclass_of(User::class, 'Scheb\\TwoFactorBundle\\Model\\Totp\\TwoFactorInterface'));

        // Pages: the sign-in page prints the core's encore_entry_script_tags() without Encore's bundle.
        foreach (['/login', '/'] as $uri) {
            $response = $kernel->handle(Request::create($uri));
            $this->assertLessThan(500, $response->getStatusCode(), $uri.': '.substr((string) $response->getContent(), 0, 2000));
        }
        $login = $kernel->handle(Request::create('/login'));
        $this->assertSame(200, $login->getStatusCode());
        $this->assertStringContainsString('security_login', (string) $login->getContent());
    }
}
