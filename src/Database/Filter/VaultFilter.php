<?php

namespace Base\Database\Filter;

use Base\Database\Scope\VaultScope;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;

/**
 * Hides the rows of another environment's vault: Doctrine's compilation of
 * Base\Database\Scope\VaultScope (plan "omnibase agnostic", step F2e), the vault
 * field => [null, the environment] it gives written as SQL (an environment holding
 * a % compared with LIKE). Moves to omnibridge/doctrine at step F3.
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
        $scope = new VaultScope();
        if (!$scope->appliesTo($targetEntity->getName())) {
            return "";
        }

        if (!$this->environment) {
            throw new InvalidArgumentException("No environment defined in \"" . self::class . "\" while setting up " . $targetEntity->getName());
        }

        foreach ($scope->criteria($targetEntity->getName(), ["environment" => $this->environment]) as $vaultFieldName => $values) {
            if (!$targetEntity->hasField($vaultFieldName)) {
                return "";
            }
            // VaultScope gives the vault field => [null, the environment]: IS NULL, or equal.
            $environment = (string) end($values);
            $operator = str_contains($environment, "%") ? "LIKE" : "=";

            return $targetTableAlias . "." . $vaultFieldName . " IS NULL OR " . $targetTableAlias . "." . $vaultFieldName . " $operator '" . $environment . "'";
        }

        return "";
    }
}
