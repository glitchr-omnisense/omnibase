<?php

namespace Base\Field\Configurator;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Field\DiscriminatorField;
use Base\Field\SelectField;
use Base\Field\Type\SelectType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class DiscriminatorConfigurator implements FieldConfiguratorInterface
{
    public function __construct(ClassMetadataManipulator $classMetadataManipulator, TranslatorInterface $translator, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->translator = $translator;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return DiscriminatorField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $showFirst = $field->getCustomOption(SelectField::OPTION_SHOW_FIRST);
    }
}
