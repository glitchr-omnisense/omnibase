<?php

namespace Tests\Base\Service;

use Base\Service\Model\LinkableInterface;
use Base\Service\Model\Sharer\Adapter\FacebookAdapter;
use Base\Service\Model\Sharer\Adapter\TwitterAdapter;
use Base\Service\Sharer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SharerTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader([
            '@Base/sharer/default.html.twig' => '{{ sharer|raw }}',
        ]));
    }

    public function testAdaptersAreKeyedByClass(): void
    {
        $facebook = new FacebookAdapter($this->twig);

        $sharer = (new Sharer())->addAdapter($facebook);

        $this->assertSame([FacebookAdapter::class => $facebook], $sharer->getAdapters());
    }

    public function testGetAdapterByClassOrIdentifier(): void
    {
        $facebook = new FacebookAdapter($this->twig);
        $sharer = (new Sharer())->addAdapter($facebook);

        $this->assertSame($facebook, $sharer->getAdapter(FacebookAdapter::class));
        $this->assertSame($facebook, $sharer->getAdapter('facebook'));

        // Existing class that was never registered, and unknown identifier.
        $this->assertNull($sharer->getAdapter(TwitterAdapter::class));
        $this->assertNull($sharer->getAdapter('twitter'));
    }

    /**
     * The bug this locks in: removeAdapter() passed the adapter list to the
     * pure function array_values_remove() and discarded the return value,
     * so no adapter was ever actually removed.
     */
    public function testRemoveAdapter(): void
    {
        $facebook = new FacebookAdapter($this->twig);
        $twitter = new TwitterAdapter($this->twig);
        $sharer = (new Sharer())->addAdapter($facebook)->addAdapter($twitter);

        $sharer->removeAdapter($facebook);

        $this->assertNull($sharer->getAdapter(FacebookAdapter::class));
        $this->assertSame($twitter, $sharer->getAdapter(TwitterAdapter::class), 'other adapters must survive');
    }

    public function testShareWithAnUnknownAdapterReturnsAnEmptyString(): void
    {
        $this->assertSame('', (new Sharer())->share('facebook', 'https://foo'));
    }

    public function testShareWithAPlainStringUrl(): void
    {
        $sharer = (new Sharer())->addAdapter(new FacebookAdapter($this->twig));

        $this->assertSame(
            'https://www.facebook.com/sharer/sharer.php?quote=hi&u=https%3A%2F%2Ffoo%2Fbar',
            $sharer->share('facebook', 'https://foo/bar', ['quote' => 'hi'])
        );
    }

    public function testShareResolvesLinkableObjectsToTheirAbsoluteUrl(): void
    {
        $linkable = $this->createMock(LinkableInterface::class);
        $linkable->expects($this->once())
            ->method('__toLink')
            ->with([], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://absolute.example/page');

        $sharer = (new Sharer())->addAdapter(new FacebookAdapter($this->twig));

        $this->assertStringContainsString(
            urlencode('https://absolute.example/page'),
            $sharer->share('facebook', $linkable)
        );
    }
}
