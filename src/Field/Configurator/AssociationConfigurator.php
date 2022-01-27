<?php

namespace Base\Field\Configurator;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Field\AssociationField;
use Doctrine\Common\Collections\Collection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

class AssociationConfigurator implements FieldConfiguratorInterface
{
    private $entityFactory;
    private $adminUrlGenerator;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator, EntityFactory $entityFactory, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->entityFactory = $entityFactory;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return AssociationField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyName = $field->getProperty();

        if (!$this->classMetadataManipulator->hasAssociation($entityDto->getFqcn(), $propertyName)) {
            throw new \RuntimeException(sprintf('The "%s" field is not a Doctrine association, so it cannot be used as an association field.', $propertyName));
        }
        
        $targetEntity = $this->classMetadataManipulator->getAssociationMapping($entityDto->getFqcn(), $propertyName)["targetEntity"] ?? null;
        if ($field->getFormTypeOption("class") == null)
            $field->setFormTypeOption("class", $targetEntity);

        $field->setFormTypeOptionIfNotSet('allow_add', $field->getCustomOptions()->get(AssociationField::OPTION_ALLOW_ADD));
        $field->setFormTypeOptionIfNotSet('allow_delete', $field->getCustomOptions()->get(AssociationField::OPTION_ALLOW_DELETE));
        $field->setFormattedValue($field->getValue());

        $href = [$field->getFormTypeOption("class") => AbstractCrudController::getCrudControllerFqcn($field->getFormTypeOption("class"))];
        
        $fieldValue = $field->getValue();
        $classList = $fieldValue instanceof Collection ? array_unique($fieldValue->map(fn($e) => get_class($e))->toArray()) : [get_class($fieldValue)];
        foreach($classList as $classname) {
        
            $crudController = AbstractCrudController::getCrudControllerFqcn($classname);

            $href[$classname] = $crudController ?
                $this->adminUrlGenerator
                            ->unsetAll()
                            ->setController($crudController)
                            ->setAction(Action::EDIT)
                            ->setEntityId("{0}")
                            ->generateUrl() : null;
        }
        $field->setFormTypeOption("href", $href);

        if ($this->classMetadataManipulator->isToOneSide($entityDto->getFqcn(), $propertyName)) {
            $this->configureToOneAssociation($field);
        }

        if ($this->classMetadataManipulator->isToManySide($entityDto->getFqcn(), $propertyName)) {
            $this->configureToManyAssociation($field);
        }
    }

    private function countNumElements($collection): int
    {
        if (null === $collection) {
            return 0;
        }

        if (is_countable($collection)) {
            return \count($collection);
        }

        if ($collection instanceof \Traversable) {
            return iterator_count($collection);
        }

        return 0;
    }

    private function formatAsString($entityInstance, EntityDto $entityDto): ?string
    {
        if (null === $entityInstance) {
            return null;
        }

        if (method_exists($entityInstance, '__toString')) {
            return (string) $entityInstance;
        }

        if (null !== $primaryKeyValue = $entityDto->getPrimaryKeyValue()) {
            return sprintf('%s #%s', $entityDto->getName(), $primaryKeyValue);
        }

        return $entityDto->getName();
    }

    private function generateLinkToAssociatedEntity(?string $crudController, EntityDto $entityDto): ?string
    {
        if (null === $crudController) {
            return null;
        }

        // TODO: check if user has permission to see the related entity
        return $this->adminUrlGenerator
            ->setController($crudController)
            ->setAction(Action::DETAIL)
            ->setEntityId($entityDto->getPrimaryKeyValue())
            ->unset(EA::MENU_INDEX)
            ->unset(EA::SUBMENU_INDEX)
            ->includeReferrer()
            ->generateUrl();
    }

    private function configureToOneAssociation(FieldDto $field): void
    {
        $field->setCustomOption(AssociationField::OPTION_DOCTRINE_ASSOCIATION_TYPE, 'toOne');

        $targetEntityFqcn = $field->getDoctrineMetadata()->get('targetEntity');
        $targetCrudControllerFqcn = $field->getCustomOption(AssociationField::OPTION_CRUD_CONTROLLER);

        $targetEntityDto = null === $field->getValue()
            ? $this->entityFactory->create($targetEntityFqcn)
            : $this->entityFactory->createForEntityInstance($field->getValue());
        $field->setFormTypeOptionIfNotSet('class', $targetEntityDto->getFqcn());

        $field->setFormTypeOptionIfNotSet('empty_data', null);

        $field->setCustomOption(AssociationField::OPTION_RELATED_URL, $this->generateLinkToAssociatedEntity($targetCrudControllerFqcn, $targetEntityDto));

        $field->setFormattedValue($this->formatAsString($field->getValue(), $targetEntityDto));
    }

    private function configureToManyAssociation(FieldDto $field): void
    {
        $field->setCustomOption(AssociationField::OPTION_DOCTRINE_ASSOCIATION_TYPE, 'toMany');

        // associations different from *-to-one cannot be sorted
        $field->setSortable(false);
        
        $field->setFormTypeOptionIfNotSet('multiple', true);
        $field->setFormTypeOptionIfNotSet('empty_data', []);

        /* @var PersistentCollection $collection */
        $field->setFormTypeOptionIfNotSet('class', $field->getDoctrineMetadata()->get('targetEntity'));

        if (null === $field->getTextAlign()) {
            $field->setTextAlign(TextAlign::RIGHT);
        }

        $showFirst = $field->getCustomOption("showFirst");
        if($field->getValue()) {

            $classFilter = $field->getFormTypeOption('class');
            $others = $field->getValue()->filter(function($value) use ($classFilter) {
                return is_a($value, $classFilter) || is_subclass_of($value, $classFilter);
            })->toArray();

            $first  = ($showFirst) ? array_shift($others) : $others[0] ?? null;
            if ($first) {

                $targetEntityFqcn = $field->getDoctrineMetadata()->get('targetEntity');
                $targetEntityDto = null === $first
                    ? $this->entityFactory->create($targetEntityFqcn)
                    : $this->entityFactory->createForEntityInstance($first);

                $targetEntityDto = $this->entityFactory->createForEntityInstance($first);
                $targetCrudControllerFqcn = $field->getCustomOption(AssociationField::OPTION_CRUD_CONTROLLER) ?? AbstractCrudController::getCrudControllerFqcn($targetEntityDto->getFqcn());

                $field->setCustomOption(AssociationField::OPTION_RELATED_URL, $this->generateLinkToAssociatedEntity($targetCrudControllerFqcn, $targetEntityDto));
            }

            $count = $this->countNumElements($others);
            if($first && $showFirst) $count++;

            if($first != null || !empty($others))  {
         
                $field->setFormattedValue([
                    "count" => $count,
                    "first" => $first,
                    "others" => $others
                ]);
            }
        }
    }
}
