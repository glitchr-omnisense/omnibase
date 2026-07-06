<?php

namespace Base\Database\Filter;

use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Trasheable;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TrashFilter extends SQLFilter
{
    /**
     * @param ClassMetaData $targetEntity
     * @param $targetTableAlias
     * @return string
     * @throws \Exception
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        $trasheableAttribute = AttributeReader::getInstance()->getClassAttributes($targetEntity->getName(), Trasheable::class);

        if (count($trasheableAttribute) < 1) {
            return "";
        }

        $fieldName = end($trasheableAttribute)->deletedAt;
        if ($targetEntity->hasField($fieldName)) {
            $date = date("Y-m-d H:00:00", time() + 3600);
            return $targetTableAlias . "." . $fieldName . " < '" . $date . "' OR " . $targetTableAlias . "." . $fieldName . " IS NULL";
        }

        return "";
    }
}
