<?php

namespace Base\Database\Factory;

use Base\Database\Types\EnumType;
use Base\Database\Types\SetType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\EntityType;
use Base\Field\Type\RelationType;
use Base\Field\Type\RoleType;
use Base\Field\Type\SelectType;
use Base\Field\Type\SlugType;
use Base\Field\Type\TranslationType;
use Base\Service\BaseService;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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

    public function getDoctrineType(string $type) {
        return \Doctrine\DBAL\Types\Type::getType($type);
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

    public function getDataClass(FormInterface $form): ?string
    {
        // Simple case, data_class from current form (with ORM Proxy management)
        if (null !== $dataClass = $form->getConfig()->getDataClass()) {
            if (false === $pos = strrpos($dataClass, '\\__CG__\\')) {
                return $dataClass;
            }

            return substr($dataClass, $pos + 8);
        }

        // Advanced case, loop parent form to get closest data_class
        while (null !== $formParent = $form->getParent()) {
            
            if (null === $dataClass = $formParent->getConfig()->getDataClass()) {
                $form = $formParent;
                continue;
            }

            if (is_subclass_of($dataClass, Collection::class)) {
                $form = $formParent;
                continue;
            }

            // Associations can help to guess the expected returned values
            if($this->hasAssociation($dataClass, $form->getName())) 
                return $this->getAssociationTargetClass($dataClass, $form->getName());
            
            // Doctrine types as well.. (e.g. EnumType or SetType)
            $fieldType = $this->getTypeOfField($dataClass, $form->getName());
            $doctrineType = $this->getDoctrineType($fieldType);
            if($this->isEnumType($doctrineType) || $this->isSetType($doctrineType))
                return get_class($doctrineType);

            break;
        }

        return null;
    }



    public function getFields(string $class, array $fields = [], array $excludedFields = []): array
    {
        if(!empty($fields) && !is_associative($fields))
            throw new \Exception("Associative array expected for 'fields' parameter, '".gettype($fields)."' received");

        $metadata = $this->getClassMetadata($class);
        $validFields = array_fill_keys($metadata->getFieldNames(), []);

        if (!empty($associationNames = array_intersect_key($validFields, $metadata->getAssociationNames())))
            $validFields += $this->getFieldMapping($metadata, $associationNames);

        // Auto detect some fields..
        foreach($validFields as $fieldName => $field) {

            if($fieldName == "id") 
                $validFields[$fieldName] = ["form_type" => HiddenType::class];
            if($fieldName == "uuid") 
                $validFields[$fieldName] = ["form_type" => HiddenType::class];
            if($fieldName == "translations")
                $validFields[$fieldName] = ["form_type" => TranslationType::class];
            if($metadata->getTypeOfField($fieldName) == "datetime")
                $validFields[$fieldName] = ["form_type" => DateTimePickerType::class];
            if($metadata->getTypeOfField($fieldName) == "array")
                $validFields[$fieldName] = ["form_type" => SelectType::class];
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

    private function getFieldMapping(ClassMetadata $metadata, array $associationNames): array
    {
        $fields = [];

        foreach ($associationNames as $assocName) {

            if (!$metadata->isAssociationInverseSide($assocName)) 
                continue;

            $class = $metadata->getAssociationTargetClass($assocName);

            if ($metadata->isSingleValuedAssociation($assocName)) {

                $nullable = ($metadata instanceof ClassMetadataInfo) && isset($metadata->discriminatorColumn['nullable']) && $metadata->discriminatorColumn['nullable'];
                $fields[$assocName] = [
                    'type' => EntityType::class,
                    'data_class' => $class,
                    'required' => !$nullable,
                    'allow_recursive' => false
                ];

                continue;
            }

            $fields[$assocName] = [

                'type' => CollectionType::class,
                'entry_type' => EntityType::class,
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






    public function getTypeOfField(string $class, string $fieldName): string
    {
        return $this->getClassMetadata($class)->getTypeOfField($fieldName);
    }

    public function getAssociationTargetClass(string $class, string $fieldName): string
    {
        return $this->getClassMetadata($class)->getAssociationTargetClass($fieldName);
    }

    public function getTargetClass(string $class, string $mappedOrInversedBy): ?string
    {
        $metadata = $this->getClassMetadata($class);
        foreach($metadata->getAssociationMappings($class) as $association => $mapping) {
            if($mapping["inversedBy"] == $mappedOrInversedBy || $mapping["mappedBy"] == $mappedOrInversedBy) 
                return $mapping["targetEntity"] ?? null;
        }

        return null;
    }

    public function hasAssociation(string $class, string $fieldName)
    {
        $metadata = $this->getClassMetadata($class);
        return $metadata->hasAssociation($fieldName);
    }

    public function getAssociationMapping(string $class, string $fieldName): ?array
    {
        $metadata = $this->getClassMetadata($class);
        return $metadata->getAssociationMappings()[$fieldName] ?? null;
    }

    public function getAssociationMappings(string $class): array
    {
        $metadata = $this->getClassMetadata($class);
        return $metadata->getAssociationMappings();
    }
    
    public function isOwningSide(string $class, string $fieldName):bool
    {
        if(!$this->hasAssociation($class, $fieldName)) return false;
        $metadata = $this->getClassMetadata($class);
        
        return !$metadata->isAssociationInverseSide($fieldName);
    }

    public function isInverseSide(string $class, string $fieldName):bool
    {
        if(!$this->hasAssociation($class, $fieldName)) return false;
        $metadata = $this->getClassMetadata($class);
        
        return $metadata->isAssociationInverseSide($fieldName);
    }

    public function isToOneSide(string $class, string $fieldName): bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isToManySide(string $class, string $fieldName): bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isManyToSide(string $class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::MANY_TO_ONE, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToSide(string $class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::ONE_TO_ONE], true); }
    public function isManyToMany(string $class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToMany (string $class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_MANY], true); }
    public function isManyToOne (string $class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isOneToOne  (string $class, string $fieldName):bool { return \in_array($this->getAssociationType($class, $fieldName), [ClassMetadataInfo::ONE_TO_ONE], true); }
    public function getAssociationType(string $class, string $fieldName)
    {
        if(!$this->hasAssociation($class, $fieldName)) return false;
        $metadata = $this->getClassMetadata($class);

        return $metadata->getAssociationMapping($fieldName)['type'] ?? 0;
    }

}
