<?php

namespace Tests\Base\Traits;

use Base\Service\Locator;
use Base\Traits\BaseTrait;
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
 * - the framework's accessors it handed out before (5.x's FrameworkAccessorsTrait) are gone since
 *   6.0 (the core's host code asks Omnibridge\Symfony\Omnibase\FrameworkLocator).
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

    /** The framework's accessors BaseTrait handed out before (5.x's FrameworkAccessorsTrait) are gone since 6.0. */
    public function testTheFrameworkAccessorsAreGone(): void
    {
        self::assertFalse(trait_exists('Base\\Traits\\FrameworkAccessorsTrait'));
        foreach (['getService', 'getDoctrine', 'getObjectManager', 'getEntityManager', 'getRepository', 'getClassMetadataManipulator', 'getTokenStorage', 'getRequestStack', 'getFirewallMap', 'getTwig', 'getSlugger', 'getRouter'] as $accessor) {
            self::assertFalse(method_exists(Locator::class, $accessor), 'BaseTrait no longer answers '.$accessor.'()');
        }
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

    private static function src(string $path): string
    {
        return \dirname(__DIR__, 2).'/src/'.$path;
    }
}
