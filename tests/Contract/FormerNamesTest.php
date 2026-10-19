<?php

namespace Tests\Base\Contract;

use Base\Contract\Runtime;
use Omnibridge\Contract;
use Omnibridge\Runtime as HostRuntime;
use PHPUnit\Framework\TestCase;

/**
 * The host's contracts moved to omniscient/omnibridge (plan "omnibase
 * agnostique", A2) and kept their former names: each former name is the moved
 * class itself (class_alias, not a subclass - master plan §5, invariant 8),
 * declared with Composer's autoloader (src/Resources/Aliases.php), never on
 * demand, so a type or an instanceof naming it holds before anything asks for
 * it; one service in the registry under either name; omnibase's registry is
 * omnibridge's, with its own accessors. The same for the Symfony adapters
 * moved to omnibridge/symfony (A1), where it is installed.
 */
final class FormerNamesTest extends TestCase
{
    /** @var array<string, class-string> */
    private const FORMER = [
        'Base\Contract\Urls' => Contract\Urls::class,
        'Base\Contract\Translations' => Contract\Translations::class,
        'Base\Contract\Locales' => Contract\Locales::class,
        'Base\Contract\Access' => Contract\Access::class,
        'Base\Contract\Sessions' => Contract\Sessions::class,
        'Base\Contract\Tokens' => Contract\Tokens::class,
        'Base\Contract\Files' => Contract\Files::class,
        'Base\Contract\Routes' => Contract\Routes::class,
        'Base\Contract\Environment' => Contract\Environment::class,
        'Base\Contract\SystemClock' => \Omnibridge\SystemClock::class,
        'Base\Exception\MissingContractException' => \Omnibridge\Exception\MissingContractException::class,
        'Omnibridge\Twig\Contract\Urls' => Contract\Urls::class,
        'Omnibridge\Twig\Contract\Translations' => Contract\Translations::class,
    ];

    /**
     * The adapters moved to omnibridge/symfony (A1), aliased where it is installed.
     *
     * @var array<string, class-string>
     */
    private const FORMER_ADAPTERS = [
        'Base\Bridge\Symfony\RouterUrls' => \Omnibridge\Symfony\RouterUrls::class,
        'Base\Bridge\Symfony\TranslatorTranslations' => \Omnibridge\Symfony\TranslatorTranslations::class,
        'Base\Bridge\Symfony\KernelEnvironment' => \Omnibridge\Symfony\Contract\KernelEnvironment::class,
        'Base\Bridge\Symfony\SecurityAccess' => \Omnibridge\Symfony\Contract\SecurityAccess::class,
        'Base\Bridge\Symfony\SecuritySessions' => \Omnibridge\Symfony\Contract\SecuritySessions::class,
        'Base\Bridge\Symfony\CsrfTokens' => \Omnibridge\Symfony\Contract\CsrfTokens::class,
        'Base\Bridge\Symfony\FileLinks' => \Omnibridge\Symfony\FileLinks::class,
    ];

    /** @var array<string, mixed> */
    private array $snapshot;

    protected function setUp(): void
    {
        $this->snapshot = (new \ReflectionClass(HostRuntime::class))->getStaticProperties();
        HostRuntime::reset();
    }

    protected function tearDown(): void
    {
        $class = new \ReflectionClass(HostRuntime::class);
        foreach ($this->snapshot as $name => $value) {
            $class->setStaticPropertyValue($name, $value);
        }
    }

    public function testEachFormerNameIsTheMovedClassDeclaredWithTheAutoloader(): void
    {
        foreach (self::FORMER as $former => $class) {
            self::assertTrue(interface_exists($former, false) || class_exists($former, false), $former.' declared without autoloading');
            $reflection = new \ReflectionClass($former);
            self::assertSame($class, $reflection->getName(), $former.' is an alias, not a subclass');
            self::assertSame((new \ReflectionClass($class))->getFileName(), $reflection->getFileName());
        }
    }

    public function testTheSymfonyAdaptersMovedToOmnibridgeKeepTheirFormerNames(): void
    {
        if (!class_exists(\Omnibridge\Symfony\RouterUrls::class)) {
            self::markTestSkipped('omnibridge/symfony is not installed: neither name exists.');
        }

        foreach (self::FORMER_ADAPTERS as $former => $class) {
            self::assertTrue(class_exists($former, false), $former.' declared without autoloading');
            $reflection = new \ReflectionClass($former);
            self::assertSame($class, $reflection->getName(), $former.' is an alias, not a subclass');
            self::assertSame((new \ReflectionClass($class))->getFileName(), $reflection->getFileName());
        }
        self::assertSame(\Omnibridge\Symfony\FileLinks::DEFAULT_FORMAT, \Base\Bridge\Symfony\FileLinks::DEFAULT_FORMAT);
    }

    public function testAnObjectMadeForAFormerNameIsTheContractAndTheRegistryHoldsItUnderBoth(): void
    {
        $locales = new class implements \Base\Contract\Locales {
            public function getLocale(): string
            {
                return 'fr-FR';
            }

            public function getDefaultLocale(): string
            {
                return 'fr-FR';
            }

            public function getAvailableLocales(): array
            {
                return ['fr-FR', 'en-GB'];
            }
        };
        self::assertInstanceOf(Contract\Locales::class, $locales);

        Runtime::set(\Base\Contract\Locales::class, $locales);
        self::assertSame($locales, Runtime::locales());
        self::assertSame($locales, HostRuntime::locales(), 'omnibase\'s registry is omnibridge\'s');
        self::assertSame($locales, Runtime::get(Contract\Locales::class));
        self::assertTrue(HostRuntime::has(\Base\Contract\Locales::class));
    }

    public function testAMissingContractIsTheSameExceptionUnderBothNames(): void
    {
        try {
            Runtime::access();
            self::fail('no Access in an empty registry');
        } catch (\Base\Exception\MissingContractException $e) {
            self::assertInstanceOf(\Omnibridge\Exception\MissingContractException::class, $e);
        }
    }

    public function testOmnibasesRegistryAddsOnlyItsOwnContracts(): void
    {
        $own = array_map(fn (\ReflectionMethod $m) => $m->getName(), array_filter(
            (new \ReflectionClass(Runtime::class))->getMethods(\ReflectionMethod::IS_STATIC),
            fn (\ReflectionMethod $m) => Runtime::class === $m->getDeclaringClass()->getName(),
        ));
        sort($own);

        self::assertSame(HostRuntime::class, get_parent_class(Runtime::class));
        self::assertSame(['mailer', 'media', 'nouns', 'parameters', 'pictures', 'texts'], $own);
    }
}
