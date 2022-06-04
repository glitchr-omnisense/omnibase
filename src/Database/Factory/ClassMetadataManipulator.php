<?php

namespace Base\Database\Factory;

use Base\Database\TranslatableInterface;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Field\Type\ArrayType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\AssociationType;
use Base\Field\Type\SelectType;
use Base\Field\Type\TranslationType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectRepository;
use InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

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

    public function __construct(EntityManagerInterface $entityManager, array $globalExcludedFields = ['id', 'translatable', 'locale'])
    {
        $this->entityManager = $entityManager;
        $this->globalExcludedFields = $globalExcludedFields;
    }

    public function getEntityManager()        : EntityManagerInterface { return $this->entityManager; }
    public function getRepository($className) : ObjectRepository       { return $this->entityManager->getRepository($className); }

    public function isEntity($entityOrClassOrMetadata) : bool
    {
        if (is_object($entityOrClassOrMetadata))
            $entityOrClassOrMetadata = ($entityOrClassOrMetadata instanceof Proxy) ? get_parent_class($entityOrClassOrMetadata) : get_class($entityOrClassOrMetadata);

        if(!is_string($entityOrClassOrMetadata) || !class_exists($entityOrClassOrMetadata)) return false;

        return !$this->entityManager->getMetadataFactory()->isTransient($entityOrClassOrMetadata);
    }

    public function isEnumType($entityOrClassOrMetadata) : bool
    {
        if ($entityOrClassOrMetadata instanceof ClassMetadata)
            $entityOrClassOrMetadata = $entityOrClassOrMetadata->name;
        else if (is_object($entityOrClassOrMetadata))
            $entityOrClassOrMetadata = get_class($entityOrClassOrMetadata);

        return is_subclass_of($entityOrClassOrMetadata, EnumType::class);
    }

    const DEFAULT_TRACKING = 0;

    protected $globalTrackingPolicy = self::DEFAULT_TRACKING;
    public function getGlobalTrackingPolicy() { return $this->globalTrackingPolicy; }
    public function setGlobalTrackingPolicy(int $policy)
    {
        $trackingPolicies = [self::DEFAULT_TRACKING, ClassMetadataInfo::CHANGETRACKING_DEFERRED_IMPLICIT, ClassMetadataInfo::CHANGETRACKING_DEFERRED_EXPLICIT, ClassMetadataInfo::CHANGETRACKING_NOTIFY];
        if(!in_array($policy, $trackingPolicies))
            throw new \Exception("Invalid global tracking policy \"$policy\" provided");

        $this->globalTrackingPolicy = $policy;
        return $this;
    }

    protected $trackingPolicy = [];
    public function getTrackingPolicy($className) { return $this->trackingPolicy[$className] ?? $this->globalTrackingPolicy; }
    public function setTrackingPolicy($className, int $policy)
    {
        $trackingPolicies = [self::DEFAULT_TRACKING, ClassMetadataInfo::CHANGETRACKING_DEFERRED_IMPLICIT, ClassMetadataInfo::CHANGETRACKING_DEFERRED_EXPLICIT, ClassMetadataInfo::CHANGETRACKING_NOTIFY];
        if(!in_array($policy, $trackingPolicies))
            throw new \Exception("Invalid tracking policy \"$policy\" provided for \"$className\"");

        $this->trackingPolicy[$className] = $policy;
        return $this;
    }

    public function isSetType($entityOrClassOrMetadata) : bool
    {
        if ($entityOrClassOrMetadata instanceof ClassMetadata)
            $entityOrClassOrMetadata = $entityOrClassOrMetadata->name;
        else if (is_object($entityOrClassOrMetadata))
            $entityOrClassOrMetadata = get_class($entityOrClassOrMetadata);

        return is_subclass_of($entityOrClassOrMetadata, SetType::class);
    }

    public function getDoctrineType(?string $type)
    {
        if(!$type) return $type;
        return \Doctrine\DBAL\Types\Type::getType($type);
    }

    public function getPrimaryKey($entity) { return $this->getClassMetadata($entity)->getSingleIdentifierFieldName(); }

    public function getDiscriminatorColumn($entity): ?string { return $this->getClassMetadata($entity) ? $this->getClassMetadata($entity)->discriminatorColumn["fieldName"] : null; }
    public function getDiscriminatorValue($entity): ?string { return $this->getClassMetadata($entity) ? $this->getClassMetadata($entity)->discriminatorValue : null; }
    public function getDiscriminatorMap($entity): array { return $this->getClassMetadata($entity) ? $this->getClassMetadata($entity)->discriminatorMap : []; }
    public function getRootEntityName($entity): ?string { return $this->getClassMetadata($entity) ? $this->getClassMetadata($entity)->rootEntityName : null; }

    public function getClassMetadata($entityOrClassOrMetadata)
    {
        if($entityOrClassOrMetadata === null) return null;
        if($entityOrClassOrMetadata instanceof ClassMetadataInfo)
            return $entityOrClassOrMetadata;

        $entityOrClassOrMetadataName = is_object($entityOrClassOrMetadata) ? get_class($entityOrClassOrMetadata) : $entityOrClassOrMetadata;
        $metadata  = class_exists($entityOrClassOrMetadataName) ? $this->entityManager->getClassMetadata($entityOrClassOrMetadataName) : null;
        if (!$metadata)
            throw new InvalidArgumentException("Entity expected, '" . $entityOrClassOrMetadataName . "' is not an entity.");

        return $metadata;
    }

    public function getFields(string $entityOrClassOrMetadata, array $fields = [], array $excludedFields = []): array
    {
        if(!empty($fields) && !is_associative($fields))
            throw new \Exception("Associative array expected for 'fields' parameter, '".gettype($fields)."' received");

        $fieldKeys = array_keys($fields);

        $metadata = $this->getClassMetadata($entityOrClassOrMetadata);
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

            else if($this->getTypeOfField($entityOrClassOrMetadata, $fieldName) == "datetime")
                $validFields[$fieldName] = ["form_type" => DateTimePickerType::class];
            else if($this->getTypeOfField($entityOrClassOrMetadata, $fieldName) == "array")
                $validFields[$fieldName] = ["form_type" => SelectType::class, "tags" => true];
            else if($this->getTypeOfField($entityOrClassOrMetadata, $fieldName) == "integer")
                $validFields[$fieldName] = ["form_type" => NumberType::class];
            else if($this->getTypeOfField($entityOrClassOrMetadata, $fieldName) == "string")
                $validFields[$fieldName] = ["form_type" => TextType::class];
            else if($this->getTypeOfField($entityOrClassOrMetadata, $fieldName) == "text")
                $validFields[$fieldName] = ["form_type" => TextareaType::class];

            else if( ($enumType = $this->getDoctrineType($this->getTypeOfField($entityOrClassOrMetadata, $fieldName))) instanceof EnumType)
                $validFields[$fieldName] = ["form_type" => SelectType::class, "class" => get_class($enumType)];
            else if( ($setType = $this->getDoctrineType($this->getTypeOfField($entityOrClassOrMetadata, $fieldName))) instanceof SetType)
                $validFields[$fieldName] = ["form_type" => SelectType::class, "class" => get_class($setType)];
        }

        $fields = array_map(fn($f) => is_array($f) ? $f : ["form_type" => $f], $fields);
        foreach($fields as $fieldName => $field) {

            if(is_array($fields[$fieldName]) && !empty($fields[$fieldName])) {

                $fields[$fieldName]["form_type"] = $fields[$fieldName]["form_type"] ?? $validFields[$fieldName]["form_type"] ?? null;
                $validFields[$fieldName] = $fields[$fieldName] ?? $validFields[$fieldName] ?? [];
                unset($fields[$fieldName]);
            }
        }

        $validFields = $this->filteringFields($validFields, $excludedFields);
        if (empty($fields)) return array_filter(array_replace(array_fill_keys($fieldKeys, null), $validFields));

        $unmappedFields = $this->filteringRemainingFields($validFields, $fields, $excludedFields, $entityOrClassOrMetadata);
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
            if($aliasName != $fieldName) $aliasNames[$aliasName] = $fieldName;

        $fields = $validFields + $unmappedFields;
        $aliasFields = array_filter($fields, fn($k) => in_array($k, $aliasNames), ARRAY_FILTER_USE_KEY);

        $fields = array_transforms(fn($k,$v): ?array => array_key_exists($k, $aliasNames) ? [$aliasNames[$k], $aliasFields[$aliasNames[$k]]] : [$k,$v], $fields);

        return array_filter(array_replace(array_fill_keys($fieldKeys, null), $fields));
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

    private function filteringRemainingFields(array $validFields, array $fields, array $excludedFields, string $entityOrClassOrMetadata): array
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
            throw new \RuntimeException(sprintf("Field(s) '%s' doesn't exist in %s", implode(', ', $unknowsFields), $entityOrClassOrMetadata));
        }

        return $unmappedFields;
    }

    private function getAssociationFormType(ClassMetadata $metadata, array $associationNames): array
    {
        $fields = [];

        foreach ($associationNames as $assocName) {

            if (!$metadata->isAssociationInverseSide($assocName))
                continue;

            $entityOrClassOrMetadata = $metadata->getAssociationTargetClass($assocName);

            if ($metadata->isSingleValuedAssociation($assocName)) {

                $nullable = ($metadata instanceof ClassMetadataInfo) && isset($metadata->discriminatorColumn['nullable']) && $metadata->discriminatorColumn['nullable'];
                $fields[$assocName] = [
                    'type' => AssociationType::class,
                    'data_class' => $entityOrClassOrMetadata,
                    'required' => !$nullable,
                    'allow_recursive' => false
                ];

                continue;
            }

            $fields[$assocName] = [

                'type' => CollectionType::class,
                'entry_type' => AssociationType::class,
                'entry_options' => [
                    'data_class' => $entityOrClassOrMetadata
                ],
                'allow_add' => true,
                'by_reference' => false,
                'allow_recursive' => false
            ];
        }

        return $fields;
    }

    public function getFieldValue($entity, string|array $fieldPath): mixed
    {
        // Prepare field path information
        if(is_string($fieldPath)) $fieldPath = explode(".", $fieldPath);
        if(empty($fieldPath)) throw new \Exception("No field path provided for \"".get_class($entity)."\" ");

        $entityName = get_class($entity);
        // Extract leading field && get metadata
        $fieldName = array_shift($fieldPath);

        // Check entity validity
        if($entity === null || !is_object($entity)) {
            if(!empty($fieldName)) throw new \Exception("Failed to find property \"".implode('.', array_merge([$fieldName],$fieldPath))."\" using \"".(is_object($entity) ? get_class($entity) : "NULL")."\" data");
            else return null;
        }

        // Extract key information
        $fieldRegex = ["/(\w*)\[(\w*)\]/", "/(\w*)%(\w*)%/", "/(\w*){(\w*)}/", "/(\w*)\((\w*)\)/"];
        $fieldKey   = preg_replace($fieldRegex, "$2", $fieldName, 1);
        if($fieldKey) $fieldName = preg_replace($fieldRegex, "$1", $fieldName, 1);
        else $fieldKey = null;

        // Go get class metadata
        $metadata = $this->getClassMetadata($entity);
        if(!$metadata) return false;

        $entity = $metadata->getFieldValue($entity, $metadata->getFieldName($fieldName));
        if(class_implements_interface($entity, TranslatableInterface::class))
            $entity = $entity->getTranslations();

        if(is_array($entity)) {

            $entity = $fieldKey ? $entity[$fieldKey] ?? null : null;
            $entity = $entity ?? begin($entity);

        } else if(class_implements_interface($entity, Collection::class)) {

            $entity = $entity->containsKey($fieldKey) ? $entity->get($fieldKey ?? 0) : null;
        }

        if($entity === null || !is_object($entity)) {
            if(empty($fieldPath)) return $entity;
            throw new \Exception("Failed to resolve property path \"".implode('.', array_merge([$fieldName],$fieldPath))."\" in \"".$entityName."\"");
        }

        // If field path is empty
        if(empty($fieldPath))
            return $metadata->getFieldValue($entity, $fieldName);

        return $this->getFieldValue($entity, implode(".", $fieldPath));
    }

    public function setFieldValue($entity, string|array $fieldPath, $value)
    {
        $metadata = $this->getClassMetadata($entity);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldPath) ?? $fieldPath;
        return $metadata->setFieldValue($entity, $fieldName, $value);
    }

    public function hasField($entityOrClassOrMetadata, string $fieldName): bool
    {
        $metadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $fieldName = $metadata->fieldNames[$fieldName] ?? null;
        return ($fieldName != null);
    }

    public function hasProperty($entityOrClassOrMetadata, string $fieldName): ?string { return property_exists($entityOrClassOrMetadata, $fieldName); }

    public function getType($entityOrClassOrMetadata, string $property)
    {
        return $this->hasAssociation($entityOrClassOrMetadata, $property)
            ? $this->getTypeOfAssociation($entityOrClassOrMetadata, $property)
            : $this->getTypeOfField($entityOrClassOrMetadata, $property);
    }

    public function getTargetClass($entityOrClassOrMetadata, $fieldName)
    {
        // Associations can help to guess the expected returned values
        if($this->hasAssociation($entityOrClassOrMetadata, $fieldName)) {

            return $this->getAssociationTargetClass($entityOrClassOrMetadata, $fieldName);

        } else if($this->hasField($entityOrClassOrMetadata, $fieldName)) {

            // Doctrine types as well.. (e.g. EnumType or SetType)
            $fieldType = $this->getTypeOfField($entityOrClassOrMetadata, $fieldName);

            $doctrineType = $this->getDoctrineType($fieldType);
            if($this->isEnumType($doctrineType) || $this->isSetType($doctrineType))
                return get_class($doctrineType);
        }

        return null;
    }

    public function getTypeOfField($entityOrClassOrMetadata, string $property)
    {
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        if( ($dot = strpos($property, ".")) > 0 ) {

            $field    = trim(substr($property, 0, $dot));
            $field = $metadata->getFieldName($field) ?? $field;

            $property = trim(substr($property,    $dot+1));

            if(!$metadata->hasAssociation($field))
                throw new \Exception("No association found for field \"$field\" in \"".get_class($entityOrClassOrMetadata)."\"");

            $entityOrClassOrMetadata = $this->getTypeOfField($entityOrClassOrMetadata, $field);
            if ($entityOrClassOrMetadata instanceof ArrayCollection)
                $entityOrClassOrMetadata = $entityOrClassOrMetadata->first();
            else if(is_array($entityOrClassOrMetadata))
                $entityOrClassOrMetadata = current($entityOrClassOrMetadata) ?? null;

            return $this->getTypeOfField($entityOrClassOrMetadata, $property);
        }

        return ($metadata->hasField($property) ? $metadata->getTypeOfField($property) : null);
    }

    public function getTypeOfAssociation($entityOrClassOrMetadata, string $property)
    {
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        if( ($dot = strpos($property, ".")) > 0 ) {

            $field    = trim(substr($property, 0, $dot));
            $field = $metadata->getFieldName($field) ?? $field;

            $property = trim(substr($property,    $dot+1));

            if(!$metadata->hasAssociation($field))
                throw new \Exception("No association found for field \"$field\" in \"".get_class($entityOrClassOrMetadata)."\"");

            $entityOrClassOrMetadata = $this->getTypeOfAssociation($entityOrClassOrMetadata, $field);
            if ($entityOrClassOrMetadata instanceof ArrayCollection)
                $entityOrClassOrMetadata = $entityOrClassOrMetadata->first();
            else if(is_array($entityOrClassOrMetadata))
                $entityOrClassOrMetadata = current($entityOrClassOrMetadata) ?? null;

            return $this->getTypeOfAssociation($entityOrClassOrMetadata, $property);
        }

        return ($metadata->hasAssociation($property) ? $metadata->getAssociationMapping($property)["type"] ?? null : null);
    }

    public function fetchEntityName(string $entityName, array|string $fieldPath, ?array &$data = null): ?string { return $this->fetchEntityMapping($entityName, $fieldPath, $data)["targetEntity"] ?? null; }
    public function fetchEntityMapping(string $entityName, array|string $fieldPath): ?array
    {
        $fieldPath = is_array($fieldPath) ? $fieldPath : explode(".", $fieldPath);
        $fieldName = head($fieldPath);
        $classMetadata = $this->entityManager->getClassMetadata($entityName);

        if ($classMetadata->hasAssociation($classMetadata->getFieldName($fieldName)))
            $entityMapping = $classMetadata->associationMappings[$classMetadata->getFieldName($fieldName)];
        else if ($classMetadata->hasField($classMetadata->getFieldName($fieldName)))
            $entityMapping = $classMetadata->fieldMappings[$classMetadata->getFieldName($fieldName)];
        else return null;

        $fieldName = $fieldPath ? head($fieldPath) : $fieldName;
        $fieldPath = tail($fieldPath, $this->isToManySide($entityName, $fieldName) ? -2 : -1);
        if(!$fieldPath) return $entityMapping;

        if(!array_key_exists("targetEntity", $entityMapping))
            return null; // Fallback, invalid pass provided

        return $this->fetchEntityMapping($entityMapping["targetEntity"], implode(".", $fieldPath));
    }

    public function getFieldName(string $entityName, array|string $fieldPath): ?string { return $this->resolveFieldPath($entityName, $fieldPath); }
    public function resolveFieldPath($entityOrClassOrMetadata, array|string $fieldPath): ?string
    {
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $entityName = $metadata->getName();
        $fieldPath = is_array($fieldPath) ? $fieldPath : explode(".", $fieldPath);
        $fieldName = head($fieldPath);

        $classMetadata = $this->getClassMetadata($entityName);
        $associationFields = array_keys($classMetadata->associationMappings);
        $columnNames = array_merge(array_combine($associationFields,$associationFields), $classMetadata->fieldNames);

        while(!array_key_exists($fieldName, $columnNames)) {

            if(!get_parent_class($classMetadata->getName())) break;

            $classMetadata = $this->getClassMetadata(get_parent_class($classMetadata->getName()));
            $associationFields = array_keys($classMetadata->associationMappings);
            $columnNames = array_merge(array_combine($associationFields,$associationFields), $classMetadata->fieldNames);
        }

        $fieldName = $columnNames[$fieldName] ?? $fieldName;
        if(!$fieldName) return null;

        $fieldPath = tail($fieldPath, $this->isToManySide($entityName, $fieldName) ? -2 : -1);
        if(!$fieldPath) return $fieldName;

        $filePath = $this->hasAssociation($entityName, $fieldName) ? $this->resolveFieldPath($this->getAssociationTargetClass($entityName, $fieldName), $fieldPath) : null;
        if(!$filePath) return null;

        return $fieldName.".".$filePath;
    }

    public function getAssociationTargetClass($entityOrClassOrMetadata, string $fieldName): string
    {
        $metadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationTargetClass($fieldName);
    }

    public function hasAssociation($entityOrClassOrMetadata, string $fieldName)
    {
        $metadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->hasAssociation($fieldName);
    }

    public function getFieldMapping($entityOrClassOrMetadata, string $fieldName): ?array
    {
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getFieldMapping($fieldName) ?? null;
    }

    public function getAssociationMapping($entityOrClassOrMetadata, string $fieldName): ?array
    {
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationMapping($fieldName) ?? null;
    }

    public function getUniqueKeys($entityOrClassOrMetadata, bool $inherits = false):array
    {
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $uniqueKeys = [];

        do {

            foreach($metadata->fieldNames as $fieldName)
                if($this->getMapping($entityOrClassOrMetadata, $fieldName)["unique"] ?? false) $uniqueKeys[] = $fieldName;

            $parentName = get_parent_class($metadata->getName());
            $metadata = $parentName ? $this->getClassMetadata($parentName) : null;

        } while($metadata && $metadata->getName() && $inherits);

        return $uniqueKeys;
    }

    public function hasTranslation($entityOrClassOrMetadata): bool { return $this->hasAssociation($entityOrClassOrMetadata, "translations"); }
    public function getTranslationMapping($entityOrClassOrMetadata, string $fieldName): ?array
    {
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        if ($this->hasAssociation($entityOrClassOrMetadata, "translations"))
            return $this->getMapping($metadata->getAssociationMapping("translations")["targetEntity"], $fieldName);
    }

    public function getMapping($entityOrClassOrMetadata, string $fieldName): ?array
    {
        if($this->hasAssociation($entityOrClassOrMetadata, $fieldName))
            return $this->getAssociationMapping($entityOrClassOrMetadata, $fieldName);
        else if($this->hasField($entityOrClassOrMetadata, $fieldName))
            return $this->getFieldMapping($entityOrClassOrMetadata, $fieldName);
        else if($this->hasTranslation($entityOrClassOrMetadata))
            return $this->getTranslationMapping($entityOrClassOrMetadata, $fieldName);

        return null;
    }

    public function getAssociationMappings($entityOrClassOrMetadata): array
    {
        $metadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        return $metadata->getAssociationMappings();
    }

    public function isOwningSide($entityOrClassOrMetadata, string $fieldName):bool
    {
        if(!$this->hasAssociation($entityOrClassOrMetadata, $fieldName)) return false;
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return !$metadata->isAssociationInverseSide($fieldName);
    }

    public function isInverseSide($entityOrClassOrMetadata, string $fieldName):bool
    {
        if(!$this->hasAssociation($entityOrClassOrMetadata, $fieldName)) return false;
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->isAssociationInverseSide($fieldName);
    }

    public function isToOneSide ($entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isToManySide($entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isManyToSide($entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::MANY_TO_ONE, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToSide ($entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::ONE_TO_ONE], true); }
    public function isManyToMany($entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToMany ($entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_MANY], true); }
    public function isManyToOne ($entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isOneToOne  ($entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_ONE], true); }
    public function getAssociationType($entityOrClassOrMetadata, string $fieldName)
    {
        $metadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$metadata) return false;

        if(!$this->hasAssociation($metadata, $fieldName)) return false;

        $fieldName = $metadata->getFieldName($fieldName) ?? $fieldName;
        return $metadata->getAssociationMapping($fieldName)['type'] ?? 0;
    }

}
