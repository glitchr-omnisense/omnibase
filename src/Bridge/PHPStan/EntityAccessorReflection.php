<?php

namespace Base\Bridge\PHPStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\TrivialParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

/**
 * One accessor of EntityAccessorsExtension: public, with the signature of the
 * method it ends in (an alias: AliasTrait hands the arguments on), any
 * arguments and that method's return type when $anyArguments (a translation's:
 * TranslatableTrait reads a property and leaves them), any arguments and mixed
 * without one.
 */
final class EntityAccessorReflection implements MethodReflection
{
    public function __construct(
        private readonly ClassReflection $declaringClass,
        private readonly string $name,
        private readonly ?MethodReflection $target,
        private readonly bool $anyArguments = false,
    ) {
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    public function getVariants(): array
    {
        if (null === $this->target) {
            return [new TrivialParametersAcceptor()];
        }
        if (!$this->anyArguments) {
            return $this->target->getVariants();
        }

        return array_map(static fn ($variant) => new FunctionVariant(TemplateTypeMap::createEmpty(), null, [], true, $variant->getReturnType()), $this->target->getVariants());
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): ?Type
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createMaybe();
    }
}
