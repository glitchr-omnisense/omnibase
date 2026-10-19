<?php

namespace Tests\Base\Contract;

use Base\Contract\Access;
use Base\Contract\Files;
use Base\Contract\Locales;
use Base\Contract\Runtime;
use Base\Contract\SystemClock;
use Base\Contract\Urls;
use Base\Exception\MissingContractException;
use Omnibridge\Runtime as HostRuntime;
use Omnibridge\Twig\Contract\Translations;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The registry of the Fondation's contracts (plan "omnibase agnostique", C4),
 * on its own: what C3e's getters will rely on. A contract asked and not
 * filled is an exception that names it (where the static locator returned
 * null in silence), a container's service is resolved once, a different
 * container forgets what the previous one gave, a service set by hand wins.
 */
final class RuntimeTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $snapshot;

    protected function setUp(): void
    {
        // The registry is process-wide and the kernel tests fill it: kept, emptied, given back
        // (its state is omnibridge's registry's, which omnibase's extends since A2).
        $this->snapshot = (new \ReflectionClass(HostRuntime::class))->getStaticProperties();
        Runtime::reset();
    }

    protected function tearDown(): void
    {
        $class = new \ReflectionClass(HostRuntime::class);
        foreach ($this->snapshot as $name => $value) {
            $class->setStaticPropertyValue($name, $value);
        }
    }

    public function testAContractNotFilledIsAnExceptionNamingIt(): void
    {
        self::assertFalse(Runtime::has(Urls::class));
        self::assertNull(Runtime::find(Urls::class));

        $this->expectException(MissingContractException::class);
        $this->expectExceptionMessage(Urls::class);
        Runtime::urls();
    }

    public function testEveryTypedAccessorThrowsWhenEmptyButTheClock(): void
    {
        foreach (['urls', 'translations', 'access', 'locales', 'files', 'events', 'mailer'] as $accessor) {
            try {
                Runtime::$accessor();
                self::fail($accessor.'() answered with nothing filled');
            } catch (MissingContractException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertInstanceOf(SystemClock::class, Runtime::clock());
        self::assertEqualsWithDelta(time(), Runtime::clock()->now()->getTimestamp(), 2);
    }

    public function testAServiceSetByHandIsAnsweredAndMustImplementTheContract(): void
    {
        $urls = $this->urls('/a');
        Runtime::set(Urls::class, $urls);

        self::assertTrue(Runtime::has(Urls::class));
        self::assertSame($urls, Runtime::urls());
        self::assertSame('/a/blog', Runtime::urls()->path('blog'));

        Runtime::set(Urls::class, null);
        self::assertFalse(Runtime::has(Urls::class));

        $this->expectException(MissingContractException::class);
        Runtime::set(Urls::class, new \stdClass());
    }

    public function testTheContainerIsAskedOnceByTheContractsName(): void
    {
        $container = $this->container([Urls::class => $this->urls('/c')]);
        Runtime::setContainer($container);

        self::assertTrue(Runtime::has(Urls::class));
        self::assertFalse(Runtime::has(Access::class));
        self::assertSame(Runtime::urls(), Runtime::urls());
        self::assertSame(1, $container->gets);
    }

    public function testAServiceOfTheWrongTypeInTheContainerIsRefused(): void
    {
        Runtime::setContainer($this->container([Urls::class => new \stdClass()]));

        $this->expectException(MissingContractException::class);
        $this->expectExceptionMessage('stdClass');
        Runtime::urls();
    }

    public function testADifferentContainerForgetsWhatThePreviousGaveAndKeepsWhatWasSet(): void
    {
        $access = $this->createMock(Access::class);
        Runtime::set(Access::class, $access);
        Runtime::setContainer($this->container([Urls::class => $this->urls('/old'), Access::class => $this->createMock(Access::class)]));
        self::assertSame('/old/x', Runtime::urls()->path('x'));

        $same = Runtime::getContainer();
        Runtime::setContainer($same);
        self::assertSame('/old/x', Runtime::urls()->path('x'), 'the same container: nothing forgotten');

        Runtime::setContainer($this->container([Urls::class => $this->urls('/new')]));
        self::assertSame('/new/x', Runtime::urls()->path('x'), 'still the previous kernel\'s');
        self::assertSame($access, Runtime::access(), 'set by hand: ahead of any container');
    }

    public function testTheClockIsTheContainersWhenItHasOne(): void
    {
        $frozen = new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-18 02:00:00', new \DateTimeZone('UTC'));
            }
        };
        self::assertInstanceOf(SystemClock::class, Runtime::clock());

        Runtime::setContainer($this->container([ClockInterface::class => $frozen]));
        self::assertSame($frozen, Runtime::clock(), 'the machine\'s clock was the empty registry\'s, not kept over a container\'s');
        self::assertSame('2026-10-18T02:00:00+00:00', Runtime::clock()->now()->format(\DATE_ATOM));
    }

    public function testEachAccessorAnswersItsContract(): void
    {
        $services = [
            Urls::class => $this->urls(''),
            Translations::class => $this->createMock(Translations::class),
            Access::class => $this->createMock(Access::class),
            Locales::class => $this->createMock(Locales::class),
            Files::class => $this->createMock(Files::class),
            EventDispatcherInterface::class => $this->createMock(EventDispatcherInterface::class),
            MailerInterface::class => $this->createMock(MailerInterface::class),
        ];
        Runtime::setContainer($this->container($services));

        self::assertSame($services[Urls::class], Runtime::urls());
        self::assertSame($services[Translations::class], Runtime::translations());
        self::assertSame($services[Access::class], Runtime::access());
        self::assertSame($services[Locales::class], Runtime::locales());
        self::assertSame($services[Files::class], Runtime::files());
        self::assertSame($services[EventDispatcherInterface::class], Runtime::events());
        self::assertSame($services[MailerInterface::class], Runtime::mailer());
    }

    public function testResetForgetsEverything(): void
    {
        Runtime::set(Urls::class, $this->urls(''));
        Runtime::setContainer($this->container([]));
        Runtime::reset();

        self::assertNull(Runtime::getContainer());
        self::assertFalse(Runtime::has(Urls::class));
    }

    private function urls(string $prefix): Urls
    {
        return new class($prefix) implements Urls {
            public function __construct(private string $prefix)
            {
            }

            public function path(string $name, array $parameters = []): string
            {
                return $this->prefix.'/'.$name;
            }

            public function url(string $name, array $parameters = []): string
            {
                return 'https://example.test'.$this->path($name, $parameters);
            }

            public function asset(string $path): string
            {
                return '/'.$path;
            }
        };
    }

    /** @param array<string, object> $services */
    private function container(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            public int $gets = 0;

            /** @param array<string, object> $services */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                ++$this->gets;

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}
