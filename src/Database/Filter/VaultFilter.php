<?php

namespace Base\Database\Filter;

use Base\Annotations\AnnotationReader;
use Base\Database\Annotation\Vault;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;

class VaultFilter extends SQLFilter
{
    protected $environment;
    public function getEnvironment() { return $this->environment; }
    public function setEnvironment(string $environment)
    {
        $this->environment = $environment;
        return $this;
    }

    public function addFilterConstraint(ClassMetadata $targetEntity, $alias): string
    {
        if($this->environment)
            throw new InvalidArgumentException("No environment defined in ".self::class);

        $vaultAnnotation = AnnotationReader::getAnnotationReader()->getClassAnnotations($targetEntity->getName(), Vault::class);
        if(count($vaultAnnotation) < 1) return "";

        $vaultFieldName = end($vaultAnnotation)->vault;
        if ($targetEntity->hasField($vaultFieldName))
            return $vaultFieldName." IS NULL OR ". $vaultFieldName." LIKE '".$this->environment."'";

        return "";
    }
}