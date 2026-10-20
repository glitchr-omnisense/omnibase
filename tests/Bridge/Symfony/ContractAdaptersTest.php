<?php

namespace Tests\Base\Bridge\Symfony;

use Base\Bridge\Symfony\FlysystemFiles;
use Base\Bridge\Symfony\LocalizerLocales;
use Omnibridge\Symfony\RouterUrls;
use Omnibridge\Symfony\Contract\SecurityAccess;
use Omnibridge\Symfony\TranslatorTranslations;
use Base\Contract\Access;
use Base\Contract\Files;
use Base\Contract\Locales;
use Base\Contract\Runtime;
use Base\Contract\Urls;
use Base\DependencyInjection\Compiler\Pass\ContractsPass;
use Base\Entity\Thread;
use Base\Service\BaseService;
use Omnibridge\Twig\Contract\Translations;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The contracts of the Foundation under Symfony (plan "omnibase agnostique",
 * C4): the bundle fills Base\Contract\Runtime at boot with omnibase's adapters
 * (Base\Bridge\Symfony\*) and the framework's clock, event dispatcher and
 * mailer, and each adapter answers what the services entities reach today
 * through BaseTrait answer - the router, the translator, BaseService's
 * isGranted(), the Localizer, Flysystem - and what Twig's path(), url(),
 * asset(), |trans and is_granted() print: the same strings, compared here one
 * by one. C3e moves the entities' getters onto these contracts on that
 * guarantee.
 */
final class ContractAdaptersTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get('security.token_storage')->setToken(null);
        parent::tearDown();
    }

    public function testTheBundleFillsTheRegistryWithItsAdaptersAndTheFrameworksServices(): void
    {
        $container = static::getContainer();

        self::assertSame($container->get(ContractsPass::SERVICE), Runtime::getContainer());
        self::assertInstanceOf(RouterUrls::class, Runtime::urls());
        self::assertInstanceOf(TranslatorTranslations::class, Runtime::translations());
        self::assertInstanceOf(SecurityAccess::class, Runtime::access());
        self::assertInstanceOf(LocalizerLocales::class, Runtime::locales());
        self::assertInstanceOf(FlysystemFiles::class, Runtime::files());
        self::assertSame($container->get('event_dispatcher'), Runtime::events());
        self::assertInstanceOf(EventDispatcherInterface::class, Runtime::events());

        // The framework's clock (symfony/clock) and mailer, where the application has them.
        if ($container->has('clock')) {
            self::assertSame($container->get('clock'), Runtime::clock());
        }
        self::assertInstanceOf(ClockInterface::class, Runtime::clock());
        if (Runtime::has(MailerInterface::class)) {
            self::assertInstanceOf(MailerInterface::class, Runtime::mailer());
        }

        // Autowirable by the contract's name, the same objects as the registry's.
        self::assertSame(Runtime::urls(), $container->get(ContractsPass::SERVICE)->get(Urls::class));
    }

    /**
     * The host's contracts are omniscient/omnibridge's since A2: the container
     * binds each under its own name (Omnibridge\Contract\*) and its former names
     * alias it - the same object autowired by either, in the locator under
     * either, and what the registry answers.
     */
    public function testEachContractIsOneServiceUnderItsOwnAndItsFormerNames(): void
    {
        $container = static::getContainer();
        $locator = $container->get(ContractsPass::SERVICE);

        foreach (ContractsPass::FORMER as $former => $contract) {
            if (!$locator->has($contract)) {
                continue;
            }
            self::assertSame($locator->get($contract), $locator->get($former), $former);
            self::assertSame(Runtime::get($contract), Runtime::get($former), $former);
            self::assertSame(Runtime::get($contract), $locator->get($contract), $contract);
        }
        self::assertSame(Runtime::urls(), $container->get(\Omnibridge\Contract\Urls::class));
        self::assertSame(Runtime::urls(), $container->get(Urls::class));
        self::assertSame(Runtime::translations(), $container->get(Translations::class));
        self::assertSame(Runtime::translations(), $container->get(\Base\Contract\Translations::class));
    }

    public function testANewKernelReplacesTheRegistrysServices(): void
    {
        $urls = Runtime::urls();
        $locator = Runtime::getContainer();

        self::ensureKernelShutdown();
        self::bootKernel();

        self::assertNotSame($locator, Runtime::getContainer());
        self::assertNotSame($urls, Runtime::urls(), 'still the previous kernel\'s adapter');
        self::assertInstanceOf(RouterUrls::class, Runtime::urls());
    }

    public function testUrlsAreTheRoutersAndTwigsPathUrlAndAsset(): void
    {
        $router = static::getContainer()->get('advanced_router');
        $urls = Runtime::urls();
        $checked = 0;

        foreach ([['security_login', []], ['security_logout', []], ['user_profile', []]] as [$route, $parameters]) {
            if (null === $router->getRouteCollection()->get($route)) {
                continue;
            }
            self::assertSame($router->generate($route, $parameters), $urls->path($route, $parameters), $route);
            self::assertSame($router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL), $urls->url($route, $parameters), $route);
            self::assertSame($this->render("{{ path('$route') }}"), $urls->path($route), $route.', Twig\'s path()');
            self::assertSame($this->render("{{ url('$route') }}"), $urls->url($route), $route.', Twig\'s url()');
            self::assertStringStartsWith('http', $urls->url($route));
            ++$checked;
        }
        self::assertGreaterThan(0, $checked, 'a route of the core was compared');

        foreach (['bundles/base/base-async.js', 'images/logo.svg'] as $asset) {
            self::assertSame($this->render("{{ asset('$asset') }}"), $urls->asset($asset), $asset);
            self::assertSame($router->getAssetUrl($asset), $urls->asset($asset), $asset.', AdvancedRouter::getAssetUrl()');
        }
    }

    public function testTranslationsAreTheTranslatorsAndTwigsTrans(): void
    {
        $translator = static::getContainer()->get('translator');
        $translations = Runtime::translations();

        $cases = [
            ['notice.account', ['label' => 'Rédaction', 'identifier' => 'demo@example.test', 'password' => 'x'], 'demo', 'fr'],
            ['notice.account', ['label' => 'Editor', 'identifier' => 'demo@example.test', 'password' => 'x'], 'demo', 'en'],
            ['notice.sign_in', ['count' => 1], 'demo', 'fr'],
            ['notice.sign_in', ['count' => 3], 'demo', 'fr'],
            ['@emails.fake_test.notice', [], null, 'fr'],
            ['@emails.admin_forwarding.notice', ['Alice', 'Hello'], null, 'en'],
            ['nothing.of.the.kind', [], 'demo', 'fr'],
        ];
        foreach ($cases as [$id, $parameters, $domain, $locale]) {
            $expected = $translator->trans($id, $parameters, $domain, $locale);
            self::assertSame($expected, $translations->trans($id, $parameters, $domain, $locale), $id.' ('.$locale.')');
            self::assertSame($expected, BaseService::getTranslator()->trans($id, $parameters, $domain, $locale), $id.', through BaseTrait');
        }
        self::assertStringContainsString('Rédaction', $translations->trans('notice.account', $cases[0][1], 'demo', 'fr'), 'an ICU message, its placeholders replaced');
        self::assertSame('Se connecter ou choisir un autre rôle', $translations->trans('notice.sign_in', ['count' => 3], 'demo', 'fr'), 'an ICU plural');

        self::assertSame(
            htmlspecialchars($translations->trans('notice.sign_in', ['count' => 1], 'demo', 'fr'), \ENT_QUOTES),
            $this->render("{{ 'notice.sign_in'|trans({count: 1}, 'demo', 'fr') }}"),
            'Twig\'s |trans'
        );
    }

    public function testAccessIsBaseServicesIsGrantedAndTwigsIsGranted(): void
    {
        $container = static::getContainer();
        $access = Runtime::access();
        $thread = new Thread();
        $attributes = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPERADMIN', 'IS_AUTHENTICATED', 'ARTICLE.PUBLISH'];

        // Nobody signed in, then an administrator: the same answers as BaseService and Twig, with and without a subject.
        foreach ([null, new InMemoryUser('c4-admin', null, ['ROLE_ADMIN'])] as $user) {
            $container->get('security.token_storage')->setToken($user ? new UsernamePasswordToken($user, 'main', $user->getRoles()) : null);
            self::assertSame($user, $access->getUser());

            foreach ($attributes as $attribute) {
                foreach ([null, $thread] as $subject) {
                    $label = $attribute.($subject ? ' on a Thread' : '').($user ? ', signed in' : ', nobody');
                    self::assertSame($container->get('security.authorization_checker')->isGranted($attribute, $subject), $access->isGranted($attribute, $subject), $label);
                    self::assertSame(BaseService::getService()->isGranted($attribute, $subject), $access->isGranted($attribute, $subject), $label.', BaseService');
                }
                self::assertSame($access->isGranted($attribute) ? 'yes' : 'no', $this->render("{{ is_granted('$attribute') ? 'yes' : 'no' }}"), $attribute.', Twig\'s is_granted()');
            }
        }
        self::assertTrue($access->isGranted('ROLE_ADMIN'), 'the administrator is one');
    }

    public function testLocalesAreTheLocalizers(): void
    {
        $localizer = static::getContainer()->get('localizer');
        $locales = Runtime::locales();

        self::assertSame($localizer->getLocale(), $locales->getLocale());
        self::assertSame($localizer::getDefaultLocale(), $locales->getDefaultLocale());
        self::assertSame(array_values($localizer::getAvailableLocales()), $locales->getAvailableLocales());
        self::assertSame($locales->getDefaultLocale(), $locales->getAvailableLocales()[0], 'the default first');
        self::assertMatchesRegularExpression('/^[a-z]{2}-[A-Z]{2}$/', $locales->getLocale(), 'omnibase\'s form, fr-FR');
        self::assertSame(BaseService::getLocalizer()->getLocale(), $locales->getLocale(), 'through BaseTrait');

        $localizer->setLocale('en-GB');
        self::assertSame($localizer->getLocale(), $locales->getLocale(), 'the locale changed is the one answered');
    }

    public function testFilesAreFlysystems(): void
    {
        $flysystem = static::getContainer()->get('flysystem');
        $files = Runtime::files();
        $publicDir = $flysystem::getPublicDir();

        // A file of a storage under public/ (local.uploads.public), and the same path in storages it is not in.
        $flysystem->write('c4/contract.txt', 'contract', 'local.uploads.public');
        try {
            foreach (['local.uploads.public', 'local.uploads', 'local.cache', null] as $storage) {
                $public = $flysystem->getPublic('c4/contract.txt', $storage);
                $public = is_string($public) && '' !== $public ? $public : null;
                self::assertSame($public, $files->publicPath('c4/contract.txt', $storage), (string) $storage);
                self::assertSame(null === $public ? null : str_lstrip($public, $publicDir), $files->publicUrl('c4/contract.txt', $storage), (string) $storage.', the editor\'s str_lstrip()');
                self::assertSame((bool) $flysystem->isRemote($storage), $files->isRemote($storage), (string) $storage);
                self::assertSame($flysystem->prefixPath('c4/contract.txt', $storage), $files->localPath('c4/contract.txt', $storage), (string) $storage);
            }
            self::assertSame($publicDir.'/uploads/c4/contract.txt', $files->publicPath('c4/contract.txt', 'local.uploads.public'), 'a public storage\'s file is under public/');
            self::assertSame('/uploads/c4/contract.txt', $files->publicUrl('c4/contract.txt', 'local.uploads.public'), 'and served from there');
            self::assertSame('contract', file_get_contents((string) $files->localPath('c4/contract.txt', 'local.uploads.public')), 'a path PHP reads');
            self::assertFalse($files->isRemote('local.uploads.public'));
        } finally {
            $flysystem->delete('c4/contract.txt', 'local.uploads.public');
        }
    }

    private function render(string $template): string
    {
        $twig = static::getContainer()->get('twig');

        return $twig->createTemplate($template)->render();
    }
}
