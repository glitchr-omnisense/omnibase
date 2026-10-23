<?php

namespace Tests\Base\Http;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\Http\Fixtures\PagesTestKernel;

/**
 * A page's language: its address (a route of one language), else the
 * visitor's choice or the language their last page was shown in (LANG),
 * else - on a first visit only - the browser's (Accept-Language), else the
 * site's. The pages are the test's own (Fixtures\PagesTestKernel):
 * /base-test/language and /base-test/langue one page at an address per
 * language, /base-test/welcome one address for both. The host speaks
 * English and French; its own language is either (the harness's English,
 * lesringostars' French), the browser asks for the other.
 *
 * The browser's language was read on every page from the USER/INFO cookie
 * the site's script writes after the first one: a visitor shown a French
 * page was moved to their browser's language from the second page on - and
 * a first visit was always in the site's language, whatever was asked.
 * Each page is asked of a kernel of its own, cookies carried by hand.
 */
class PageLanguageHttpTest extends KernelTestCase
{
    use HttpTestTrait;

    /** The cookie the site's script writes with the browser's settings (UserInfoTrait). */
    private const USER_INFO = 'USER/INFO';

    private const WELCOME = '/base-test/welcome';

    /** The page at an address per language. */
    private const ADDRESS = ['en' => '/base-test/language', 'fr' => '/base-test/langue'];

    /** What a browser of each language asks. */
    private const BROWSER = ['en' => 'en-US,en;q=0.9', 'fr' => 'fr-FR,fr;q=0.9,en;q=0.5'];

    /** @var array<string, string> */
    private array $cookies = [];

    /** The site's language, and the other of the two. */
    private string $site;
    private string $other;

    protected static function getKernelClass(): string
    {
        return PagesTestKernel::class;
    }

    protected function setUp(): void
    {
        $this->bootHost();
        $localizer = static::getContainer()->get(\Base\Service\LocalizerInterface::class);
        if (array_diff(['en', 'fr'], $localizer->getAvailableLocaleLangs())) {
            self::markTestSkipped('The host application does not speak English and French.');
        }
        $this->site = \in_array($localizer->getDefaultLocaleLang(), ['en', 'fr'], true) ? $localizer->getDefaultLocaleLang() : 'en';
        $this->other = 'en' === $this->site ? 'fr' : 'en';
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[self::USER_INFO]);
        parent::tearDown();
    }

    private function visit(string $path, ?string $browser = null, array $server = []): Response
    {
        self::ensureKernelShutdown();
        $this->bootHost();

        $request = Request::create($path, 'GET', [], $this->cookies, [], $server);
        $request->headers->remove('Accept-Language');
        if (null !== $browser) {
            $request->headers->set('Accept-Language', $browser);
        }
        $response = static::$kernel->handle($request);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $response;
    }

    /** @return array{string, string} the language the page was shown in, and where it links itself */
    private static function shown(Response $response): array
    {
        self::assertSame(200, $response->getStatusCode(), substr(strip_tags((string) $response->getContent()), 0, 600));
        self::assertSame(1, preg_match('/<a id="here" href="([^"]+)">[^<]*<\/a> \(([a-z]{2}_[A-Z]{2})\)/', (string) $response->getContent(), $found));

        return [substr($found[2], 0, 2), $found[1]];
    }

    /** @return array{string, string} a page in $lang, linking itself at its address in $lang */
    private static function in(string $lang): array
    {
        return [$lang, self::ADDRESS[$lang]];
    }

    public function testTheBrowsersLanguageWelcomesTheFirstPageAndThePagesAfterStayInIt(): void
    {
        self::assertSame(self::in($this->other), self::shown($this->visit(self::WELCOME, self::BROWSER[$this->other])), 'a first visit, in the browser\'s language - and its links in it');
        self::assertStringStartsWith($this->other, $this->cookies['LANG'] ?? '', 'remembered');

        // The site's script has written the browser's settings since; the browser asks for the site's language now.
        $_COOKIE[self::USER_INFO] = json_encode(['locale' => 'en' === $this->site ? 'en-US' : 'fr-FR']);
        self::assertSame(self::in($this->other), self::shown($this->visit(self::WELCOME, self::BROWSER[$this->site])), 'the second page stays in the language of the first');
        self::assertSame(self::in($this->other), self::shown($this->visit(self::WELCOME, self::BROWSER[$this->site])));
    }

    public function testWithoutAWordFromTheBrowserItIsTheSitesLanguage(): void
    {
        self::assertSame(self::in($this->site), self::shown($this->visit(self::WELCOME)));

        $unspoken = current(array_diff(['ko', 'fi', 'pl', 'tr', 'cs'], static::getContainer()->get(\Base\Service\LocalizerInterface::class)->getAvailableLocaleLangs()));
        self::assertSame(self::in($this->site), self::shown($this->visit(self::WELCOME, $unspoken.';q=0.9')), 'a language the site does not speak');
    }

    public function testTheAddressDecidesAndIsRemembered(): void
    {
        $this->cookies['LANG'] = 'fr-FR';
        self::assertSame(self::in('en'), self::shown($this->visit(self::ADDRESS['en'], 'fr')), 'the English address is English');
        self::assertSame(self::in('en'), self::shown($this->visit(self::WELCOME, 'fr')), 'and the next page too');
        self::assertSame(self::in('fr'), self::shown($this->visit(self::ADDRESS['fr'])));
        self::assertSame(self::in('fr'), self::shown($this->visit(self::WELCOME, 'en')));
    }

    public function testTheVisitorsChoiceIsKept(): void
    {
        self::assertSame(self::in('en'), self::shown($this->visit(self::WELCOME, 'en')));

        $switch = $this->visit('/locale/fr', 'en', ['HTTP_REFERER' => 'http://localhost'.self::WELCOME]);
        self::assertTrue($switch->isRedirect(self::WELCOME), (string) $switch->headers->get('Location'));
        self::assertStringStartsWith('fr', $this->cookies['LANG'] ?? '');
        self::assertSame(self::in('fr'), self::shown($this->visit(self::WELCOME, 'en')));

        // From a page with an address per language: to its address in the language chosen.
        $switch = $this->visit('/locale/en', 'fr', ['HTTP_REFERER' => 'http://localhost'.self::ADDRESS['fr']]);
        self::assertTrue($switch->isRedirect(self::ADDRESS['en']), (string) $switch->headers->get('Location'));
        self::assertSame(self::in('en'), self::shown($this->visit(self::WELCOME, 'fr')));
    }
}
