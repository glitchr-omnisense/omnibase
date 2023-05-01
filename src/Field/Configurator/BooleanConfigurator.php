<?php

namespace Base\Field\Configurator;

use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Field\BooleanField;
use Base\Service\BaseService;
use Base\Twig\Environment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class BooleanConfigurator implements FieldConfiguratorInterface
{
    /**
     * @var AdminUrlGenerator
     */
    private AdminUrlGenerator $adminUrlGenerator;

    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    /**
     * @var ?CsrfTokenManagerInterface
     */
    private ?CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, ?CsrfTokenManagerInterface $csrfTokenManager = null)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return BooleanField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $field->setFormTypeOption(BooleanField::OPTION_CONFIRMATION_MODAL_ON_CHECK, $field->getCustomOption(BooleanField::OPTION_CONFIRMATION_MODAL_ON_CHECK));
        $field->setFormTypeOption(BooleanField::OPTION_CONFIRMATION_MODAL_ON_UNCHECK, $field->getCustomOption(BooleanField::OPTION_CONFIRMATION_MODAL_ON_UNCHECK));

        $isRenderedAsSwitch = $field->getCustomOption(BooleanField::OPTION_RENDER_AS_SWITCH);
        $field->setFormTypeOption(BooleanField::OPTION_RENDER_AS_SWITCH, $isRenderedAsSwitch);

        if ($isRenderedAsSwitch) {
            $crudDto = $context->getCrud();

            if (null !== $crudDto && Action::NEW !== $crudDto->getCurrentAction()) {
                $toggleUrl = $this->adminUrlGenerator
                    ->setAction(Action::EDIT)
                    ->setEntityId($entityDto->getPrimaryKeyValue())
                    ->set('fieldName', $field->getProperty())
                    ->set('csrfToken', $this->csrfTokenManager?->getToken(BooleanField::CSRF_TOKEN_NAME))
                    ->generateUrl();

                $field->setCustomOption(BooleanField::OPTION_TOGGLE_URL, $toggleUrl);
            }

            $field->setFormTypeOptionIfNotSet('label_attr.class', 'checkbox-switch');
            $field->setCssClass($field->getCssClass() . ' has-switch');
        }
    }
}
