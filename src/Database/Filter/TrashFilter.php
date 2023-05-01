<?php

namespace Base\Database\Filter;

use Base\Annotations\AnnotationReader;
use Base\Database\Annotation\Trasheable;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TrashFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        $trasheableAnnotation = AnnotationReader::getInstance()->getClassAnnotations($targetEntity->getName(), Trasheable::class);

        if (count($trasheableAnnotation) < 1) {
            return "";
        }

        $fieldName = end($trasheableAnnotation)->deletedAt;
        if ($targetEntity->hasField($fieldName)) {
            $date = date("Y-m-d H:00:00", time() + 3600);
            return $targetTableAlias . "." . $fieldName . " < '" . $date . "' OR " . $targetTableAlias . "." . $fieldName . " IS NULL";
        }

        return "";
    }
}
