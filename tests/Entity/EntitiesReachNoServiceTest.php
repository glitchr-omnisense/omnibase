<?php

namespace Tests\Base\Entity;

use Base\Contract\Link;
use Base\Contract\Runtime;
use Base\Entity\Layout\Widget\Attachment;
use Base\Entity\Layout\Widget\Page;
use Base\Entity\User;
use Base\Service\Links;
use Omnibridge\Contract\Urls;
use PHPUnit\Framework\TestCase;

/**
 * Step E1 of the plan "omnibase agnostic": what the entities asked of a service they ask the contracts.
 * A widget's __toLink() through Base\Service\Links on the Urls contract (an absolute path by default,
 * as Symfony's UrlGeneratorInterface::ABSOLUTE_PATH was); a user's identifier read as
 * symfony/property-access read it - its getter -, without it.
 */
final class EntitiesReachNoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Runtime::set(Urls::class, null);
    }

    public function testLinksMakeTheAddressesOnUrls(): void
    {
        $urls = new class implements Urls {
            public function path(string $name, array $parameters = []): string
            {
                return '/'.$name.'?'.http_build_query($parameters);
            }

            public function url(string $name, array $parameters = []): string
            {
                return 'https://example.org'.$this->path($name, $parameters);
            }

            public function asset(string $path): string
            {
                return '/'.$path;
            }
        };
        $links = new Links($urls);
        self::assertSame('/widget_page?slug=a', $links->to('widget_page', ['slug' => 'a']));
        self::assertSame('https://example.org/widget_page?slug=a', $links->to('widget_page', ['slug' => 'a'], Link::ABSOLUTE_URL));
        try {
            $links->to('widget_page', [], Link::RELATIVE_PATH);
            self::fail('a relative path is refused where Urls has no generate()');
        } catch (\InvalidArgumentException) {
        }

        Runtime::set(Urls::class, $urls);
        $page = (new \ReflectionClass(Page::class))->newInstanceWithoutConstructor()->setSlug('a');
        self::assertSame('/widget_page?slug=a', $page->__toLink());
        self::assertSame('https://example.org/widget_attachment?slug=b', (new \ReflectionClass(Attachment::class))->newInstanceWithoutConstructor()->setSlug('b')->__toLink([], Link::ABSOLUTE_URL));
        self::assertSame(1, (new \ReflectionMethod(Page::class, '__toLink'))->getParameters()[1]->getDefaultValue());
    }

    public function testTheUserIdentifierIsReadByItsGetter(): void
    {
        $user = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(User::class, 'email'))->setValue($user, 'someone@example.org');
        self::assertSame('someone@example.org', $user->getUserIdentifier());

        $nobody = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(User::class, 'email'))->setValue($nobody, null);
        $this->expectExceptionMessage('User identifier is NULL');
        $nobody->getUserIdentifier();
    }
}
