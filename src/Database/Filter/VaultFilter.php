<?php

namespace Base\Database\Filter;

use Base\Annotations\AnnotationReader;
use Base\Database\Annotation\Vault;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;

/**
 *
 */
class VaultFilter extends SQLFilter
{
    protected $environment;

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @param string $environment
     * @return $this
     */
    /**
     * @param string $environment
     * @return $this
     */
    public function setEnvironment(string $environment)
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * @param ClassMetaData $targetEntity
     * @param $targetTableAlias
     * @return string
     * @throws \Exception
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        $vaultAnnotation = AnnotationReader::getInstance()->getClassAnnotations($targetEntity->getName(), Vault::class);
        if (count($vaultAnnotation) < 1) {
            return "";
        }

        if (!$this->environment) {
            throw new InvalidArgumentException("No environment defined in \"" . self::class . "\" while setting up " . $targetEntity->getName());
        }

        $vaultFieldName = end($vaultAnnotation)->vault;
        $operator = str_contains($this->environment, "%") ? "LIKE" : "=";
        if ($targetEntity->hasField($vaultFieldName)) {
            return $targetTableAlias . "." . $vaultFieldName . " IS NULL OR " . $targetTableAlias . "." . $vaultFieldName . " $operator '" . $this->environment . "'";
        }

        return "";
    }
}
