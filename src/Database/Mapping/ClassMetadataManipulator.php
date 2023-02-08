<?php

namespace Base\Database\Mapping;

use App\Entity\Marketplace\Product\Extra\Wallpaper;
use App\Entity\Marketplace\Sales\Region;
use Base\Cache\Abstract\AbstractLocalCache;
use Base\Database\Mapping\ClassMetadataCompletor;
use Base\Database\TranslatableInterface;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\AssociationType;
use Base\Field\Type\SelectType;
use Base\Field\Type\TranslationType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use InvalidArgumentException;

use Base\Database\Mapping\Factory\ClassMetadataFactory;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ClassMetadataManipulator extends AbstractLocalCache
{
    /**
     * @var ManagerRegistry
     * */
    protected $doctrine;

    /**
     * @var EntityManagerInterface
     * */
    protected $entityManager;

    /**
     * @var array
     */
    protected array $globalExcludedFields;

    protected static ?string $cacheDir = null;

    public function __construct(ManagerRegistry $doctrine, EntityManagerInterface $entityManager, ?string $cacheDir = null, array $globalExcludedFields = ['id', 'translatable', 'locale'])
    {
        $this->doctrine = $doctrine;
        $this->entityManager = $entityManager;
        $this->globalExcludedFields = $globalExcludedFields;
        
        self::$cacheDir = self::$cacheDir ?? $cacheDir;
        if($cacheDir) parent::__construct(self::$cacheDir);
    }

    public function getEntityManager() : EntityManagerInterface { return $this->entityManager; }
    public function isEntity($entityOrClassOrMetadata) : bool
    {
        if (is_object($entityOrClassOrMetadata))
            $entityOrClassOrMetadata = ($entityOrClassOrMetadata instanceof Proxy) ? get_parent_class($entityOrClassOrMetadata) : get_class($entityOrClassOrMetadata);

        if(!is_string($entityOrClassOrMetadata) || !class_exists($entityOrClassOrMetadata)) return false;

        return !$this->getEntityManager()->getMetadataFactory()->isTransient($entityOrClassOrMetadata) || is_instanceof($entityOrClassOrMetadata, Proxy::class);
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
        
        try { $doctrineType = \Doctrine\DBAL\Types\Type::getType($type); }
        catch (\Exception $e) { throw new \LogicException("Have you modified an entity (or an enum), or imported a new database ? Please doom the cache if so. Also make sure to use custom db features from base component", $e->getCode(), $e); }

        return $doctrineType;
    }

    public function getPrimaryKey($entity) { return $this->getClassMetadata($entity)->getSingleIdentifierFieldName(); }

    public function getDiscriminatorColumn($entity): ?string { return $this->getClassMetadata($entity) ? $this->getClassMetadata($entity)->discriminatorColumn["fieldName"] : null; }
    public function getDiscriminatorValue($entity): ?string { return $this->getClassMetadata($entity) ? $this->getClassMetadata($entity)->discriminatorValue : null; }
    public function getDiscriminatorMap($entity): array { return $this->getClassMetadata($entity) ? $this->getClassMetadata($entity)->discriminatorMap : []; }
    public function getRootEntityName($entity): ?string { return $this->getClassMetadata($entity) ? $this->getClassMetadata($entity)->rootEntityName : null; }

    public function getRepository(null|string|object $entityOrClassOrMetadata)
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        return $classMetadata ? $this->getEntityManager()->getRepository($classMetadata->name) : null;
    }

    public function getClassMetadata(null|string|object $entityOrClassOrMetadata)
    {
        if($entityOrClassOrMetadata === null) return null;
        if($entityOrClassOrMetadata instanceof PersistentCollection)
            return $entityOrClassOrMetadata->getTypeClass();
        if($entityOrClassOrMetadata instanceof ClassMetadataInfo)
            return $entityOrClassOrMetadata;

        if(is_object($entityOrClassOrMetadata) && !$this->isEntity($entityOrClassOrMetadata))
            return null;

        $entityOrClassOrMetadataName = is_object($entityOrClassOrMetadata) ? get_class($entityOrClassOrMetadata) : $entityOrClassOrMetadata;
        $classMetadata  = class_exists($entityOrClassOrMetadataName) ? $this->doctrine->getManagerForClass($entityOrClassOrMetadataName)?->getClassMetadata($entityOrClassOrMetadataName) : null;
        if (!$classMetadata)
            throw new InvalidArgumentException("Entity expected, '" . $entityOrClassOrMetadataName . "' is not an entity.");

        return $classMetadata;
    }

    public function getFields(null|string|object $entityOrClassOrMetadata, array $fields = [], array $excludedFields = []): array
    {
        if(!empty($fields) && !is_associative($fields))
            throw new \Exception("Associative array expected for 'fields' parameter, '".gettype($fields)."' received");

        $fieldKeys = array_keys($fields);

        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        $validFields  = array_fill_keys(array_merge($classMetadata->getFieldNames()), []);
        $validFields += $this->getAssociationFormType($classMetadata, $classMetadata->getAssociationNames());

        // Auto detect some fields..
        foreach($validFields as $fieldName => $field) {

            if($fieldName == "id")
                $validFields[$fieldName] = ["form_type" => HiddenType::class];
            else if($fieldName == "uuid")
                $validFields[$fieldName] = ["form_type" => HiddenType::class];
            else if($fieldName == "translations")
                $validFields[$fieldName] = [
                    "form_type" => TranslationType::class,
                    "translatable_class" => $classMetadata->getName(),
                    "translation_class"  => $classMetadata->getName()::getTranslationEntityClass(),
                ];
            else if($this->getTypeOfField($entityOrClassOrMetadata, $fieldName) == "datetime")
                $validFields[$fieldName] = ["form_type" => DateTimePickerType::class];
            else if($this->getTypeOfField($entityOrClassOrMetadata, $fieldName) == "array" || $this->getTypeOfField($entityOrClassOrMetadata, $fieldName) == "json")
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
        foreach($classMetadata->fieldNames as $aliasName => $fieldName)
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

    private function filteringRemainingFields(array $validFields, array $fields, array $excludedFields, null|string|object $entityOrClassOrMetadata): array
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

    private function getAssociationFormType(ClassMetadata $classMetadata, array $associationNames): array
    {
        $fields = [];

        foreach ($associationNames as $assocName) {

            if (!$classMetadata->isAssociationInverseSide($assocName))
                continue;

            $entityOrClassOrMetadata = $classMetadata->getAssociationTargetClass($assocName);

            if ($classMetadata->isSingleValuedAssociation($assocName)) {

                $nullable = ($classMetadata instanceof ClassMetadataInfo) && isset($classMetadata->discriminatorColumn['nullable']) && $classMetadata->discriminatorColumn['nullable'];
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
        if($fieldPath == "") return $entity;

        // Prepare field path information
        if(is_string($fieldPath)) $fieldPath = explode(".", $fieldPath);
        if(empty($fieldPath)) throw new \Exception("No field path provided for \"".get_class($entity)."\" ");

        if(!$entity) return null;

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
        $classMetadata = $this->getClassMetadata($entity);
        if(!$classMetadata) return null;

        if ($entity instanceof Proxy)
            $entity->__load();

        if(class_implements_interface($entity, TranslatableInterface::class) && $fieldName == "translations") {

            $fieldValue = $entity->getTranslations();
            $fieldName  = implode(".", $fieldPath);
            $fieldPath = "";
        }

        if($this->hasField($entity, $fieldName))
            $fieldValue = $classMetadata->getFieldValue($entity, $fieldName);
        else {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $fieldValue = $propertyAccessor->getValue($entity, $fieldName);
        }

        if(is_array($fieldValue)) {

            $entity = $fieldKey ? $fieldValue[$fieldKey] ?? null : null;
            $fieldValue = $fieldValue ?? begin($fieldValue);

        } else if(class_implements_interface($fieldValue, Collection::class)) {

            $fieldValue = $fieldValue->containsKey($fieldKey) ? $fieldValue->get($fieldKey ?? 0) : null;
        }

        if($fieldValue === null || !is_object($fieldValue)) {
            if(empty($fieldPath)) return $fieldValue;
            throw new \Exception("Failed to resolve property path \"".implode('.', array_merge([$fieldName],$fieldPath))."\" in \"".$entityName."\"");
        }

        // If field path is not empty
        if(!empty($fieldPath))
            return $this->getFieldValue($fieldValue, implode(".", $fieldPath));

        // Access property
        return $fieldValue;
    }

    public function setFieldValue($entity, string|array $fieldPath, $value)
    {
        $classMetadata = $this->getClassMetadata($entity);
        if(!$classMetadata) return false;

        $fieldName = $this->getFieldName($entity, $fieldPath) ?? $fieldPath;
        return $classMetadata->setFieldValue($entity, $fieldName, $value);
    }

    public function hasField(null|string|object $entityOrClassOrMetadata, string $fieldName): bool
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        $fieldName = $this->getFieldName($classMetadata, $fieldName) ?? null;
        return ($fieldName != null);
    }

    public function hasProperty(null|string|object $entityOrClassOrMetadata, string $fieldName): ?string { return property_exists($entityOrClassOrMetadata, $fieldName); }

    public function getPropertyValue($entity, string $property)
    {
        if(!$entity) return null;

        $classMetadata = $this->getClassMetadata($entity);
        $fieldName = $classMetadata->getFieldName($property);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        return $this->hasProperty($entity, $fieldName) ? $propertyAccessor->getValue($entity, $fieldName) : null;
    }

    public function setPropertyValue($entity, string $property, $value)
    {
        $classMetadata = $this->getClassMetadata($entity);
        $fieldName = $classMetadata->getFieldName($property);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        return $propertyAccessor->setValue($entity, $fieldName, $value);
    }

    public function getType(null|string|object $entityOrClassOrMetadata, string $property)
    {
        return $this->hasAssociation($entityOrClassOrMetadata, $property)
            ? $this->getTypeOfAssociation($entityOrClassOrMetadata, $property)
            : $this->getTypeOfField($entityOrClassOrMetadata, $property);
    }

    public function getClosestEntity(FormInterface|FormEvent $form)
    {
        if ($form instanceof FormEvent)
            $form = $form->getForm();

        while(!$this->isEntity($form->getData())) {

            $form = $form->getParent();
            if($form === null) return null;
        }

        return $form->getData();
    }

    public function getClosestEntityCollection(FormInterface|FormEvent $form)
    {
        if ($form instanceof FormEvent)
            $form = $form->getForm();

        do {

            $form = $form->getParent();
            if($form === null) return null;

        } while (!$this->isEntity($form->getParent()?->getData()));

        return $form->getData();
    }

    public function isCollectionOwner(object $entityOrForm, ?Collection $collection = null) : ?bool
    {
        $entity       = $this->isEntity($entityOrForm) ? $entityOrForm : $this->getClosestEntity($entityOrForm);
        if($entity == null) return null;

        $collection ??= $this->isEntity($entityOrForm) ? null : $this->getClosestEntityCollection($entityOrForm);
        if(!$collection instanceof Collection) return null;

        if($collection instanceof PersistentCollection) {

            if($collection->getOwner() === null) return null;

            $isTranslatable = class_implements_interface($collection->getOwner(), TranslationInterface::class);
            if($isTranslatable && $entity == $collection->getOwner()->getTranslatable()) return true;

            return $entity === $collection->getOwner();
        }

        return in_class($entity, $collection) || $collection instanceof ArrayCollection;
    }

    public function getDeclaringEntity(null|string|object $entityOrClassOrMetadata, $fieldPath)
    {
        $fieldMapping = $this->getFieldMapping($entityOrClassOrMetadata, $fieldPath);
        if(array_key_exists("declared", $fieldMapping))
            return $fieldMapping["declared"];

        if( ($dot = strpos($fieldPath, ".")) > 0 ) {

            $fieldPath = trim(substr($fieldPath, 0, $dot));
            $fieldPath = $this->getFieldName($entityOrClassOrMetadata, $fieldPath) ?? $fieldPath;

            $entityOrClassOrMetadata = $this->getTargetClass($entityOrClassOrMetadata, $fieldPath);

            return $entityOrClassOrMetadata;
        }

        return $this->getClassMetadata($entityOrClassOrMetadata)?->getName();
    }

    public function getTargetClass(null|string|object $entityOrClassOrMetadata, $fieldName)
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

    public function getTypeOfField(null|string|object $entityOrClassOrMetadata, string $property)
    {
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        if( ($dot = strpos($property, ".")) > 0 ) {

            $field    = trim(substr($property, 0, $dot));
            $field = $this->getFieldName($entityOrClassOrMetadata, $field) ?? $field;

            $property = trim(substr($property,    $dot+1));

            if(!$classMetadata->hasAssociation($field))
                throw new \Exception("No association found for field \"$field\" in \"".get_class($entityOrClassOrMetadata)."\"");

            $entityOrClassOrMetadata = $this->getTypeOfField($entityOrClassOrMetadata, $field);
            if ($entityOrClassOrMetadata instanceof ArrayCollection)
                $entityOrClassOrMetadata = $entityOrClassOrMetadata->first();
            else if(is_array($entityOrClassOrMetadata))
                $entityOrClassOrMetadata = current($entityOrClassOrMetadata) ?? null;

            return $this->getTypeOfField($entityOrClassOrMetadata, $property);
        }

        return ($classMetadata->hasField($property) ? $classMetadata->getTypeOfField($property) : null);
    }

    public function getTypeOfAssociation(null|string|object $entityOrClassOrMetadata, string $property)
    {
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        if( ($dot = strpos($property, ".")) > 0 ) {

            $field    = trim(substr($property, 0, $dot));
            $field = $this->getFieldName($entityOrClassOrMetadata, $field) ?? $field;

            $property = trim(substr($property,    $dot+1));

            if(!$classMetadata->hasAssociation($field))
                throw new \Exception("No association found for field \"$field\" in \"".get_class($entityOrClassOrMetadata)."\"");

            $entityOrClassOrMetadata = $this->getTypeOfAssociation($entityOrClassOrMetadata, $field);
            if ($entityOrClassOrMetadata instanceof ArrayCollection)
                $entityOrClassOrMetadata = $entityOrClassOrMetadata->first();
            else if(is_array($entityOrClassOrMetadata))
                $entityOrClassOrMetadata = current($entityOrClassOrMetadata) ?? null;

            return $this->getTypeOfAssociation($entityOrClassOrMetadata, $property);
        }

        return ($classMetadata->hasAssociation($property) ? $classMetadata->getAssociationMapping($property)["type"] ?? null : null);
    }

    public function fetchEntityName(string $entityName, array|string $fieldPath): ?string { return $this->fetchEntityMapping($entityName, $fieldPath)["targetEntity"] ?? null; }
    public function fetchEntityMapping(string $entityName, array|string $fieldPath): ?array
    {
        $fieldPath = is_array($fieldPath) ? $fieldPath : explode(".", $fieldPath);
        $fieldName = head($fieldPath);
        $classMetadata = $this->doctrine->getManagerForClass($entityName)->getClassMetadata($entityName);

        if ($classMetadata->hasAssociation($this->getFieldName($classMetadata, $fieldName)))
            $entityMapping = $classMetadata->associationMappings[$this->getFieldName($classMetadata, $fieldName)];
        else if ($classMetadata->hasField($this->getFieldName($classMetadata, $fieldName)))
            $entityMapping = $classMetadata->fieldMappings[$this->getFieldName($classMetadata, $fieldName)];
        else return null;

        $fieldName = $fieldPath ? head($fieldPath) : $fieldName;
        $fieldPath = tail($fieldPath, $this->isToManySide($entityName, $fieldName) ? -2 : -1);
        if(!$fieldPath) return $entityMapping;

        if(array_key_exists("targetEntity", $entityMapping))
            return $this->fetchEntityMapping($entityMapping["targetEntity"], implode(".", $fieldPath));

        return null; // Fallback, invalid path provided
    }

    public function isAlias(null|object|string $entityOrClassOrMetadata, array|string $fieldPath): bool { return $this->getFieldName($entityOrClassOrMetadata, $fieldPath) != $fieldPath; }

    public function getBackFieldName(null|object|string $entityOrClassOrMetadata, array|string $fieldName): ?string
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata->hasAssociation($fieldName)) return null;

        $fieldName = $this->getFieldName($entityOrClassOrMetadata, $fieldName) ?? $fieldName;
        return $classMetadata->associationMappings[$fieldName]["inversedBy"] ?? null;
    }

    public function getFieldNameMappedBy(null|object|string $entityOrClassOrMetadata, string $mappedBy): ?string
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return null;

        foreach($classMetadata->associationMappings as $fieldName => $associationMapping)
            if(($associationMapping["mappedBy"] ?? null) == $mappedBy) return $fieldName;

        return null;
    }

    public function getFieldNameInversedBy(null|object|string $entityOrClassOrMetadata, string $inversedBy): ?string
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return null;

        foreach($classMetadata->associationMappings as $fieldName => $associationMapping)
            if($associationMapping["inversedBy"] ?? null == $inversedBy) return $fieldName;

        return null;
    }

    public function getFieldName(null|object|string $entityOrClassOrMetadata, array|string $fieldName): ?string
    {
        return $this->getFieldNames($entityOrClassOrMetadata)[$fieldName] ?? null;
    }

    public function getFieldNames(null|object|string $entityOrClassOrMetadata): ?array
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return null;

        $associationFields = array_keys($classMetadata->associationMappings);
        $classMetadataEnhanced = $this->getClassMetadataCompletor($entityOrClassOrMetadata);
        return array_merge($classMetadataEnhanced->aliasNames ?? [], $classMetadata->fieldNames, array_combine($associationFields,$associationFields));
    }

    public function resolveFieldPath(null|object|string $entityOrClassOrMetadata, array|string $fieldPath): ?string
    {
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        $entityName = $classMetadata->getName();
        $fieldPath = is_array($fieldPath) ? $fieldPath : explode(".", $fieldPath);
        $fieldName = head($fieldPath);

        $classMetadata = $this->getClassMetadata($entityName);
        $columnNames = $this->getFieldNames($classMetadata);

        while(!array_key_exists($fieldName, $columnNames)) {

            if(!get_parent_class($classMetadata->getName())) break;

            $classMetadata = $this->getClassMetadata(get_parent_class($classMetadata->getName()));
            $columnNames = $this->getFieldNames($classMetadata);
        }

        $fieldName = $columnNames[$fieldName] ?? $fieldName;
        if(!$fieldName) return null;

        $fieldPath = tail($fieldPath, $this->isToManySide($entityName, $fieldName) ? -2 : -1);
        if(!$fieldPath) return $fieldName;

        $filePath = $this->hasAssociation($entityName, $fieldName) ? $this->resolveFieldPath($this->getAssociationTargetClass($entityName, $fieldName), $fieldPath) : null;
        if(!$filePath) return null;

        return $fieldName.".".$filePath;
    }

    public function getAssociationTargetClass(null|string|object $entityOrClassOrMetadata, string $fieldName): string
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        $fieldName = $this->getFieldName($classMetadata, $fieldName) ?? $fieldName;
        return $classMetadata->getAssociationTargetClass($fieldName);
    }

    public function hasAssociation(null|string|object $entityOrClassOrMetadata, ?string $fieldName)
    {
        if($fieldName === null) return false;

        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        $fieldName = $this->getFieldName($classMetadata, $fieldName) ?? $fieldName;
        return $classMetadata->hasAssociation($fieldName);
    }

    public function hasRecursiveAssociation(null|string|object $entityOrClassOrMetadata, ?string $fieldName)
    {
        if($fieldName === null) return false;
        if($this->hasAssociation($entityOrClassOrMetadata, $fieldName)) {

            $associationMapping = $this->getAssociationMapping($entityOrClassOrMetadata, $fieldName);
            return $associationMapping["targetEntity"] == $associationMapping["sourceEntity"];
        }

        return false;
    }

    public function getFieldMapping(null|string|object $entityOrClassOrMetadata, string $fieldName): ?array
    {
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        return $this->fetchEntityMapping($classMetadata->getName(), $fieldName);
    }

    public function getAssociationMapping(null|string|object $entityOrClassOrMetadata, string $fieldName): ?array
    {
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        $fieldName = $this->getFieldName($classMetadata, $fieldName) ?? $fieldName;
        return $classMetadata->getAssociationMapping($fieldName) ?? null;
    }

    public function getUniqueKeys(null|string|object $entityOrClassOrMetadata, bool $inherits = false):array
    {
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        $uniqueKeys = [];

        do {

            foreach(($classMetadata->fieldNames) as $fieldName)
                if($this->getMapping($entityOrClassOrMetadata, $fieldName)["unique"] ?? false) $uniqueKeys[] = $fieldName;

            $parentName = get_parent_class($classMetadata->getName());
            $classMetadata = $parentName ? $this->getClassMetadata($parentName) : null;

        } while($classMetadata && $classMetadata->getName() && $inherits);

        return $uniqueKeys;
    }

    public function hasTranslation(null|string|object $entityOrClassOrMetadata): bool { return $this->hasAssociation($entityOrClassOrMetadata, "translations"); }
    public function getTranslationMapping(null|string|object $entityOrClassOrMetadata, string $fieldName): ?array
    {
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        if ($this->hasAssociation($entityOrClassOrMetadata, "translations"))
            return $this->getMapping($classMetadata->getAssociationMapping("translations")["targetEntity"], $fieldName);
    }

    public function getMapping(null|string|object $entityOrClassOrMetadata, string $fieldName): ?array
    {
        if($this->hasAssociation($entityOrClassOrMetadata, $fieldName))
            return $this->getAssociationMapping($entityOrClassOrMetadata, $fieldName);
        else if($this->hasField($entityOrClassOrMetadata, $fieldName))
            return $this->getFieldMapping($entityOrClassOrMetadata, $fieldName);
        else if($this->hasTranslation($entityOrClassOrMetadata))
            return $this->getTranslationMapping($entityOrClassOrMetadata, $fieldName);

        return null;
    }

    public function getAssociationMappings(null|string|object $entityOrClassOrMetadata): array
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        return $classMetadata->getAssociationMappings();
    }

    public function isOwningSide(null|string|object $entityOrClassOrMetadata, string $fieldName):bool
    {
        if(!$this->hasAssociation($entityOrClassOrMetadata, $fieldName)) return false;
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        $fieldName = $this->getFieldName($classMetadata, $fieldName) ?? $fieldName;
        return !$classMetadata->isAssociationInverseSide($fieldName);
    }

    public function isInverseSide(null|string|object $entityOrClassOrMetadata, string $fieldName):bool
    {
        if(!$this->hasAssociation($entityOrClassOrMetadata, $fieldName)) return false;
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        $fieldName = $this->getFieldName($classMetadata, $fieldName) ?? $fieldName;
        return $classMetadata->isAssociationInverseSide($fieldName);
    }

    public function isToOneSide       (null|string|object $entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isToManySide      (null|string|object $entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isManyToSide      (null|string|object $entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::MANY_TO_ONE, ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToSide       (null|string|object $entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::ONE_TO_ONE], true); }
    public function isManyToMany      (null|string|object $entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::MANY_TO_MANY], true); }
    public function isOneToMany       (null|string|object $entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_MANY], true); }
    public function isManyToOne       (null|string|object $entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::MANY_TO_ONE], true); }
    public function isOneToOne        (null|string|object $entityOrClassOrMetadata, string $fieldName):bool { return \in_array($this->getAssociationType($entityOrClassOrMetadata, $fieldName), [ClassMetadataInfo::ONE_TO_ONE], true); }
    public function getAssociationType(null|string|object $entityOrClassOrMetadata, string $fieldName)
    {
        $classMetadata  = $this->getClassMetadata($entityOrClassOrMetadata);
        if(!$classMetadata) return false;

        if(!$this->hasAssociation($classMetadata, $fieldName)) return false;

        $fieldName = $this->getFieldName($classMetadata, $fieldName) ?? $fieldName;
        return $classMetadata->getAssociationMapping($fieldName)['type'] ?? 0;
    }

    public function getAllClassNames() 
    { 
        $classMetadataFactory = $this->entityManager->getMetadataFactory();
        if($classMetadataFactory instanceof ClassMetadataFactory)
            return $classMetadataFactory->getAllClassNames();
        
        return [];
    }

    protected static array $completors = [];
    protected function getCompletorFor(object|string $className)
    {
        $className = is_object($className) ? get_class($className) : $className;
        if ( array_key_exists($className, self::$completors) )
            return self::$completors[$className];

        self::$completors[$className] = new ClassMetadataCompletor($className, []);
        return self::$completors[$className];
    }


    public function getClassMetadataCompletor(null|string|object $entityOrClassOrMetadata) : ?ClassMetadataCompletor
    {
        $classMetadata = $this->getClassMetadata($entityOrClassOrMetadata);
        return $classMetadata ? $this->getCompletorFor($classMetadata->name) : null;
    }

    public function warmUp(string $cacheDir): bool
    {
        self::$completors = $this->getCache("/Completors", function() {

            foreach($this->getAllClassNames() as $className)
                $this->getCompletorFor($className);
            
            return self::$completors;
        });
        
        return true;
    }
    
    public function saveCompletors()
    {
        $this->setCache("/Completors", self::$completors, null,true);
    }
}
