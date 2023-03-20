<?php

namespace Base\Field\Configurator;

use App\Controller\Backend\Crud\UserCrudController;
use Base\Field\RoleField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

class RoleConfigurator extends SelectConfigurator
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return RoleField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        parent::configure($field, $entityDto, $context);
        $multiple = is_array($field->getValue());

        $formattedValues = $field->getFormattedValue();
        $formattedValues = $multiple ? $formattedValues : [$formattedValues];
        foreach ($formattedValues as $key => $formattedValue) {
            $role = $formattedValue["id"] ?? null;
            if (!$role) {
                continue;
            }

            $url = $this->adminUrlGenerator
                        ->unsetAll()
                        ->setController(UserCrudController::class)
                        ->setAction(Action::INDEX)
                        ->set("filters[roles][comparison]", "like")
                        ->set("filters[roles][value]", $role)
                        ->generateUrl();

            $formattedValues[$key] = $formattedValue;
            $formattedValues[$key]["url"] = $url;
        }

        $field->setFormattedValue($multiple ? $formattedValues : $formattedValues[0]);
    }
}
