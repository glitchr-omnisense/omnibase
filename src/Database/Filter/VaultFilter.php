<?php

namespace Base\Database\Filter;

use Base\Annotations\AnnotationReader;
use Base\Database\Annotation\Vault;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;

class VaultFilter extends SQLFilter
{
    use BaseTrait;

    public function addFilterConstraint(ClassMetadata $targetEntity, $alias): string
    {
        $vaultAnnotation = AnnotationReader::getAnnotationReader()->getClassAnnotations($targetEntity->getName(), Vault::class);
        if(count($vaultAnnotation) < 1) return "";

        $vaultFieldName = end($vaultAnnotation)->vault;
        if ($targetEntity->hasField($vaultFieldName))
            return $vaultFieldName." IS NULL OR ". $vaultFieldName." LIKE '".$this->getEnvironment()."'";

        return "";
    }
}