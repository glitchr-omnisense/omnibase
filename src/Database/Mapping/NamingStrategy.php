<?php

namespace Base\Database\Mapping;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Table;
use Exception;
use ReflectionClass;

/**
 *
 */
class NamingStrategy implements \Doctrine\ORM\Mapping\NamingStrategy
{
    public const TABLE_NAME_SIZE = 64;
    public const TABLE_I18N_SUFFIX = "Intl";

    /**
     * {}
     */

    private array $uniqueTableName = [];

    /**
     * @param $className
     * @return string
     * @throws Exception
     */
    public function classToTableName($className): string
    {
        $className = !is_string($className) ? get_class($className) : $className;
        $className = class_exists($className)
            ? (new ReflectionClass($className))->getName()
            : $className;

        $tableName = array_search($className, $this->uniqueTableName);

        //
        // Search for a table name in class annotation
        if (class_exists($className)) {
            if (!$tableName) {
                $annotationReader = new AnnotationReader();
                $annotations = $annotationReader->getClassAnnotations(new ReflectionClass($className));
                while ($annotation = array_pop($annotations)) {
                    if ($annotation instanceof Table && !empty($annotation->name)) {
                        $tableName = $annotation->name;
                        break;
                    }
                }
            }
        }

        //
        // Determination of table name based on class information
        if (!$tableName) {
            $tableName = str_strip(strstr($className, "\\Entity\\"), "\\Entity\\");
            if (empty($tableName)) {
                $tableName = $className;
                if (strrpos($tableName, '\\') !== false) {
                    $tableName = lcfirst(substr($className, strrpos($className, '\\') + 1));
                }
            }

            $tableName = str_replace("\\", "", $tableName);
            $tableName = explode("_", camel2snake($tableName));
            $tableName = array_unique($tableName);

            $tableName = snake2camel(implode("_", $tableName));
            $tableName = lcfirst($tableName);
            $tableName = preg_replace('/' . self::TABLE_I18N_SUFFIX . '$/', self::TABLE_I18N_SUFFIX, $tableName);
        }

        //
        // Make sure there is no ambiguity or issue related to SQL server
        if (strlen($tableName) > self::TABLE_NAME_SIZE) {
            throw new Exception("Table name will be truncated for \"" . $className . "\"");
        }

        if (str_contains($className, "\\Entity\\") && array_key_exists($tableName, $this->uniqueTableName) && $className != $this->uniqueTableName[$tableName]) {
            throw new Exception("Ambiguous table name \"" . $tableName . "\" found between \"" . $this->uniqueTableName[$tableName] . "\" and \"" . $className . "\"");
        }

        $this->uniqueTableName[$tableName] = $className;

        return $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function propertyToColumnName($propertyName, $className = null): string
    {
        return lcfirst($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function embeddedFieldToColumnName($propertyName, $embeddedColumnName, $className = null, $embeddedClassName = null): string
    {
        return lcfirst($propertyName) . '_' . lcfirst($embeddedColumnName);
    }

    /**
     * {@inheritdoc}
     */
    public function referenceColumnName(): string
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    public function joinColumnName($propertyName, $className = null): string
    {
        return lcfirst($propertyName) . '_' . lcfirst($this->referenceColumnName());
    }

    /**
     * {@inheritdoc}
     */
    public function joinTableName($sourceEntity, $targetEntity, $propertyName = null): string
    {
        return lcfirst($this->classToTableName($sourceEntity)) . '_' .
            lcfirst($this->classToTableName($propertyName ?? $targetEntity));
    }

    /**
     * {@inheritdoc}
     */
    public function joinKeyColumnName($entityName, $referencedColumnName = null): string
    {
        return lcfirst($this->classToTableName($entityName)) . '_' .
            lcfirst(($referencedColumnName ?: $this->referenceColumnName()));
    }
}
