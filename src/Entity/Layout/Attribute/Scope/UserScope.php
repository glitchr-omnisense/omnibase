<?php

namespace Base\Entity\Layout\Attribute\Scope;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\AbstractAdapter;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Attribute\Scope\UserScopeRepository;
use Base\Entity\User;
use Base\Field\Type\SelectType;

/**
 * @ORM\Entity(repositoryClass=UserScopeRepository::class)
 * @DiscriminatorEntry( value = "scope_user" )
 */

class UserScope extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return User::__iconizeStatic(); }

    public static function getType(): string { return SelectType::class; }
    public function getOptions(): array { return []; }

    public function resolve(mixed $value): mixed { return $value; }
}
