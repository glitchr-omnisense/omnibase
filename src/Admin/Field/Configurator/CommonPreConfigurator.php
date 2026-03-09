<?php

namespace Base\Admin\Field\Configurator;

use Base\Admin\Config\Action;
use Base\Field\AvatarField;
use Base\Service\TranslatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Translation\EntityTranslationIdGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField as EaAvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Generator\LabelGenerator;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

use function in_array;
use function Symfony\Component\Translation\t;

readonly class CommonPreConfigurator extends \EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\CommonPreConfigurator
{
    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    public function __construct(PropertyAccessorInterface $propertyAccessor, EntityFactory $entityFactory, EntityTranslationIdGeneratorInterface $entityTranslationIdGenerator, TranslatorInterface $translator)
    {
        $this->translator = $translator;
        parent::__construct($propertyAccessor, $entityFactory, $entityTranslationIdGenerator);
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $translationDomain = $context->getI18n()->getTranslationDomain();
        $label = $this->buildLabelOption($entityDto, $field, $translationDomain, $context->getCrud()->getCurrentPage(), $context->isUseEntityTranslations());
        $field->setLabel($label);

        if ($entityDto->getInstance() && $this->propertyAccessor->isReadable($entityDto->getInstance(), $field->getProperty())) {
            parent::configure($field, $entityDto, $context);
        }
    }

    /**
     * @param FieldDto $field
     * @param string $translationDomain
     * @param string|null $currentPage
     * @param EntityDto|null $entityDto
     * @return TranslatableInterface|string|false|null
     * @throws \Exception
     */
    protected function buildLabelOption(EntityDto $entityDto, FieldDto $field, string $translationDomain, ?string $currentPage, bool $useEntityTranslations): TranslatableInterface|string|false|null
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
            $label = $this->robotizeString($entityDto, $field->getProperty()) ?? LabelGenerator::humanize($field->getProperty());
        }

        if (empty($label)) {
            return $label;
        }

        // don't translate labels in form-related pages because Symfony Forms translates
        // labels automatically and that causes false "translation is missing" errors
        if (in_array($currentPage, [Crud::PAGE_EDIT, Crud::PAGE_NEW], true)) {
            return $label;
        }

        if ($label instanceof TranslatableInterface) {
            return $label;
        }

        return t($label, $field->getTranslationParameters(), $translationDomain);
    }

    protected function robotizeString(EntityDto $entityDto, string $property): ?string
    {
        if (!isset($entityDto)) {
            return null;
        }
        if (!$this->translator->transEntityExists($entityDto->getFqcn(), $property)) {
            return null;
        }

        return $this->translator->transEntity($entityDto->getFqcn(), $property);
    }
}
