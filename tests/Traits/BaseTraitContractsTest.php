<?php

namespace Tests\Base\Traits;

use Base\Service\Locator;
use Base\Traits\BaseTrait;
use Base\Traits\FrameworkAccessorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * BaseTrait names contracts only (plan "omnibase agnostic", step F2f). The guard:
 *
 * - src/Traits/BaseTrait.php names no class of Symfony, Doctrine, Twig, Laravel, nor of a bridge
 *   (Base\Bridge\, Omnibridge\Symfony\...) in its code - its use lines, its qualified names;
 *   comments and strings aside;
 * - each type its accessors take or answer is a contract: an interface of omnisense/omnibridge
 *   (Omnibridge\Contract\, Omnibridge\Persistence\) or Base\Contract\, or a class of the agnostic
 *   core (tests/Agnostic.php);
 * - each service comes in two forms: getX(), never null (MissingContractException when the host has
 *   none), and findX(), its optional form, null when the host has none;
 * - the framework's accessors it handed out before are Base\Traits\FrameworkAccessorsTrait's, each
 *   deprecated, and no class of src/ calls them through BaseTrait any more (the core's host code
 *   asks Base\Bridge\Symfony\FrameworkLocator).
 *
 * That a missing service throws is BaseTraitMissingContractTest's, and BareTest's without the
 * framework (tests/Contract/basetrait-seams-without-framework.php).
 */
final class BaseTraitContractsTest extends TestCase
{
    private const CONTRACTS = ['Omnibridge\\Contract\\', 'Omnibridge\\Persistence\\', 'Base\\Contract\\'];

    private const FRAMEWORKS = ['Symfony\\', 'Doctrine\\', 'Twig\\', 'Illuminate\\', 'Base\\Bridge\\', 'Omnibridge\\Symfony\\', 'Omnibridge\\Doctrine\\', 'Omnibridge\\Twig\\', 'Omnibridge\\Laravel\\', 'Omnibridge\\Eloquent\\'];

    public function testBaseTraitNamesNoFramework(): void
    {
        $file = self::src('Traits/BaseTrait.php');
        foreach (\PhpToken::tokenize((string) file_get_contents($file)) as $token) {
            if (!$token->is([\T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED])) {
                continue;
            }
            $name = ltrim($token->text, '\\');
            foreach (self::FRAMEWORKS as $framework) {
                self::assertStringStartsNotWith($framework, $name, "BaseTrait.php:{$token->line} names $name");
            }
        }
    }

    public function testItsAccessorsNameContractsOnly(): void
    {
        $agnostic = require \dirname(__DIR__).'/Agnostic.php';
        $checked = 0;
        foreach (self::ownMethods(BaseTrait::class) as $method) {
            $types = [$method->getReturnType()];
            foreach ($method->getParameters() as $parameter) {
                $types[] = $parameter->getType();
            }
            foreach ($types as $type) {
                foreach (self::names($type) as $name) {
                    $isContract = [] !== array_filter(self::CONTRACTS, static fn (string $prefix): bool => str_starts_with($name, $prefix));
                    self::assertTrue($isContract || \in_array($name, $agnostic, true), "BaseTrait::{$method->getName()}() names $name, neither a contract nor a class of the agnostic core");
                    ++$checked;
                }
            }
        }
        self::assertGreaterThan(10, $checked);
    }

    public function testEachServiceHasAThrowingAndAnOptionalForm(): void
    {
        $methods = [];
        foreach (self::ownMethods(BaseTrait::class) as $method) {
            $methods[$method->getName()] = $method;
        }

        $services = 0;
        foreach ($methods as $name => $method) {
            if (!str_starts_with($name, 'find')) {
                continue;
            }
            $getter = 'get'.substr($name, 4);
            self::assertArrayHasKey($getter, $methods, "$name() has no $getter()");
            ++$services;

            $optional = $method->getReturnType();
            self::assertInstanceOf(\ReflectionNamedType::class, $optional, "$name()'s answer");
            self::assertTrue($optional->allowsNull(), "$name() answers null when the host has none");
            if ('getParameterBag' === $getter) {
                continue; // the bag, or one of its values by a key: untyped, as before
            }
            $needed = $methods[$getter]->getReturnType();
            self::assertInstanceOf(\ReflectionNamedType::class, $needed, "$getter()'s answer");
            self::assertFalse($needed->allowsNull(), "$getter() never answers null: MissingContractException");
            self::assertSame($optional->getName(), $needed->getName(), "$name() and $getter() answer the same contract");
        }

        // Every service accessor has its optional form: a getX() typed with a class has a findX().
        foreach ($methods as $name => $method) {
            $type = $method->getReturnType();
            if (str_starts_with($name, 'get') && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                self::assertArrayHasKey('find'.substr($name, 3), $methods, "$name() has no optional form");
            }
        }
        self::assertGreaterThanOrEqual(11, $services);
    }

    public function testTheFrameworkAccessorsAreDeprecated(): void
    {
        $accessors = self::ownMethods(FrameworkAccessorsTrait::class, \ReflectionMethod::IS_PUBLIC);
        self::assertCount(12, $accessors, 'getService, getDoctrine, getObjectManager, getEntityManager, getRepository, getClassMetadataManipulator, getTokenStorage, getRequestStack, getFirewallMap, getTwig, getSlugger, getRouter');

        foreach ($accessors as $method) {
            self::assertStringContainsString('@deprecated', (string) $method->getDocComment(), $method->getName());
            self::assertTrue(method_exists(Locator::class, $method->getName()), 'BaseTrait still answers '.$method->getName().'()');

            $deprecations = [];
            set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
                $deprecations[] = $message;

                return true;
            }, \E_USER_DEPRECATED);
            try {
                $arguments = $method->getNumberOfRequiredParameters() > 0 ? [\stdClass::class] : [];
                Locator::{$method->getName()}(...$arguments);
            } catch (\Throwable) {
                // What the host answers does not matter here, only that the call is deprecated.
            } finally {
                restore_error_handler();
            }
            self::assertCount(1, $deprecations, $method->getName().'() is deprecated when called');
            self::assertStringContainsString('BaseTrait::'.$method->getName().'() is deprecated', $deprecations[0]);
        }
    }

    public function testNoClassOfTheCoreCallsThemThroughBaseTrait(): void
    {
        $deprecated = array_map(static fn (\ReflectionMethod $method): string => $method->getName(), self::ownMethods(FrameworkAccessorsTrait::class, \ReflectionMethod::IS_PUBLIC));
        $directory = self::src('');
        $calls = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ('php' !== $file->getExtension() || str_ends_with($file->getPathname(), '/Traits/FrameworkAccessorsTrait.php')) {
                continue;
            }
            $tokens = array_values(array_filter(\PhpToken::tokenize((string) file_get_contents($file->getPathname())), static fn (\PhpToken $token): bool => !$token->isIgnorable()));
            $class = self::declaredClass($tokens);
            foreach ($tokens as $i => $token) {
                if (!$token->is(\T_STRING) || !\in_array($token->text, $deprecated, true) || '(' !== ($tokens[$i + 1]->text ?? null)) {
                    continue;
                }
                $operator = $tokens[$i - 1]->text ?? '';
                $receiver = $tokens[$i - 2]->text ?? '';
                $where = substr($file->getPathname(), \strlen($directory)).":{$token->line} {$receiver}{$operator}{$token->text}()";
                if ('::' === $operator && \in_array(ltrim($receiver, '\\'), ['Locator', 'BaseService', 'Base\\Service\\Locator', 'Base\\Service\\BaseService'], true)) {
                    $calls[] = $where;
                } elseif ((('->' === $operator && '$this' === $receiver) || ('::' === $operator && \in_array($receiver, ['self', 'static', 'parent'], true))) && null !== $class && self::usesBaseTrait($class)) {
                    $calls[] = $where;
                }
            }
        }
        self::assertSame([], $calls, 'the core asks Base\Bridge\Symfony\FrameworkLocator, or a contract');
    }

    /** @return list<\ReflectionMethod> the methods the trait declares in its own file, not those of the traits it uses */
    private static function ownMethods(string $trait, ?int $filter = null): array
    {
        $reflection = new \ReflectionClass($trait);

        return array_values(array_filter($reflection->getMethods($filter), static fn (\ReflectionMethod $method): bool => $method->getFileName() === $reflection->getFileName()));
    }

    /** @return list<string> the classes a type names (self, static and the builtins aside) */
    private static function names(?\ReflectionType $type): array
    {
        if (null === $type) {
            return [];
        }
        if ($type instanceof \ReflectionNamedType) {
            return $type->isBuiltin() || \in_array($type->getName(), ['self', 'static'], true) ? [] : [$type->getName()];
        }

        return array_merge(...array_map(self::names(...), $type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType ? $type->getTypes() : []));
    }

    /** @param list<\PhpToken> $tokens */
    private static function declaredClass(array $tokens): ?string
    {
        $namespace = '';
        foreach ($tokens as $i => $token) {
            if ($token->is(\T_NAMESPACE)) {
                $namespace = $tokens[$i + 1]->text.'\\';
            }
            if ($token->is([\T_CLASS, \T_TRAIT]) && $tokens[$i + 1]->is(\T_STRING) && !(($tokens[$i - 1] ?? null)?->is(\T_DOUBLE_COLON))) {
                return $namespace.$tokens[$i + 1]->text;
            }
        }

        return null;
    }

    /** Whether the class, a parent of it or a trait of theirs uses BaseTrait. */
    private static function usesBaseTrait(string $class): bool
    {
        if (!class_exists($class) && !trait_exists($class)) {
            return false;
        }
        $traits = [];
        $pending = [$class, ...array_values(class_parents($class) ?: [])];
        while ($pending) {
            foreach (class_uses(array_shift($pending)) ?: [] as $trait) {
                if (!isset($traits[$trait])) {
                    $traits[$trait] = true;
                    $pending[] = $trait;
                }
            }
        }

        return isset($traits[BaseTrait::class]) || BaseTrait::class === $class;
    }

    private static function src(string $path): string
    {
        return \dirname(__DIR__, 2).'/src/'.$path;
    }
}
