<?php

namespace Base\Backend\Field\Configurator;

use Base\Backend\Config\Action;
use Base\Field\AvatarField;
use Base\Service\TranslatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField as EaAvatarField;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

class CommonPreConfigurator extends \EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\CommonPreConfigurator
{
    public function __construct(PropertyAccessor $propertyAccessor, TranslatorInterface $translator)
    {
        $this->translator = $translator;
        parent::__construct($propertyAccessor);
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $translationDomain = $context->getI18n()->getTranslationDomain();
        $label = $this->buildLabelOption($field, $translationDomain, $context->getCrud()->getCurrentPage(), $entityDto);
        $field->setLabel($label);

        if($this->propertyAccessor->isReadable($entityDto->getInstance(), $field->getProperty()))
            parent::configure($field, $entityDto, $context);
    }

    /**
     * @return TranslatableInterface|string|false|null
     */
    protected function buildLabelOption(FieldDto $field, string $translationDomain, ?string $currentPage, ?EntityDto $entityDto = null)
    {
        // don't autogenerate a label for these special fields (there's a dedicated configurator for them)
        if (FormField::class === $field->getFieldFqcn()) {
            $label = $field->getLabel();

            if ($label instanceof TranslatableInterface) {
                return $label;
            }

            return empty($label) ? $label : t($label, $field->getTranslationParameters(), $translationDomain);
        }

        // if an Avatar field doesn't define its label, don't autogenerate it for the 'index' page
        // (because the table of the 'index' page looks better without a header in the avatar column)
        if (Action::INDEX === $currentPage && null === $field->getLabel() && is_instanceof($field->getFieldFqcn(), [AvatarField::class, EaAvatarField::class])) {
            $field->setLabel(false);
        }

        // it field doesn't define its label explicitly, generate an automatic
        // label based on the field's field name
        if (null === $label = $field->getLabel()) {
            $label = $this->robotizeString($entityDto, $field->getProperty()) ?? $this->humanizeString($field->getProperty());
        }

        if (empty($label)) {
            return $label;
        }

        // don't translate labels in form-related pages because Symfony Forms translates
        // labels automatically and that causes false "translation is missing" errors
        if (\in_array($currentPage, [Crud::PAGE_EDIT, Crud::PAGE_NEW], true)) {
            return $label;
        }

        if ($label instanceof TranslatableInterface) {
            return $label;
        }

        return t($label, $field->getTranslationParameters(), $translationDomain);
    }

    protected function robotizeString(EntityDto $entityDto, string $property): ?string
    {
        if(!isset($entityDto)) return null;
        if(!$this->translator->transEntityExists($entityDto->getFqcn(), $property)) return null;

        return $this->translator->transEntity($entityDto->getFqcn(), $property);
    }
}
