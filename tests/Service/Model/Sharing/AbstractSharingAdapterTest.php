<?php

namespace Tests\Base\Service\Model\Sharing;

use PHPUnit\Framework\TestCase;
use Tests\Base\Fixtures\Sharing\FakeSharingAdapter;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * generate() is pure string/Twig plumbing, so a bare Twig Environment with an
 * ArrayLoader is enough — the loader is keyed with the adapter's real default
 * template name, no bundle wiring needed.
 */
class AbstractSharingAdapterTest extends TestCase
{
    private FakeSharingAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new FakeSharingAdapter(new Environment(new ArrayLoader([
            '@Base/sharing/default.html.twig' => '{{ sharing|raw }}',
            'custom.html.twig' => 'CUSTOM:{{ sharing|raw }}',
            'context.html.twig' => '{{ a }}|{{ b }}|{{ adapter.identifier }}',
        ])));
    }

    public function testGenerateSubstitutesAndUrlEncodesOptions(): void
    {
        $sharing = $this->adapter->generate([
            'a' => 'hello world',
            'url' => 'https://foo/bar?x=1',
        ]);

        // Values are urlencode()d (space => +), and the unfilled {b}
        // placeholder is stripped. Rendered through the default template.
        $this->assertSame(
            'https://share.example/create?a=hello+world&b=&url=https%3A%2F%2Ffoo%2Fbar%3Fx%3D1',
            $sharing
        );
    }

    public function testGenerateWithAnExplicitTemplate(): void
    {
        $sharing = $this->adapter->generate(['a' => 'x', 'b' => 'y', 'url' => 'u'], 'custom.html.twig');

        $this->assertSame('CUSTOM:https://share.example/create?a=x&b=y&url=u', $sharing);
    }

    /**
     * Current behavior: options go through array_filter() without a callback,
     * so empty strings — and falsy values like "0" — are dropped and their
     * placeholders stripped from the share URL.
     */
    public function testFalsyOptionsAreFilteredOut(): void
    {
        $sharing = $this->adapter->generate(['a' => '', 'b' => '0', 'url' => 'u']);

        $this->assertSame('https://share.example/create?a=&b=&url=u', $sharing);
    }

    public function testOptionsAndAdapterAreExposedToTheTemplate(): void
    {
        $sharing = $this->adapter->generate(['a' => 'left', 'b' => 'right', 'url' => 'u'], 'context.html.twig');

        $this->assertSame('left|right|fake', $sharing);
    }

    public function testDefaultTemplateName(): void
    {
        $this->assertSame('@Base/sharing/default.html.twig', $this->adapter->getTemplate());
    }
}
