<?php

namespace Base\Database\Annotation\Extension;

use Base\Database\Entity\EntityExtensionInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

interface ExtensionOptionInterface extends EntityExtensionInterface
{
    public function supports(string $target, ?string $targetValue = null, mixed $object = null): bool;
    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void;    
}