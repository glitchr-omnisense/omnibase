<?php

namespace Tests\Base\Contract;

use Base\Contract\Runtime;
use Base\DependencyInjection\Compiler\Pass\ContractsPass;
use Omnibridge\Runtime as HostRuntime;
use PHPUnit\Framework\TestCase;

/**
 * The host's contracts moved to omniscient/omnibridge (plan "omnibase
 * agnostique", A2) and the Symfony adapters to omnibridge/symfony (A1) kept
 * their former names as aliases until omnibase 4's final pass, which removed
 * them once no site and no bundle named them: none of those names is declared
 * any more, and omnibase's registry is omnibridge's with its own accessors.
 */
final class FormerNamesTest extends TestCase
{
    /** The names removed at omnibase 4's final pass. */
    private const REMOVED = [
        'Base\Contract\Urls', 'Base\Contract\Translations', 'Base\Contract\Locales', 'Base\Contract\Access',
        'Base\Contract\Sessions', 'Base\Contract\Tokens', 'Base\Contract\Files', 'Base\Contract\Routes',
        'Base\Contract\Environment', 'Base\Contract\SystemClock', 'Base\Exception\MissingContractException',
        'Base\Bridge\Symfony\RouterUrls', 'Base\Bridge\Symfony\TranslatorTranslations', 'Base\Bridge\Symfony\KernelEnvironment',
        'Base\Bridge\Symfony\SecurityAccess', 'Base\Bridge\Symfony\SecuritySessions', 'Base\Bridge\Symfony\CsrfTokens',
        'Base\Bridge\Symfony\FileLinks',
    ];

    public function testTheFormerNamesAreGone(): void
    {
        foreach (self::REMOVED as $former) {
            self::assertFalse(class_exists($former) || interface_exists($former), $former.' is still declared');
        }
        self::assertSame([], ContractsPass::FORMER);
        foreach ([\Omnibridge\Contract\Urls::class, \Omnibridge\Contract\Translations::class, \Omnibridge\Contract\Access::class] as $contract) {
            self::assertSame([], HostRuntime::formerNames($contract), $contract);
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
