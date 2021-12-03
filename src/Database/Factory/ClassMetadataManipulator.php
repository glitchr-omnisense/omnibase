<?php

namespace Base\Database\Factory;

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
    
        return ! $this->entityManager->getMetadataFactory()->isTransient($class);
    }

    public function getClassMetadata($entity)
    {
        if($entity === null) return null;

        $className = is_object($entity) ? get_class($entity) : $entity;
        return $this->entityManager->getClassMetadata($className);
    }

    public function getFields(string $class, array $fields = [], array $excludedFields = []): array
    {
        if(!BaseService::array_is_associative($fields))
            throw new \Exception("Associative array expected for 'fields' parameter, '".gettype($fields)."' received");

        $metadata = $this->getClassMetadata($class);
        $validFields = array_fill_keys($metadata->getFieldNames(), []);

        if (!empty($associationNames = array_intersect_key($validFields, $metadata->getAssociationNames())))
            $validFields += $this->getAssociationMapping($metadata, $associationNames);

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

    public function getAssociationTargetClass(string $class, string $fieldName): string
    {
        $metadata = $this->entityManager->getClassMetadata($class);

        if (!$metadata->hasAssociation($fieldName)) {
            throw new \RuntimeException(sprintf('Unable to find the association target class of "%s" in %s.', $fieldName, $class));
        }

        return $metadata->getAssociationTargetClass($fieldName);
    }

    private function getAssociationMapping(ClassMetadata $metadata, array $associationNames): array
    {
        $fields = [];

        foreach ($associationNames as $assocName) {
            if (!$metadata->isAssociationInverseSide($assocName)) {
                continue;
            }

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

    public function getDataClass(FormInterface $form): ?string
    {
        // Simple case, data_class from current form (with ORM Proxy management)
        if (null !== $dataClass = $form->getConfig()->getDataClass()) {
            if (false === $pos = strrpos($dataClass, '\\__CG__\\')) {
                return $dataClass;
            }

            return substr($dataClass, $pos + 8);
        }

        $formInit = $form;
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

            return $this->getAssociationTargetClass($dataClass, $form->getName());
        }

        // return null;
        throw new \RuntimeException('Unable to get "data_class" in form "'.$formInit->getName().'" or any of its parents');
    }
}
