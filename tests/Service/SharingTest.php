<?php

namespace Tests\Base\Service;

use Base\Service\Model\LinkableInterface;
use Base\Service\Model\Sharing\Adapter\FacebookAdapter;
use Base\Service\Model\Sharing\Adapter\TwitterAdapter;
use Base\Service\Sharing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SharingTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader([
            '@Base/sharing/default.html.twig' => '{{ sharing|raw }}',
        ]));
    }

    public function testAdaptersAreKeyedByClass(): void
    {
        $facebook = new FacebookAdapter($this->twig);

        $sharing = (new Sharing())->addAdapter($facebook);

        $this->assertSame([FacebookAdapter::class => $facebook], $sharing->getAdapters());
    }

    public function testGetAdapterByClassOrIdentifier(): void
    {
        $facebook = new FacebookAdapter($this->twig);
        $sharing = (new Sharing())->addAdapter($facebook);

        $this->assertSame($facebook, $sharing->getAdapter(FacebookAdapter::class));
        $this->assertSame($facebook, $sharing->getAdapter('facebook'));

        // Existing class that was never registered, and unknown identifier.
        $this->assertNull($sharing->getAdapter(TwitterAdapter::class));
        $this->assertNull($sharing->getAdapter('twitter'));
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
        $sharing = (new Sharing())->addAdapter($facebook)->addAdapter($twitter);

        $sharing->removeAdapter($facebook);

        $this->assertNull($sharing->getAdapter(FacebookAdapter::class));
        $this->assertSame($twitter, $sharing->getAdapter(TwitterAdapter::class), 'other adapters must survive');
    }

    public function testGenerateWithAnUnknownAdapterReturnsAnEmptyString(): void
    {
        $this->assertSame('', (new Sharing())->generate('facebook', 'https://foo'));
    }

    public function testGenerateWithAPlainStringUrl(): void
    {
        $sharing = (new Sharing())->addAdapter(new FacebookAdapter($this->twig));

        $this->assertSame(
            'https://www.facebook.com/sharer/sharer.php?quote=hi&u=https%3A%2F%2Ffoo%2Fbar',
            $sharing->generate('facebook', 'https://foo/bar', ['quote' => 'hi'])
        );
    }

    public function testGenerateResolvesLinkableObjectsToTheirAbsoluteUrl(): void
    {
        $linkable = $this->createMock(LinkableInterface::class);
        $linkable->expects($this->once())
            ->method('__toLink')
            ->with([], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://absolute.example/page');

        $sharing = (new Sharing())->addAdapter(new FacebookAdapter($this->twig));

        $this->assertStringContainsString(
            urlencode('https://absolute.example/page'),
            $sharing->generate('facebook', $linkable)
        );
    }
}
