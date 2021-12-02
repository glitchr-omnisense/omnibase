<?php

namespace Base\Field\Configurator;

use Base\Field\EntityField;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use function Symfony\Component\String\u;

final class EntityConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return EntityField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $field->setFormTypeOptionIfNotSet('allow_add', $field->getCustomOptions()->get(EntityField::OPTION_ALLOW_ADD));
        $field->setFormTypeOptionIfNotSet('allow_delete', $field->getCustomOptions()->get(EntityField::OPTION_ALLOW_DELETE));

        if($field->getCustomOptions()->get(EntityField::OPTION_RENDER_FORMAT) == "select2")
            $field->setFormTypeOptionIfNotSet('select2', true);
        elseif($field->getCustomOptions()->get(EntityField::OPTION_RENDER_FORMAT) == "dropzone")
            $field->setFormTypeOptionIfNotSet('dropzone', true);

        $field->setFormattedValue($this->formatCollection($field, $context));
    }

    private function formatCollection(FieldDto $field, AdminContext $context)
    {
        return $this->countNumElements($field->getValue());
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
}
