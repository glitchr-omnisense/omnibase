<?php

namespace Base\Field\Configurator;

use Base\Entity\User;
use Base\Field\IdField;
use Base\Routing\RouterInterface;
use Base\Security\LoginRestrictionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function Symfony\Component\String\u;

class IdConfigurator implements FieldConfiguratorInterface
{
    /**
     * @var AuthorizationCheckerInterface
     */
    private AuthorizationCheckerInterface $authorizationChecker;

    /**
     * @var AdminUrlGenerator
     */
    private AdminUrlGenerator $adminUrlGenerator;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, AdminUrlGenerator $adminUrlGenerator, RouterInterface $router)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->router = $router;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return IdField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $maxLength = $field->getCustomOption(IdField::OPTION_MAX_LENGTH);
        if (null === $maxLength) {
            $maxLength = Crud::PAGE_INDEX === $context->getCrud()->getCurrentPage() ? 7 : -1;
        }

        // Check access rights and context to impersonate
        $switchRole = $this->router->getRouteFirewall()->getSwitchUser()["role"] ?? null;
        $switchParameter = $this->router->getRouteFirewall()->getSwitchUser()["parameter"] ?? "_switch_user";

        $field->setCustomOption(IdField::OPTION_IMPERSONATE, $switchParameter);
        if (!$entityDto->getInstance() instanceof User || $entityDto->getInstance() instanceof LoginRestrictionInterface || !$switchRole || !$this->authorizationChecker->isGranted($switchRole)) {
            $field->setCustomOption(IdField::OPTION_IMPERSONATE, false);
        }

        // Formatted data
        $field->setValue($entityDto->getInstance());
        $accessor = PropertyAccess::createPropertyAccessor();

        if ($field->getValue() === null) {
            return;
        }

        $value = $accessor->isReadable($field->getValue(), $field->getProperty())
            ? $accessor->getValue($field->getValue(), $field->getProperty()) : null;

        $hashtag = gettype($value) == "integer" ? "#" : "";
        $value = $hashtag . ($maxLength !== -1 ? u($value)->truncate($maxLength, '…')->toString() : $value);

        $url = null;
        if (Crud::PAGE_DETAIL !== $context->getCrud()->getCurrentPage() &&
            $field->getCustomOption(IdField::OPTION_ADD_LINK) && $entityDto->getInstance()) {
            $url = $this->adminUrlGenerator
                ->setAction('detail')
                ->setEntityId($entityDto->getInstance()->getId())
                ->generateUrl();
        }

        $field->setFormattedValue(($url ? "<a href='" . $url . "'>" . $value . "</a>" : $value));
    }
}
