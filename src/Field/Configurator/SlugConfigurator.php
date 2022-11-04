<?php

namespace Base\Field\Configurator;

use Base\Service\BaseService;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Base\Field\SlugField;
use Base\Service\Model\LinkableInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class SlugConfigurator implements FieldConfiguratorInterface
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return SlugField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $targetFieldName = $field->getCustomOption(SlugField::OPTION_TARGET_FIELD_NAME);
        $field->setFormTypeOption('target', $targetFieldName);

        if (null !== $unlockConfirmationMessage = $field->getCustomOption(SlugField::OPTION_UNLOCK_CONFIRMATION_MESSAGE)) {
            $field->setFormTypeOption('attr.data-confirm-text', $this->translator->trans($unlockConfirmationMessage, [], $context->getI18n()->getTranslationDomain()));
        }

        $entity = $entityDto->getInstance();
        if($entity) $slug = PropertyAccess::createPropertyAccessor()->getValue($entity, $field->getProperty());
        if($entity && $slug && class_implements_interface($entityDto->getInstance(), LinkableInterface::class))
            $field->setFormattedValue(["url" => $entity->__toLink() ?? null, "slug" => $slug]);
    }
}
