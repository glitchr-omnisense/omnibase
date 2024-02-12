<?php

namespace Base\Database\Mapping;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineAnnotationReader;
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

    protected array $uniqueTableName = [];

    protected ?DoctrineAnnotationReader $annotationReader;
    public function __construct()
    {
        $this->annotationReader = new DoctrineAnnotationReader();
    }

    /**
     * @param $className
     * @return string
     * @throws Exception
     */
    public function classToTableName($className): string
    {
        $className = is_object($className) ? get_class($className) : $className;
        $className = class_exists($className)
            ? (new ReflectionClass($className))->getName()
            : $className;

        $tableName = array_search($className, $this->uniqueTableName);

        //
        // Search for a table name in class metadata
        if (class_exists($className)) {
            if (!$tableName) {

                $reflClass = new ReflectionClass($className);
                
                // Attributes
                $annotations = [];
                foreach($reflClass->getAttributes() as $attribute) {
    
                    $annotation = $attribute->newInstance();
                    if (!is_serializable($annotation)) {
                        throw new Exception("Attribute \"" . get_class($annotation) . "\" failed to serialize. Please implement __serialize/__unserialize, or double-check properties.");
                    }
    
                    $annotations[] = $annotation;
                }

                while ($annotation = array_pop($annotations)) {
                    if ($annotation instanceof Table && !empty($annotation->name)) {
                        $tableName = $annotation->name;
                        break;
                    }
                }

                // Doctrine annotations
                $annotations = $this->annotationReader->getClassAnnotations($reflClass);
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
        if(!$tableName) {

            // Strip App\ or Base\ namespace..
            $tableName = str_lstrip($className, ["App\\", "Base\\"]);

            // Strip first occurence of \Entity\
            $entityNamespace = "Entity\\";
            $pos = strpos($tableName, $entityNamespace);
            if ($pos !== false) $tableName = substr_replace($tableName, "", $pos, strlen($entityNamespace));

            // Defaulting case
            if (empty($tableName)) {

                $tableName = $className;
                if (strrpos($tableName, '\\') !== false) {

                    $tableName = lcfirst(substr($className, strrpos($className, '\\') + 1));
                }
            }

            // Turn from namespace into camel string..
            $tableName = str_replace("\\", "", $tableName);
            $tableName = explode("_", camel2snake($tableName));
            $tableName = array_unique($tableName);

            $tableName = snake2camel(implode("_", $tableName));
            $tableName = lcfirst($tableName);

            // Handle I18n case
            $tableName = preg_replace('/' . self::TABLE_I18N_SUFFIX . '$/', self::TABLE_I18N_SUFFIX, $tableName);
        }

        //
        // Make sure there is no ambiguity or issue related to SQL server
        if (strlen($tableName) > self::TABLE_NAME_SIZE) {
            throw new Exception("Table name will be truncated for \"" . $className . "\"");
        }

        // dump($className, $tableName, $this->uniqueTableName);
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
