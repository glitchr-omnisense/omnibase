<?php

namespace Base\Database;

use Base\Service\BaseService;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Table;

/**
 * Custom Naming Strategy
 *
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 */
class NamingStrategy implements \Doctrine\ORM\Mapping\NamingStrategy
{
    public const TABLE_NAME_SIZE = 64;
    
    public static function camelToSnakeCase($input): string { return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input)); }
    public static function snakeToCamelCase($input): string { return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input)))); }

    /**
     * {@inheritdoc}
     */

    private $uniqueTableName = [];
    public function classToTableName($classNameWithNamespace) : string
    {
        //
        // Invariant table name if not a class
        if(!class_exists($classNameWithNamespace))
            return $classNameWithNamespace;

        //
        // Cache lookup table
        $classNameWithNamespace = (new \ReflectionClass($classNameWithNamespace))->getName();
        $tableName = array_search($classNameWithNamespace, $this->uniqueTableName);

        //
        // Search for a table name in class annotation
        if(!$tableName) {

            $annotationReader = new AnnotationReader();
            $annotations = $annotationReader->getClassAnnotations(new \ReflectionClass($classNameWithNamespace));
            while  ($annotation = array_pop($annotations)) {
                if ($annotation instanceof Table && !empty($annotation->name)) {
                    $tableName = $annotation->name;
                    break;
                }
            }
        }

        //
        // Determination of table name based on class information
        if(!$tableName) {

            $tableName = ltrim(strstr($classNameWithNamespace, "\\Entity\\"), "\\Entity\\");
            if(empty($tableName)) {

                $tableName = $classNameWithNamespace;
                if(strrpos($tableName, '\\') !== false)
                    $tableName = lcfirst(substr($classNameWithNamespace, strrpos($classNameWithNamespace, '\\') + 1));
            }

            $tableName = str_replace("\\", "", $tableName);
            $tableName = explode("_",self::camelToSnakeCase($tableName));
            
            $prev = null;
            foreach($tableName as $key => $current) {
                if($current == $prev) unset($tableName[$key]);
                $prev = $current;
            }

            $tableName = self::snakeToCamelCase(implode("_", $tableName));
            $tableName = lcfirst($tableName);
            $tableName = preg_replace('/Translation$/', 'Intl', $tableName);
        }

        //
        // Make sure there is no ambiguity or issue related to SQL server
        if(strlen($tableName) > self::TABLE_NAME_SIZE)
            throw new \Exception("Table name will be truncated for \"".$classNameWithNamespace."\"");

        if(array_key_exists($tableName, $this->uniqueTableName) && $classNameWithNamespace !=  $this->uniqueTableName[$tableName])
            throw new \Exception("Ambiguous table name \"".$tableName."\" found between \"".$this->uniqueTableName[$tableName]."\" and \"".$classNameWithNamespace."\"");

        $this->uniqueTableName[$tableName] = $classNameWithNamespace;
        return $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function propertyToColumnName($propertyName, $classNameWithNamespace = null) :string
    {
        return lcfirst($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function embeddedFieldToColumnName($propertyName, $embeddedColumnName, $classNameWithNamespace = null, $embeddedClassName = null) : string
    {
        return lcfirst($propertyName).'_'.lcfirst($embeddedColumnName);
    }

    /**
     * {@inheritdoc}
     */
    public function referenceColumnName() : string
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    public function joinColumnName($propertyName, $classNameWithNamespace = null) : string
    {
        return lcfirst($propertyName) . '_' . lcfirst($this->referenceColumnName());
    }

    /**
     * {@inheritdoc}
     */
    public function joinTableName($sourceEntity, $targetEntity, $propertyName = null) : string
    {
        return lcfirst($this->classToTableName($sourceEntity)) . '_' .
               lcfirst($this->classToTableName($propertyName ?? $targetEntity));
    }

    /**
     * {@inheritdoc}
     */
    public function joinKeyColumnName($entityName, $referencedColumnName = null) : string
    {
        return lcfirst($this->classToTableName($entityName)) . '_' .
               lcfirst(($referencedColumnName ?: $this->referenceColumnName()));
    }
}
