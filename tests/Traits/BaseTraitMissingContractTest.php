<?php

namespace Tests\Base\Traits;

use Base\Service\ArrayParameterBag;
use Base\Service\Locator;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Traits\BaseCommonTrait;
use Base\Traits\BaseTrait;
use Omnibridge\Exception\MissingContractException;
use Omnibridge\Runtime;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * BaseTrait's accessors with no host and with one (plan "omnibase agnostic", step F2f): a service the
 * host does not answer throws Omnibridge\Exception\MissingContractException, where it used to answer
 * null and fail further on with a call on null; its find...() form answers null, for a caller that
 * does without; a service the host answers is handed out by both forms. The framework's accessors,
 * deprecated, keep answering null as before.
 *
 * The locator's statics and omnibridge's registry are process-global and every other test in the
 * run depends on them: both are snapshot, emptied for each test and restored verbatim.
 */
final class BaseTraitMissingContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $locator;

    /** @var array<string, mixed> */
    private array $registry;

    protected function setUp(): void
    {
        $class = new \ReflectionClass(Locator::class);
        $this->locator = $class->getStaticProperties();
        foreach ((new \ReflectionClass(BaseCommonTrait::class))->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
            $class->setStaticPropertyValue($property->getName(), null);
        }

        $this->registry = [];
        foreach (['container' => null, 'given' => [], 'resolved' => []] as $name => $empty) {
            $property = new \ReflectionProperty(Runtime::class, $name);
            $this->registry[$name] = $property->getValue();
            $property->setValue(null, $empty);
        }
    }

    protected function tearDown(): void
    {
        $class = new \ReflectionClass(Locator::class);
        foreach ($this->locator as $name => $value) {
            $class->setStaticPropertyValue($name, $value);
        }
        foreach ($this->registry as $name => $value) {
            (new \ReflectionProperty(Runtime::class, $name))->setValue(null, $value);
        }
    }

    public function testAServiceTheHostLacksIsSaidAloud(): void
    {
        $entity = new class {
            use BaseTrait;
        };

        foreach ([
            'the translator, by the locator' => static fn () => Locator::getTranslator(),
            'the translator, by an entity' => static fn () => $entity::getTranslator(),
            'the localizer' => static fn () => $entity::getLocalizer(),
            'the notifier' => static fn () => $entity::getNotifier(),
            'the trading market' => static fn () => $entity::getTrading(),
            'the setting bag' => static fn () => $entity::getSettingBag(),
            'the entity hydrator' => static fn () => $entity::getEntityHydrator(),
            'the obfuscator' => static fn () => $entity::getObfuscator(),
            'the icon provider' => static fn () => $entity::getIconProvider(),
            'the media service' => static fn () => $entity::getMediaService(),
            'the parameters' => static fn () => $entity::getParameterBag(),
            'a parameter' => static fn () => $entity::getParameterBag('base.user.active_delay'),
            'whether a class is an entity' => static fn () => $entity::isEntity(\stdClass::class),
        ] as $label => $ask) {
            try {
                $ask();
                self::fail("$label: answered with no host");
            } catch (MissingContractException $e) {
                self::assertStringContainsString('in the runtime', $e->getMessage(), $label);
            }
        }

        try {
            $entity::getTranslator();
        } catch (MissingContractException $e) {
            self::assertStringContainsString(TranslatorInterface::class, $e->getMessage(), 'the exception names the contract');
        }
    }

    public function testTheOptionalFormAnswersNull(): void
    {
        $entity = new class {
            use BaseTrait;
        };

        self::assertNull(Locator::findTranslator());
        self::assertNull($entity::findTranslator());
        self::assertNull($entity::findLocalizer());
        self::assertNull($entity::findNotifier());
        self::assertNull($entity::findTrading());
        self::assertNull($entity::findSettingBag());
        self::assertNull($entity::findEntityHydrator());
        self::assertNull($entity::findObfuscator());
        self::assertNull($entity::findIconProvider());
        self::assertNull($entity::findMediaService());
        self::assertNull($entity::findParameterBag());
    }

    public function testAServiceTheHostAnswersIsHandedOutByBothForms(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        Locator::setRuntime(new class(['translator' => $translator]) implements ContainerInterface {
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        });
        Runtime::set(ParameterBagInterface::class, new ArrayParameterBag(['base.user.active_delay' => 300]));

        $entity = new class {
            use BaseTrait;
        };
        self::assertSame($translator, $entity::getTranslator());
        self::assertSame($translator, $entity::findTranslator());
        self::assertSame($translator, Locator::getTranslator());
        self::assertSame(300, $entity::getParameterBag('base.user.active_delay'));
        self::assertNull($entity::getParameterBag('base.user.no_such_key'), 'a key the parameters lack is null, the parameters are there');
        self::assertInstanceOf(ArrayParameterBag::class, $entity::findParameterBag());
    }

    public function testTheDeprecatedFrameworkAccessorsStillAnswerNull(): void
    {
        $entity = new class {
            use BaseTrait;
        };

        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        try {
            self::assertNull($entity::getRouter());
            self::assertNull($entity::getTwig());
            self::assertNull($entity::getRequestStack());
            self::assertNull($entity::getTokenStorage());
            self::assertNull($entity::getEntityManager());
            self::assertNull($entity::getDoctrine());
            self::assertNull($entity::getService());
        } finally {
            restore_error_handler();
        }
    }
}
