<?php

namespace Base\Field\Configurator;

use Base\Field\IconField;
use Base\Field\Type\IconType;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Component\PropertyAccess\PropertyAccess;

class IconConfigurator extends SelectConfigurator
{
    protected $iconProvider;
    public function __construct(...$args)
    {
        $this->iconProvider = array_pop($args);
        parent::__construct(...$args);
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return IconField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $icon = null;
        if( null !== $field->getCustomOption(IconField::OPTION_TARGET_FIELD_NAME))
            $icon = $propertyAccessor->getValue($entityDto->getInstance(), $field->getCustomOption(IconField::OPTION_TARGET_FIELD_NAME));

        $provider = $field->getFormTypeOption("provider") ?? $this->baseService->getParameterBag("base.icon_provider.default_provider");
        $iconProvider = $this->iconProvider->getProvider($provider);

        foreach($iconProvider->getAssets() as $asset) {

            $relationship = pathinfo_relationship($asset);
            $location = $relationship == "javascript" ? "javascripts" : "stylesheets";
            $this->baseService->addHtmlContent($location, $asset);
        }
        
        $field->setCustomOption("iconColor", $icon);
    }
}
