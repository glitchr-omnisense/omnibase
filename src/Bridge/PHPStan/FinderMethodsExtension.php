<?php

namespace Base\Bridge\PHPStan;

use Base\Database\Repository\ServiceEntityRepository;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

/**
 * The finder language of omnibase's repositories for PHPStan: a repository
 * extending Base\Database\Repository\ServiceEntityRepository answers, through
 * __call() and ServiceEntityParser, every method its name describes -
 * findOneBySlug(), cacheByParentAndState(), countByDomainAndUpdatedAtYoungerThan(),
 * distinctCountBy...(), lengthOf...() - which no declaration can list. Each is
 * known to PHPStan as a public method taking any arguments and returning mixed,
 * as ServiceEntityRepository::__call() declares; any other unknown name is still
 * an undefined method.
 *
 * Registered by extension.neon at the core's root, which the core's phpstan.neon
 * includes and an application's may (vendor/omnisense/omnibase/extension.neon).
 * Loaded by PHPStan alone, never at run time.
 */
final class FinderMethodsExtension implements MethodsClassReflectionExtension
{
    /** The requests ServiceEntityParser::__parse() recognises (find, cache, count, distinctCount, lengthOf), then the rest of the name. */
    public const PATTERN = '/^(?:find|cache|count|distinctCount|lengthOf)[A-Z]/';

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (!$classReflection->is(ServiceEntityRepository::class) && !$classReflection->isSubclassOf(ServiceEntityRepository::class)) {
            return false;
        }

        return 1 === preg_match(self::PATTERN, $methodName);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new FinderMethodReflection($classReflection, $methodName);
    }
}
