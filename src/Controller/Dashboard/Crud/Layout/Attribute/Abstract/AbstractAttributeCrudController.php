<?php

namespace Base\Controller\Dashboard\Crud\Layout\Attribute\Abstract;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\DiscriminatorField;
use Base\Field\IconField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use Base\Model\IconProvider\BootstrapTwitter;
use EasyCorp\Bundle\EasyAdminBundle\Collection\ActionCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;

class AbstractAttributeCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureActionsWithEntityDto(ActionCollection $actions, EntityDto $entityDto): ActionCollection
    {
        foreach($actions as $action) {

            if($action->getName() !== "delete") continue;
            if( count($entityDto->getInstance()->getAttributes()) === 0) continue;
            $action->setCssClass($action->getCssClass()." hide");
        }

        return $actions;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield DiscriminatorField::new("type")->setTextAlign(TextAlign::RIGHT);
            yield IconField::new('icon')->setTextAlign(TextAlign::LEFT)->setColumns(4);
            yield SlugField::new('code')->setColumns(4)->setTargetFieldName("translations.label");

            yield TranslationField::new("label");
            yield TranslationField::new()->showOnIndex("help");
            yield AssociationField::new("attributes")->justDisplay();

        }, $args);
    }
}
