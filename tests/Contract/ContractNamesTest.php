<?php

namespace Tests\Base\Contract;

use Base\Contract\Runtime;
use Omnibridge\Runtime as HostRuntime;
use PHPUnit\Framework\TestCase;

/**
 * The generic contracts of omnisense/omnibridge under their Base\ names (Base\Contract\Contracts): one type, two
 * names - a type declaration, an instanceof and a container alias on the Base\ name take the object the host built
 * under the Omnibridge\ one. No former name of the 4.x adapters (Base\Bridge\Symfony\*) is declared.
 */
final class ContractNamesTest extends TestCase
{
    private const REMOVED = [
        'Base\Bridge\Symfony\RouterUrls', 'Base\Bridge\Symfony\TranslatorTranslations', 'Base\Bridge\Symfony\KernelEnvironment',
        'Base\Bridge\Symfony\SecurityAccess', 'Base\Bridge\Symfony\SecuritySessions', 'Base\Bridge\Symfony\CsrfTokens',
        'Base\Bridge\Symfony\FileLinks',
    ];

    public function testTheFormerAdapterNamesAreGoneAndTheContractsAnswerTheirBaseNames(): void
    {
        foreach (self::REMOVED as $former) {
            self::assertFalse(class_exists($former) || interface_exists($former), $former.' is still declared');
        }
        foreach (\Base\Contract\Contracts::NAMES as $name => $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                continue;
            }
            self::assertTrue(class_exists($name) || interface_exists($name), $name.' is not declared');
            self::assertSame((new \ReflectionClass($class))->getName(), (new \ReflectionClass($name))->getName(), $name.' is another type than '.$class);
            self::assertSame($name, \Base\Contract\Contracts::name($class));
        }
        self::assertTrue(is_a(\Omnibridge\Contract\Urls::class, \Base\Contract\Urls::class, true));
        self::assertTrue(is_a(\Base\Contract\UnitOfWork::class, \Omnibridge\Persistence\UnitOfWork::class, true));
        self::assertSame('Base\\Contract\\Runtime', Runtime::class, 'the core\'s registry keeps its own class');
    }

    public function testOmnibasesRegistryAddsOnlyItsOwnContracts(): void
    {
        $own = array_map(fn (\ReflectionMethod $m) => $m->getName(), array_filter(
            (new \ReflectionClass(Runtime::class))->getMethods(\ReflectionMethod::IS_STATIC),
            fn (\ReflectionMethod $m) => Runtime::class === $m->getDeclaringClass()->getName(),
        ));
        sort($own);

        self::assertSame(HostRuntime::class, get_parent_class(Runtime::class));
        // notifier, projectDir and translator since step E1: what a Notification asks (BaseTrait's accessors before).
        self::assertSame(['mailer', 'media', 'notifier', 'nouns', 'parameters', 'pictures', 'projectDir', 'texts', 'translator'], $own);
    }
}
