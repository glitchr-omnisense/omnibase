<?php

namespace Tests\Base\Service\Model\Sharing\Adapter;

use Base\Service\Model\IconizeInterface;
use Base\Service\Model\Sharing\Adapter\FacebookAdapter;
use Base\Service\Model\Sharing\Adapter\GooglePlusAdapter;
use Base\Service\Model\Sharing\Adapter\LinkedInAdapter;
use Base\Service\Model\Sharing\Adapter\PinterestAdapter;
use Base\Service\Model\Sharing\Adapter\TumblrAdapter;
use Base\Service\Model\Sharing\Adapter\TwitterAdapter;
use Base\Service\Model\Sharing\SharingAdapterInterface;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Identifier/URL contract of every concrete adapter. Plain loops instead of
 * data providers: the suite must run under both PHPUnit 9.6 (host app's
 * phpunit-bridge) and PHPUnit 10 (standalone CI), which disagree on
 * provider metadata syntax.
 */
class AdaptersTest extends TestCase
{
    private const EXPECTED = [
        FacebookAdapter::class => 'facebook',
        TwitterAdapter::class => 'twitter',
        LinkedInAdapter::class => 'linkedin',
        GooglePlusAdapter::class => 'google+',
        TumblrAdapter::class => 'tumblr',
        PinterestAdapter::class => 'pinterest',
    ];

    private function makeAdapter(string $class): SharingAdapterInterface
    {
        return new $class(new Environment(new ArrayLoader([])));
    }

    public function testIdentifiersAreUniqueAndStable(): void
    {
        $seen = [];
        foreach (self::EXPECTED as $class => $identifier) {
            $adapter = $this->makeAdapter($class);

            $this->assertSame($identifier, $adapter->getIdentifier(), $class);
            $this->assertNotContains($adapter->getIdentifier(), $seen, "$class reuses an identifier");
            $seen[] = $adapter->getIdentifier();
        }
    }

    public function testEveryShareUrlIsHttpsAndCarriesTheUrlPlaceholder(): void
    {
        foreach (array_keys(self::EXPECTED) as $class) {
            $url = $this->makeAdapter($class)->getUrl();

            $this->assertStringStartsWith('https://', $url, $class);
            $this->assertStringContainsString('{url}', $url, $class);
        }
    }

    public function testEveryAdapterExposesStaticIconsAndUsesTheSharedTemplate(): void
    {
        foreach (array_keys(self::EXPECTED) as $class) {
            $adapter = $this->makeAdapter($class);

            $this->assertInstanceOf(IconizeInterface::class, $adapter, $class);
            $this->assertNotEmpty($class::__iconizeStatic(), $class);
            $this->assertNull($adapter->__iconize(), $class);
            $this->assertSame('@Base/sharer/default.html.twig', $adapter->getTemplate(), $class);
        }
    }
}
