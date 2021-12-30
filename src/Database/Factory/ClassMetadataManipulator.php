<?php

namespace Base\Database\Factory;

use Base\Database\Types\EnumType;
use Base\Database\Types\SetType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\AssociationType;
use Base\Field\Type\RelationType;
use Base\Field\Type\RoleType;
use Base\Field\Type\SelectType;
use Base\Field\Type\SlugType;
use Base\Field\Type\TranslationType;
use Base\Service\BaseService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
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

    public function isEntity($class) : bool
    {
        if ($class instanceof ClassMetadataInterface)
            return isset($class->isMappedSuperclass) && $class->isMappedSuperclass === false;
        else if (is_object($class))
            $class = ($class instanceof Proxy) ? get_parent_class($class) : get_class($class);

        return !$this->entityManager->getMetadataFactory()->isTransient($class);
    }

    public function isEnumType($class) : bool
    {
        if ($class instanceof ClassMetadataInterface)
            return isset($class->name);
        else if (is_object($class))
            $class = get_class($class);

        return is_subclass_of($class, EnumType::class);
    }

    public function isSetType($class) : bool
    {
        if ($class instanceof ClassMetadataInterface)
            return isset($class->name);
        else if (is_object($class))
            $class = get_class($class);

        return is_subclass_of($class, SetType::class);
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

        $className = is_object($entity) ? get_class($entity) : $entity;
        $metadata  = $this->entityManager->getClassMetadata($className);
        
        if (!$metadata)
            throw new InvalidArgumentException("Entity expected, '" . $className . "' is not an entity.");
        
        return $metadata;
    }

    public function getFields(string $class, array $fields = [], array $excludedFields = []): array
    {
        if(!empty($fields) && !is_associative($fields))
            throw new \Exception("Associative array expected for 'fields' parameter, '".gettype($fields)."' received");

        $metadata = $this->getClassMetadata($class);
        $validFields = array_fill_keys($metadata->getFieldNames(), []);

        if (!empty($associationNames = array_intersect_key($validFields, $metadata->getAssociationNames())))
            $validFields += $this->getFieldFormType($metadata, $associationNames);

        // Auto detect some fields..
        foreach($validFields as $fieldName => $field) {

            if($fieldName == "id") 
                $validFields[$fieldName] = ["form_type" => HiddenType::class];
            else if($fieldName == "uuid") 
                $validFields[$fieldName] = ["form_type" => HiddenType::class];
            else if($fieldName == "translations")
                $validFields[$fieldName] = ["form_type" => TranslationType::class];
            
            else if($this->getTypeOfField($class, $fieldName) == "datetime")
                $validFields[$fieldName] = ["form_type" => DateTimePickerType::class];
            else if($this->getTypeOfField($class, $fieldName) == "array")
                $validFields[$fieldName] = ["form_type" => SelectType::class];
            else if($this->getTypeOfField($class, $fieldName) == "integer")
                $validFields[$fieldName] = ["form_type" => NumberType::class];

            else if( ($enumType = $this->getDoctrineType($this->getTypeOfField($class, $fieldName))) instanceof EnumType)
                $validFields[$fieldName] = ["form_type" => SelectType::class, "class" => get_class($enumType)];
            else if( ($setType = $this->getDoctrineType($this->getTypeOfField($class, $fieldName))) instanceof SetType)
                $validFields[$fieldName] = ["form_type" => SelectType::class, "class" => get_class($setType)];
        }
        
        foreach($fields as $fieldName => $field) {

            if(is_array($fields[$fieldName]) && !empty($fields[$fieldName]))
                $validFields[$fieldName] = $fields[$fieldName];
        }

        $validFields = $this->filteringFields($validFields, $excludedFields);
        if (empty($fields)) return $validFields;

        $unmappedFields = $this->filteringRemainingFields($validFields, $fields, $excludedFields, $class);
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
        
        return $validFields + $unmappedFields;
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

    private function filteringRemainingFields(array $validFields, array $fields, array $excludedFields, string $class): array
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
            throw new \RuntimeException(sprintf("Field(s) '%s' doesn't exist in %s", implode(', ', $unknowsFields), $class));
        }

        return $unmappedFields;
    }

    private function getFieldFormType(ClassMetadata $metadata, array $associationNames): array
    {
        $fields = [];

        foreach ($associationNames as $assocName) {

            if (!$metadata->isAssociationInverseSide($assocName)) 
                continue;

            $class = $metadata->getAssociationTargetClass($assocName);

            if ($metadata->isSingleValuedAssociation($assocName)) {

                $nullable = ($metadata instanceof ClassMetadataInfo) && isset($metadata->discriminatorColumn['nullable']) && $metadata->discriminatorColumn['nullable'];
                $fields[$assocName] = [
                    'type' => AssociationType::class,
                    'data_class' => $class,
                    'required' => !$nullable,
                    'allow_recursive' => false
                ];

                continue;
            }

            $fields[$assocName] = [

                'type' => CollectionType::class,
                'entry_type' => AssociationType::class,
                'entry_options' => [
                    'data_class' => $class
                ],
                'allow_add' => true,
                'by_reference' => false,
                'allow_recursive' => false
            ];
        }

        return $fields;
    }

    public function hasField($class, string $fieldName): ?string
    {
        $metadata = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->hasField($fieldName);
    }

    public function getTypeOfField($class, string $fieldName): ?string 
    {
        $metadata = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getTypeOfField($fieldName);
    }

    public function getAssociationTargetClass($class, string $fieldName): string 
    { 
        $metadata = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationTargetClass($fieldName);
    }

    public function hasAssociation($class, string $fieldName)
    {
        $metadata = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->hasAssociation($fieldName);
    }

    public function getFieldMapping($class, string $fieldName): ?array
    {
        $metadata  = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getFieldMapping($fieldName) ?? null;
    }
    
    public function getAssociationMapping($class, string $fieldName): ?array
    {
        $metadata  = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationMapping($fieldName) ?? null;
    }

    public function getMapping($class, string $fieldName): ?array
    {
        if($this->hasAssociation($class, $fieldName))
            return $this->getAssociationMapping($class, $fieldName);
        else if($this->hasField($class, $fieldName))
            return $this->getFieldMapping($class, $fieldName);
    
        return null;
    }
    
    public function getAssociationMappings($class): array
    {
        $metadata = $this->getClassMetadata($class);
        if(!$metadata) return false;

        return $metadata->getAssociationMappings();
    }
    
    public function isOwningSide($class, string $fieldName):bool
    {
        if(!$this->hasAssociation($class, $fieldName)) return false;
        $metadata  = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return !$metadata->isAssociationInverseSide($fieldName);
    }

    public function isInverseSide($class, string $fieldName):bool
    {
        if(!$this->hasAssociation($class, $fieldName)) return false;
        $metadata  = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->isAssociationInverseSide($fieldName);
    }

    public function isToOneSide ($class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isToManySide($class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isManyToSide($class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::MANY_TO_ONE, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToSide ($class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::ONE_TO_ONE], true); }
    public function isManyToMany($class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToMany ($class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_MANY], true); }
    public function isManyToOne ($class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isOneToOne  ($class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_ONE], true); }
    public function getAssociationType($class, string $fieldName)
    {
        $class = is_object($class) ? get_class($class) : $class;
        if(!$this->hasAssociation($class, $fieldName)) return false;

        $metadata  = $this->getClassMetadata($class);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationMapping($fieldName)['type'] ?? 0;
    }

}
