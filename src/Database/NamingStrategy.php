<?php

namespace Base\Database;

use Base\Service\BaseService;

/**
 * Custom Naming Strategy
 *
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 */
class NamingStrategy implements \Doctrine\ORM\Mapping\NamingStrategy
{
    public static function camelToSnakeCase($input)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
    public static function snakeToCamelCase($input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }

    /**
     * {@inheritdoc}
     */
    public function classToTableName($classNameWithNamespace)
    {
        /*
         * Another alternative: to keep namespace structure
         */

        //$tableName = ltrim(strstr($classNameWithNamespace, "\\Entity\\"), "\\Entity\\");
        // if(empty($tableName)) {

        //     $tableName = $classNameWithNamespace;
        //     if(strrpos($tableName, '\\') !== false)
        //         $tableName = lcfirst(substr($classNameWithNamespace, strrpos($classNameWithNamespace, '\\') + 1));
        // }

        // $tableName = self::camelToSnakeCase($tableName);
        // $tableName = str_replace("\\", "", $tableName);
        //return $tableName;

        if (strpos($classNameWithNamespace, '\\') !== false) {

            // e.g. 'App\Entity\Test\Test' will return 'test' table name
            // e.g. 'App\Entity\Sub\Test' will return 'subTest' table name
            $last = strrpos($classNameWithNamespace, '\\');
            $nextToLast = strrpos($classNameWithNamespace, '\\', $last - strlen($classNameWithNamespace) - 1);

            $namespace  = substr($classNameWithNamespace, $nextToLast + 1, $last - $nextToLast - 1);
            $className  = substr($classNameWithNamespace, $last + 1);

            if(str_starts_with($className, $namespace) || $namespace == "Entity") {

                $table = lcfirst($className);

            } else {

                $table  = lcfirst($namespace);
                $table .= ucfirst($className);
            }

            return $table;
        }

        return $classNameWithNamespace;
    }

    /**
     * {@inheritdoc}
     */
    public function propertyToColumnName($propertyName, $classNameWithNamespace = null)
    {
        return lcfirst($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function embeddedFieldToColumnName($propertyName, $embeddedColumnName, $classNameWithNamespace = null, $embeddedClassName = null)
    {
        return lcfirst($propertyName).'_'.lcfirst($embeddedColumnName);
    }

    /**
     * {@inheritdoc}
     */
    public function referenceColumnName()
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    public function joinColumnName($propertyName, $classNameWithNamespace = null)
    {
        return lcfirst($propertyName) . '_' . lcfirst($this->referenceColumnName());
    }

    /**
     * {@inheritdoc}
     */
    public function joinTableName($sourceEntity, $targetEntity, $propertyName = null)
    {
        return lcfirst($this->classToTableName($sourceEntity)) . '_' .
               lcfirst($this->classToTableName($propertyName ?? $targetEntity));
    }

    /**
     * {@inheritdoc}
     */
    public function joinKeyColumnName($entityName, $referencedColumnName = null)
    {
        return lcfirst($this->classToTableName($entityName)) . '_' .
               lcfirst(($referencedColumnName ?: $this->referenceColumnName()));
    }
}
