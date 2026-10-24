<?php

namespace Tests\Base\Screen;

use Base\Screen\Answer;
use Base\Screen\Cache;
use Base\Screen\File;
use Base\Screen\Fragments;
use Base\Screen\Json;
use Base\Screen\Negotiation;
use Base\Screen\Redirect;
use Base\Screen\Shell;
use Base\Screen\Template;
use Base\Screen\Visit;
use PHPUnit\Framework\TestCase;

/**
 * How an action's result is answered, framework-free (step R10 of the audit
 * "lesringostars face au master plan"): a Template's markup, or its data in
 * JSON for a client preferring it when it allows it, both varying on Accept;
 * Json with its templates rendered (Fragments joined), encoded as Symfony's
 * JsonResponse encodes; a Shell by omnibridge/react's protocol (the shell, the
 * page in JSON, 409); a File as it is; each with the caching its action
 * asked. A View's answers are ScreenResponderTest's (Symfony's adapter).
 */
final class NegotiationTest extends TestCase
{
    /** @var list<array{string, string}> what was rendered, and how */
    private array $rendered = [];

    private function negotiation(): Negotiation
    {
        return new Negotiation(function (string $template, array $context, string $as): string {
            $this->rendered[] = [$template, $as];

            return '<'.$template.'>'.json_encode($context, \JSON_UNESCAPED_SLASHES).'</'.$template.'>';
        });
    }

    public function testATemplateIsItsMarkup(): void
    {
        $answer = $this->negotiation()->answer(new Template('blog', ['n' => 1], 422, cache: Cache::private()), new Visit('/blog', 'application/json'));

        self::assertSame([422, '<blog>{"n":1}</blog>', false, null], [$answer->status, $answer->body, $answer->json, $answer->file]);
        self::assertSame([['blog', Negotiation::PAGE]], $this->rendered);
        self::assertSame([], $answer->cache->vary, 'no JSON allowed: nothing to vary on');
        self::assertFalse($answer->cache->shared);

        $this->negotiation()->answer(new Template('screen', alone: true), new Visit());
        self::assertSame(['screen', Negotiation::ALONE], $this->rendered[1], 'a page whole');
    }

    public function testATemplateAllowingJsonGivesItsDataToAClientPreferringIt(): void
    {
        $template = new Template('screen', ['title' => '<Le Fuji>', 'url' => '/a/b', 'part' => new Template('part')], json: true, alone: true);

        $json = $this->negotiation()->answer($template, new Visit('/screen', 'application/json'));
        self::assertTrue($json->json);
        self::assertSame('{"title":"\u003CLe Fuji\u003E","url":"\/a\/b","part":"\u003Cpart\u003E[]\u003C\/part\u003E"}', $json->body, 'as Symfony\'s JsonResponse writes it');
        self::assertSame(['Accept'], $json->cache->vary);

        $html = $this->negotiation()->answer($template, new Visit('/screen', 'text/html,application/json;q=0.9'));
        self::assertFalse($html->json);
        self::assertStringStartsWith('<screen>', $html->body);
        self::assertSame(['Accept'], $html->cache->vary, 'the HTML varies on it too: one address, two answers');
    }

    public function testTheFirstTypeThatNamesAFormatDecides(): void
    {
        self::assertTrue((new Visit(accept: 'application/json'))->prefersJson());
        self::assertTrue((new Visit(accept: '*/*, application/json'))->prefersJson(), 'a wildcard names no format');
        self::assertTrue((new Visit(accept: 'text/html;q=0.5, application/json'))->prefersJson(), 'by quality first');
        self::assertFalse((new Visit(accept: 'text/html, application/json'))->prefersJson());
        self::assertFalse((new Visit(accept: 'application/ld+json'))->prefersJson(), 'JSON-LD is another format');
        self::assertFalse((new Visit())->prefersJson());
        self::assertSame(['application/json', 'text/html'], (new Visit(accept: 'text/html;q=0.8, application/json'))->acceptable());
        self::assertTrue((new Visit(accept: 'application/json'))->asksJsonAlone());
        self::assertFalse((new Visit(accept: 'application/json, text/html'))->asksJsonAlone());
    }

    public function testJsonDataHaveTheirTemplatesRendered(): void
    {
        $answer = $this->negotiation()->answer(new Json([
            'hits' => [new Template('hit', ['id' => 1]), new Template('hit', ['id' => 2])],
            'html' => new Fragments(new Template('comment', ['id' => 3]), new Template('comment', ['id' => 4])),
            'facets' => ['tags' => ['counts' => ['a' => ['count' => 2, 'html' => new Template('facet')]]]],
            'none' => new Fragments(),
            'found' => 2,
        ], cache: Cache::public(3600, mustRevalidate: true)), new Visit());

        self::assertTrue($answer->json);
        $data = json_decode($answer->body, true);
        self::assertSame(['<hit>{"id":1}</hit>', '<hit>{"id":2}</hit>'], $data['hits']);
        self::assertSame('<comment>{"id":3}</comment><comment>{"id":4}</comment>', $data['html']);
        self::assertSame('<facet>[]</facet>', $data['facets']['tags']['counts']['a']['html']);
        self::assertSame(['', 2], [$data['none'], $data['found']]);
        self::assertSame([Negotiation::PART], array_values(array_unique(array_column($this->rendered, 1))), 'parts of an answer');
        self::assertSame([true, 3600, true], [$answer->cache->shared, $answer->cache->maxAge, $answer->cache->mustRevalidate]);

        $refused = $this->negotiation()->answer(new Json('Invalid token', 403), new Visit());
        self::assertSame([403, '"Invalid token"'], [$refused->status, $refused->body]);
    }

    public function testAFileIsAnsweredAsItIs(): void
    {
        $answer = $this->negotiation()->answer(new File(__FILE__, 'text/plain', Cache::public(86400)), new Visit());

        self::assertSame([__FILE__, ['Content-Type' => 'text/plain'], ''], [$answer->file, $answer->headers, $answer->body]);
        self::assertSame([], $this->negotiation()->answer(new File(__FILE__), new Visit())->headers, 'its type left to the host');
    }

    public function testARedirectSendsTheClientToItsAddress(): void
    {
        $answer = $this->negotiation()->answer(new Redirect('/japon/articles/le-fuji'), new Visit());

        self::assertSame([302, ['Location' => '/japon/articles/le-fuji'], '', false], [$answer->status, $answer->headers, $answer->body, $answer->json]);
        self::assertSame(301, $this->negotiation()->answer(new Redirect('/', 301), new Visit())->status);
        self::assertSame([], $this->rendered, 'nothing rendered');
    }

    public function testAShellIsOmnibridgesProtocol(): void
    {
        if (!ScreenResponderProtocol::available()) {
            self::markTestSkipped('omnibridge/react is not installed (nor beside the core).');
        }
        $shell = new Shell('Blog', ['locale' => 'fr_FR', 'title' => 'Japon'], 'shell', ['locale' => 'fr_FR'], '3f2a');

        $first = $this->negotiation()->answer($shell, new Visit('/react/japon/articles?page=2'));
        self::assertSame(200, $first->status);
        self::assertStringStartsWith('<shell>{"root":"<div id=\"app\" data-page=', $first->body);
        self::assertStringContainsString('&quot;url&quot;:&quot;/react/japon/articles?page=2&quot;', $first->body, 'the page\'s address is the visit\'s');
        self::assertSame(['shell', Negotiation::ALONE], $this->rendered[0]);

        $within = $this->negotiation()->answer($shell, new Visit('/react/japon/articles', '', 'true', '3f2a'));
        self::assertSame('{"component":"Blog","props":{"locale":"fr_FR","title":"Japon"},"url":"/react/japon/articles","version":"3f2a"}', $within->body);
        self::assertSame(['application/json', 'true', '3f2a'], [$within->headers['Content-Type'], $within->headers['X-Omnibridge'], $within->headers['X-Omnibridge-Version']]);

        $stale = $this->negotiation()->answer($shell, new Visit('/react/japon/articles', '', 'true', 'old'));
        self::assertSame([409, '/react/japon/articles'], [$stale->status, $stale->headers['X-Omnibridge-Location']]);
        self::assertCount(1, $this->rendered, 'no shell rendered for a visit within the front end');
    }

    public function testACacheAddsWhatTheAnswerSays(): void
    {
        $cache = Cache::public(60)->with(['Accept'])->with(['Accept', 'Cookie'], private: true, noStore: true);

        self::assertSame([false, 60, true, ['Accept', 'Cookie']], [$cache->shared, $cache->maxAge, $cache->noStore, $cache->vary]);
        self::assertInstanceOf(Answer::class, $this->negotiation()->answer(new Template('x'), new Visit()));
    }
}

/** omnibridge/react, installed or beside the core (the workspace), or mounted by the harness's checkouts overlay. */
final class ScreenResponderProtocol
{
    public static function available(): bool
    {
        if (class_exists(\Omnibridge\React\Protocol::class)) {
            return true;
        }
        foreach ([\dirname(__DIR__, 4).'/omnibridge/react/src', '/srv/omnibridge/react/src'] as $src) {
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
}
