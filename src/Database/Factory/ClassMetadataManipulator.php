<?php

namespace Base\Database\Factory;

use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Field\Type\ArrayType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\AssociationType;
use Base\Field\Type\SelectType;
use Base\Field\Type\TranslationType;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

class ClassMetadataManipulator
{
    /** 
     * @var EntityManagerInterface
     * */
    protected $entityManager;

    /**
     * @var array
     */
    protected array $globalExcludedFields;

    public function __construct(EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag)
    {
        $this->entityManager = $entityManager;

        $this->globalExcludedFields = [];
        if ( ($matches = preg_grep('/^base.database.excluded_fields\.[0-9]*$/', array_keys($parameterBag->all()))) )
            foreach ($matches as $match) $this->globalExcludedFields[] = $parameterBag->get($match);
    }

    public function isEntity($entityOrClass) : bool
    {
        if ($entityOrClass instanceof ClassMetadataInterface)
            return isset($entityOrClass->isMappedSuperclass) && $entityOrClass->isMappedSuperclass === false;
        else if (is_object($entityOrClass))
            $entityOrClass = ($entityOrClass instanceof Proxy) ? get_parent_class($entityOrClass) : get_class($entityOrClass);

        return !$this->entityManager->getMetadataFactory()->isTransient($entityOrClass);
    }

    public function isEnumType($entityOrClass) : bool
    {
        if ($entityOrClass instanceof ClassMetadataInterface)
            return isset($entityOrClass->name);
        else if (is_object($entityOrClass))
            $entityOrClass = get_class($entityOrClass);

        return is_subclass_of($entityOrClass, EnumType::class);
    }

    public function isSetType($entityOrClass) : bool
    {
        if ($entityOrClass instanceof ClassMetadataInterface)
            return isset($entityOrClass->name);
        else if (is_object($entityOrClass))
            $entityOrClass = get_class($entityOrClass);

        return is_subclass_of($entityOrClass, SetType::class);
    }

    public function getDoctrineType(?string $type)
    {
        if(!$type) return $type;
        return \Doctrine\DBAL\Types\Type::getType($type);
    }

    public function getDiscriminatorColumn($entity): ?string { return $this->getClassMetadata($entity)->discriminatorColumn["fieldName"]; }
    public function getDiscriminatorValue($entity): ?string { return $this->getClassMetadata($entity)->discriminatorValue; }
    public function getDiscriminatorMap($entity): array { return $this->getClassMetadata($entity)->discriminatorMap; }
    public function getRootEntityName($entity): ?string { return $this->getClassMetadata($entity)->rootEntityName; }

    public function getTargetClass($entity, $fieldName)
    {
        // Associations can help to guess the expected returned values
        if($this->hasAssociation($entity, $fieldName)) {

            return $this->getAssociationTargetClass($entity, $fieldName);

        } else if($this->hasField($entity, $fieldName)) {

            // Doctrine types as well.. (e.g. EnumType or SetType)
            $fieldType = $this->getTypeOfField($entity, $fieldName);

            $doctrineType = $this->getDoctrineType($fieldType);
            if($this->isEnumType($doctrineType) || $this->isSetType($doctrineType))
                return get_class($doctrineType);
        }

        return null;
    }

    public function getClassMetadata($entity)
    {
        if($entity === null) return null;

        $entityOrClassName = is_object($entity) ? get_class($entity) : $entity;
        $metadata  = $this->entityManager->getClassMetadata($entityOrClassName);
        
        if (!$metadata)
            throw new InvalidArgumentException("Entity expected, '" . $entityOrClassName . "' is not an entity.");
        
        return $metadata;
    }

    public function getFields(string $entityOrClass, array $fields = [], array $excludedFields = []): array
    {
        if(!empty($fields) && !is_associative($fields))
            throw new \Exception("Associative array expected for 'fields' parameter, '".gettype($fields)."' received");

        $metadata = $this->getClassMetadata($entityOrClass);
        $validFields  = array_fill_keys(array_merge($metadata->getFieldNames()), []);
        $validFields += $this->getAssociationFormType($metadata, $metadata->getAssociationNames());

        // Auto detect some fields..
        foreach($validFields as $fieldName => $field) {

            if($fieldName == "id") 
                $validFields[$fieldName] = ["form_type" => HiddenType::class];
            else if($fieldName == "uuid") 
                $validFields[$fieldName] = ["form_type" => HiddenType::class];
            else if($fieldName == "translations")
                $validFields[$fieldName] = ["form_type" => TranslationType::class];
            
            else if($this->getTypeOfField($entityOrClass, $fieldName) == "datetime")
                $validFields[$fieldName] = ["form_type" => DateTimePickerType::class];
            else if($this->getTypeOfField($entityOrClass, $fieldName) == "array")
                $validFields[$fieldName] = ["form_type" => ArrayType::class];
            else if($this->getTypeOfField($entityOrClass, $fieldName) == "integer")
                $validFields[$fieldName] = ["form_type" => NumberType::class];
            else if($this->getTypeOfField($entityOrClass, $fieldName) == "string")
                $validFields[$fieldName] = ["form_type" => TextType::class];
            else if($this->getTypeOfField($entityOrClass, $fieldName) == "text")
                $validFields[$fieldName] = ["form_type" => TextareaType::class];

            else if( ($enumType = $this->getDoctrineType($this->getTypeOfField($entityOrClass, $fieldName))) instanceof EnumType)
                $validFields[$fieldName] = ["form_type" => SelectType::class, "class" => get_class($enumType)];
            else if( ($setType = $this->getDoctrineType($this->getTypeOfField($entityOrClass, $fieldName))) instanceof SetType)
                $validFields[$fieldName] = ["form_type" => SelectType::class, "class" => get_class($setType)];
        }

        $fields = array_map(fn($f) => is_array($f) ? $f : ["form_type" => $f], $fields);
        foreach($fields as $fieldName => $field) {

            if(is_array($fields[$fieldName]) && !empty($fields[$fieldName]))
                $validFields[$fieldName] = $fields[$fieldName];
        }

        $validFields = $this->filteringFields($validFields, $excludedFields);
        if (empty($fields)) return $validFields;

        $unmappedFields = $this->filteringRemainingFields($validFields, $fields, $excludedFields, $entityOrClass);
        foreach ($fields as $fieldName => $field) {

            if (\in_array($fieldName, $excludedFields, true))
                continue;

            if (isset($unmappedFields[$fieldName])) 
                continue;

            if (null === $field) 
                continue;

            if (false === ($field['display'] ?? true)) {
                unset($validFields[$fieldName]);
                continue;
            }

            // Override with priority
            $validFields[$fieldName] = $field + $validFields[$fieldName];
        }

        $aliasNames = [];
        foreach($metadata->fieldNames as $aliasName => $fieldName)
            if($aliasName != $fieldName) $aliasNames[$fieldName] = $aliasName;

        $fields = $validFields + $unmappedFields;
        $aliasFields = array_filter($fields, fn($k) => in_array($k, $aliasNames), ARRAY_FILTER_USE_KEY);

        $fields = array_key_removes($fields, ...array_values($aliasNames));
        $fields = array_transforms(fn($k,$v): ?array => array_key_exists($k, $aliasNames) ? [$aliasNames[$k], $aliasFields[$aliasNames[$k]]] : [$k,$v], $fields);

        return $fields;
    }

    private function filteringFields(array $fields, array $excludedFields): array
    {
        $excludedFields = array_merge($this->globalExcludedFields, $excludedFields);

        $validFields = [];
        foreach ($fields as $fieldName => $field) {
            if (\in_array($fieldName, $excludedFields, true))
                continue;

            $validFields[$fieldName] = $field;
        }

        return $validFields;
    }

    private function filteringRemainingFields(array $validFields, array $fields, array $excludedFields, string $entityOrClass): array
    {
        $unmappedFields = [];

        $validFieldKeys = array_keys($validFields);
        $unknowsFields = [];

        foreach ($fields as $fieldName => $field) {
            if (\in_array($fieldName, $excludedFields)) continue;
            if (\in_array($fieldName, $validFieldKeys, true)) continue;

            if (false === ($field['mapped'] ?? true)) {
                $unmappedFields[$fieldName] = $field;
                continue;
            }

            $unknowsFields[] = $fieldName;
        }

        if (\count($unknowsFields) > 0) {
            throw new \RuntimeException(sprintf("Field(s) '%s' doesn't exist in %s", implode(', ', $unknowsFields), $entityOrClass));
        }

        return $unmappedFields;
    }

    private function getAssociationFormType(ClassMetadata $metadata, array $associationNames): array
    {
        $fields = [];

        foreach ($associationNames as $assocName) {

            if (!$metadata->isAssociationInverseSide($assocName)) 
                continue;

            $entityOrClass = $metadata->getAssociationTargetClass($assocName);

            if ($metadata->isSingleValuedAssociation($assocName)) {

                $nullable = ($metadata instanceof ClassMetadataInfo) && isset($metadata->discriminatorColumn['nullable']) && $metadata->discriminatorColumn['nullable'];
                $fields[$assocName] = [
                    'type' => AssociationType::class,
                    'data_class' => $entityOrClass,
                    'required' => !$nullable,
                    'allow_recursive' => false
                ];

                continue;
            }

            $fields[$assocName] = [

                'type' => CollectionType::class,
                'entry_type' => AssociationType::class,
                'entry_options' => [
                    'data_class' => $entityOrClass
                ],
                'allow_add' => true,
                'by_reference' => false,
                'allow_recursive' => false
            ];
        }

        return $fields;
    }

    public function hasProperty($entityOrClass, string $fieldName): ?string
    {
        return property_exists($entityOrClass, $fieldName);
    }

    public function hasField($entityOrClass, string $fieldName): ?string
    {
        $metadata = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->hasField($fieldName);
    } 

    public function getFieldValue($entity, string $fieldName): mixed 
    {
        $metadata = $this->getClassMetadata($entity);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getFieldValue($entity, $fieldName);
    }

    public function setFieldValue($entity, string $fieldName, $value) 
    {
        $metadata = $this->getClassMetadata($entity);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->setFieldValue($entity, $fieldName, $value);
    }

    public function getTypeOfField($entityOrClass, string $fieldName): ?string 
    {
        $metadata = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getTypeOfField($fieldName);
    }

    public function getAssociationTargetClass($entityOrClass, string $fieldName): string 
    { 
        $metadata = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationTargetClass($fieldName);
    }

    public function hasAssociation($entityOrClass, string $fieldName)
    {
        $metadata = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->hasAssociation($fieldName);
    }

    public function getFieldMapping($entityOrClass, string $fieldName): ?array
    {
        $metadata  = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getFieldMapping($fieldName) ?? null;
    }
    
    public function getAssociationMapping($entityOrClass, string $fieldName): ?array
    {
        $metadata  = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationMapping($fieldName) ?? null;
    }

    public function getMapping($entityOrClass, string $fieldName): ?array
    {
        if($this->hasAssociation($entityOrClass, $fieldName))
            return $this->getAssociationMapping($entityOrClass, $fieldName);
        else if($this->hasField($entityOrClass, $fieldName))
            return $this->getFieldMapping($entityOrClass, $fieldName);
    
        return null;
    }
    
    public function getAssociationMappings($entityOrClass): array
    {
        $metadata = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        return $metadata->getAssociationMappings();
    }
    
    public function isOwningSide($entityOrClass, string $fieldName):bool
    {
        if(!$this->hasAssociation($entityOrClass, $fieldName)) return false;
        $metadata  = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return !$metadata->isAssociationInverseSide($fieldName);
    }

    public function isInverseSide($entityOrClass, string $fieldName):bool
    {
        if(!$this->hasAssociation($entityOrClass, $fieldName)) return false;
        $metadata  = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->isAssociationInverseSide($fieldName);
    }

    public function isToOneSide ($entityOrClass, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClass, $fieldName), [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isToManySide($entityOrClass, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClass, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isManyToSide($entityOrClass, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClass, $fieldName), [ClassMetadataInfo::MANY_TO_ONE, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToSide ($entityOrClass, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClass, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::ONE_TO_ONE], true); }
    public function isManyToMany($entityOrClass, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClass, $fieldName), [ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToMany ($entityOrClass, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClass, $fieldName), [ClassMetadataInfo::ONE_TO_MANY], true); }
    public function isManyToOne ($entityOrClass, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClass, $fieldName), [ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isOneToOne  ($entityOrClass, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClass, $fieldName), [ClassMetadataInfo::ONE_TO_ONE], true); }
    public function getAssociationType($entityOrClass, string $fieldName)
    {
        $entityOrClass = is_object($entityOrClass) ? get_class($entityOrClass) : $entityOrClass;
        if(!$this->hasAssociation($entityOrClass, $fieldName)) return false;

        $metadata  = $this->getClassMetadata($entityOrClass);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationMapping($fieldName)['type'] ?? 0;
    }

}
