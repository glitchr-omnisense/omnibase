<?php

namespace Tests\Base\Bridge\Symfony;

use Base\Bridge\Symfony\CsrfTokens;
use Base\Bridge\Symfony\MediaPictures;
use Base\Bridge\Symfony\ScreenResponder;
use Base\Bridge\Symfony\SymfonyScreenContext;
use Base\Bridge\Symfony\WysiwygTexts;
use Base\Contract\Pictures;
use Base\Contract\Runtime;
use Base\Contract\Texts;
use Base\Contract\Tokens;
use Base\Screen\Builder\Stories;
use Base\Screen\ContextProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The screens' Symfony side, wired by the bundle (plan "omnibase agnostique",
 * C5): the context of a request (its locale, the host, the links whose routes
 * exist, the visitor, the theme, the messages left - taken once, as Twig's
 * app.flashes() takes them), the Pictures and Texts contracts answering as
 * `|picture` and `|wysiwyg` do, the registry handing them, the responder and
 * the builder as services.
 */
final class ScreenAdaptersTest extends KernelTestCase
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

    public function testTheServicesAreTheBridges(): void
    {
        $container = static::getContainer();
        self::assertInstanceOf(SymfonyScreenContext::class, $container->get(ContextProvider::class));
        self::assertInstanceOf(ScreenResponder::class, $container->get(ScreenResponder::class));
        self::assertInstanceOf(Stories::class, $container->get(Stories::class));
        self::assertInstanceOf(MediaPictures::class, Runtime::pictures());
        self::assertInstanceOf(WysiwygTexts::class, Runtime::texts());
        self::assertInstanceOf(CsrfTokens::class, Runtime::tokens());
        self::assertNull($container->getParameter('base.screens.version'));
    }

    public function testTheContextIsTheRequests(): void
    {
        $container = static::getContainer();
        $request = Request::create('https://localhost:8443/chroniques');
        $request->setLocale('fr');
        $request->setSession($session = new Session(new MockArraySessionStorage()));
        $session->getFlashBag()->add('blog', 'comment.flash.pending');
        $session->getFlashBag()->add('other', 'kept');
        $container->get('request_stack')->push($request);
        $context = $container->get(ContextProvider::class);

        $frame = $context->frame(['destination' => ['slug' => 'japon']]);
        self::assertSame('fr', $frame->locale);
        self::assertSame('https://localhost:8443', $frame->host);
        self::assertSame(date('Y'), $frame->site->age);
        self::assertSame($container->get('router')->generate('security_login'), $frame->links['login']);
        self::assertArrayHasKey('home', $frame->links);
        self::assertIsArray($frame->links['locales']);
        self::assertNull($frame->viewer);
        self::assertSame(['slug' => 'japon'], $frame->toArray()['destination']);

        self::assertSame(['comment.flash.pending'], $context->flashes('blog'));
        self::assertSame([], $context->flashes('blog'), 'read once, as app.flashes() reads them');
        self::assertSame(['kept'], $session->getFlashBag()->peek('other'), 'the other types left for their page');

        $user = new InMemoryUser('c5-member', null, ['ROLE_USER']);
        $container->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        self::assertSame('c5-member', $context->viewer()?->name);
        self::assertSame('c5-member', $context->frame()->toArray()['viewer']['name']);
    }

    public function testPicturesAndTextsAnswerAsTheFiltersDo(): void
    {
        $container = static::getContainer();
        $request = Request::create('https://localhost/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);
        $twig = $container->get('twig');
        $pictures = $container->get(Pictures::class);
        $texts = $container->get(Texts::class);

        foreach ([null, '', '/uploads/missing.jpg', 'https://example.org/a.png', 'images/logo.png'] as $image) {
            self::assertSame($twig->createTemplate('{{ image|picture(800) }}')->render(['image' => $image]), (string) $pictures->picture($image, 800), (string) $image);
        }

        $editor = json_encode(['time' => 1, 'blocks' => [
            ['id' => 'a', 'type' => 'header', 'data' => ['text' => 'Le départ', 'level' => 2]],
            ['id' => 'b', 'type' => 'paragraph', 'data' => ['text' => 'Tôt, <b>très</b> tôt.']],
        ]]);
        $html = '<h2>Un titre</h2><p>Écrit avec Quill.</p>';
        foreach ([$editor, $html] as $text) {
            self::assertSame($twig->createTemplate('{{ text|wysiwyg|raw }}')->render(['text' => $text]), $texts->render($text));
            self::assertSame(array_values($twig->getExtension(\Base\Twig\Extension\HtmlTwigExtension::class)->getTableOfContents($text)), $texts->toc($text));
        }
        self::assertTrue($texts->isEditor($editor));
        self::assertFalse($texts->isEditor($html));
        self::assertSame('', $texts->render(null));
        self::assertSame($twig->createTemplate('{{ text|wysiwyg|raw }}')->render(['text' => '']), $texts->render(''), 'an empty text: the editor\'s empty frame, as the filter');
        self::assertSame([], $texts->toc(''));
    }

    /** A token of the Tokens contract is one the CSRF token manager accepts for that intent, and only for it - as Twig's csrf_token() is. */
    public function testTokensAreTheFormsTokens(): void
    {
        $container = static::getContainer();
        $request = Request::create('https://localhost/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);
        $manager = $container->get('security.csrf.token_manager');
        $tokens = $container->get(Tokens::class);

        $token = $tokens->csrf('2fa_disable');
        self::assertTrue($manager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('2fa_disable', $token)));
        self::assertFalse($manager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('2fa_email', $token)), 'one intent\'s token is not another\'s');
        $printed = $container->get('twig')->createTemplate("{{ csrf_token('2fa_disable') }}")->render([]);
        self::assertTrue($manager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('2fa_disable', $printed)), 'the template\'s token, for the same intent');
    }
}
