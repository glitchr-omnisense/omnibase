<?php

namespace Tests\Base\Bridge\Symfony;

use Base\Bridge\Symfony\ScreenResponder;
use Base\Screen\Card;
use Base\Screen\ContextProvider;
use Base\Screen\Frame;
use Base\Screen\Site;
use Base\Screen\View;
use Base\Screen\Viewer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * A screen answered under Symfony (plan "omnibase agnostique", C5): the HTML
 * by Twig with `frame` and `screen` (and a site's variables of before); a
 * visit within a front end by omnibridge/react's protocol - the View's
 * component, the frame's and the screen's props together, the version, 409
 * for another version -; a plain JSON request without omnibridge/react; each
 * answer varying on what chose it, and a JSON answer carrying a visitor
 * private to them and varying on the session's cookie.
 */
final class ScreenResponderTest extends TestCase
{
    private static function protocol(): bool
    {
        if (class_exists(\Omnibridge\React\Protocol::class)) {
            return true;
        }
        // omnibridge/react beside the core (the workspace), or mounted by the harness's checkouts overlay.
        foreach ([\dirname(__DIR__, 5).'/omnibridge/react/src', '/srv/omnibridge/react/src'] as $src) {
            if (is_file($src.'/Protocol.php')) {
                spl_autoload_register(static function (string $class) use ($src): void {
                    if (str_starts_with($class, 'Omnibridge\\React\\') && is_file($file = $src.'/'.str_replace('\\', '/', substr($class, \strlen('Omnibridge\\React\\'))).'.php')) {
                        require $file;
                    }
                });

                return class_exists(\Omnibridge\React\Protocol::class);
            }
        }

        return false;
    }

    private function responder(?Viewer $viewer = null, ?bool $omnibridge = null, ?string $version = '3f2a'): ScreenResponder
    {
        $twig = new Environment(new ArrayLoader([
            'card.html.twig' => '<html lang="{{ frame.locale }}"><h1>{{ screen.title }}</h1><a href="{{ screen.url }}">{{ frame.site.title }}</a>{{ legacy|default("") }}</html>',
        ]));
        $context = new class($viewer) implements ContextProvider {
            public function __construct(private ?Viewer $viewer)
            {
            }

            public function frame(array $extra = []): Frame
            {
                return new Frame('fr', new Site('Le blog', age: '2026'), ['home' => '/'], $this->viewer, ['scheme' => 'light'], $extra, 'https://example.org');
            }

            public function viewer(): ?Viewer
            {
                return $this->viewer;
            }

            public function flashes(string $type): array
            {
                return [];
            }
        };

        return new ScreenResponder($twig, $context, $version, $omnibridge);
    }

    private static function view(): View
    {
        return new View('card.html.twig', new Card('le-fuji', 'Le Fuji', '/chroniques/le-fuji'), 'Blog/Post');
    }

    public function testTheHtmlIsTheTemplateWithTheFrameAndTheScreen(): void
    {
        $response = $this->responder(omnibridge: false)->respond(Request::create('/chroniques/le-fuji'), self::view(), ['legacy' => 'before']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html lang="fr"><h1>Le Fuji</h1><a href="/chroniques/le-fuji">Le blog</a>before</html>', $response->getContent());
        self::assertContains('Accept', $response->getVary());

        $status = $this->responder(omnibridge: false)->respond(Request::create('/x'), new View('card.html.twig', new Card('a', 'A', '/a'), status: 422));
        self::assertSame(422, $status->getStatusCode());
    }

    public function testWithoutOmnibridgeAJsonRequestGetsTheProps(): void
    {
        $request = Request::create('/chroniques/le-fuji', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->responder(omnibridge: false)->respond($request, self::view());

        self::assertSame('application/json', $response->headers->get('Content-Type'));
        $props = json_decode((string) $response->getContent(), true);
        self::assertSame(['locale', 'site', 'links', 'viewer', 'theme', 'slug', 'title'], \array_slice(array_keys($props), 0, 7));
        self::assertSame('Le Fuji', $props['title']);
        self::assertContains('Accept', $response->getVary());
        self::assertNotContains('Cookie', $response->getVary(), 'nobody signed in: nothing personal');

        $html = $this->responder(omnibridge: false)->respond(Request::create('/x', server: ['HTTP_ACCEPT' => 'text/html,application/json;q=0.9']), self::view());
        self::assertStringStartsWith('<html', (string) $html->getContent(), 'a browser asking HTML first gets the page');
    }

    public function testAJsonAnswerCarryingAVisitorIsTheirsAlone(): void
    {
        $request = Request::create('/chroniques/le-fuji', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->responder(new Viewer(3, 'louis'), omnibridge: false)->respond($request, self::view());

        self::assertSame(['id' => 3, 'name' => 'louis', 'avatar' => null], json_decode((string) $response->getContent(), true)['viewer']);
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertContains('Cookie', $response->getVary());
    }

    public function testAVisitWithinTheFrontEndGetsTheOmnibridgePage(): void
    {
        if (!self::protocol()) {
            self::markTestSkipped('omnibridge/react is not installed (nor beside the core).');
        }
        $request = Request::create('/chroniques/le-fuji?x=1', server: ['HTTP_X_OMNIBRIDGE' => 'true', 'HTTP_X_OMNIBRIDGE_VERSION' => '3f2a']);
        $response = $this->responder()->respond($request, self::view());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('3f2a', $response->headers->get('X-Omnibridge-Version'));
        self::assertContains('X-Omnibridge', $response->getVary());
        $page = json_decode((string) $response->getContent(), true);
        self::assertSame(['component', 'props', 'url', 'version'], array_keys($page));
        self::assertSame(['Blog/Post', '/chroniques/le-fuji?x=1', '3f2a'], [$page['component'], $page['url'], $page['version']]);
        self::assertSame($this->responder()->props((new \ReflectionProperty(ScreenResponder::class, 'context'))->getValue($this->responder())->frame(), self::view()), $page['props']);
        self::assertNotContains('Cookie', $response->getVary());

        $signedIn = $this->responder(new Viewer(3, 'louis'))->respond($request, self::view());
        self::assertTrue($signedIn->headers->hasCacheControlDirective('private'));
        self::assertContains('Cookie', $signedIn->getVary());
        self::assertContains('X-Omnibridge', $signedIn->getVary());

        $stale = $this->responder()->respond(Request::create('/chroniques/le-fuji', server: ['HTTP_X_OMNIBRIDGE' => 'true', 'HTTP_X_OMNIBRIDGE_VERSION' => 'old']), self::view());
        self::assertSame(409, $stale->getStatusCode());
        self::assertSame('/chroniques/le-fuji', $stale->headers->get('X-Omnibridge-Location'));

        $html = $this->responder()->respond(Request::create('/chroniques/le-fuji', server: ['HTTP_ACCEPT' => 'application/json']), self::view());
        self::assertStringStartsWith('<html', (string) $html->getContent(), 'with omnibridge/react, JSON is its protocol\'s only');
        self::assertContains('X-Omnibridge', $html->getVary());
    }

    /** A screen whose props would take a key of the frame's is refused, in the HTML as in the JSON: its own would be lost. */
    public function testAScreenMayNotTakeTheFramesKeys(): void
    {
        $screen = new class implements \Base\Screen\Screen {
            public string $title = 'Le blog';
            public string $url = '/chroniques';

            public function toArray(): array
            {
                return ['title' => $this->title, 'links' => ['index' => $this->url]];
            }

            public function jsonSerialize(): array
            {
                return $this->toArray();
            }
        };
        foreach ([[], ['HTTP_ACCEPT' => 'application/json']] as $server) {
            try {
                $this->responder(omnibridge: false)->respond(Request::create('/chroniques', server: $server), new View('card.html.twig', $screen));
                self::fail('A screen taking "links" is refused.');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('"links"', $e->getMessage());
            }
        }
    }

    /** A screen carrying its viewer's secrets (its forms' tokens) is kept out of every cache: private, no-store, varying on Cookie - the HTML as the JSON, a visitor or not. */
    public function testAConfidentialScreenIsStoredByNoCache(): void
    {
        $screen = new class implements \Base\Screen\Confidential {
            public string $title = 'Réglages';
            public string $url = '/settings';

            public function toArray(): array
            {
                return ['title' => $this->title, 'token' => 'abc'];
            }

            public function jsonSerialize(): array
            {
                return $this->toArray();
            }
        };
        foreach ([null, new Viewer(3, 'louis')] as $viewer) {
            foreach ([[], ['HTTP_ACCEPT' => 'application/json']] as $server) {
                $response = $this->responder($viewer, omnibridge: false)->respond(Request::create('/settings', server: $server), new View('card.html.twig', $screen));
                self::assertTrue($response->headers->hasCacheControlDirective('private'));
                self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
                self::assertFalse($response->headers->hasCacheControlDirective('public'));
                self::assertContains('Cookie', $response->getVary());
            }
        }
        // An ordinary screen's HTML is left to the framework, as before.
        $html = $this->responder(new Viewer(3, 'louis'), omnibridge: false)->respond(Request::create('/chroniques/le-fuji'), self::view());
        self::assertFalse($html->headers->hasCacheControlDirective('no-store'));
    }
}
