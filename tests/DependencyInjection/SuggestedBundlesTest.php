<?php

namespace Tests\Base\DependencyInjection;

use Base\Security\OAuthUserProvider;
use Base\Security\UserProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
