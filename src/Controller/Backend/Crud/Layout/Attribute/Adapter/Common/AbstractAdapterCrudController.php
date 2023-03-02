<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute\Adapter\Common;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\DiscriminatorField;
use Base\Field\IconField;
use Base\Field\SlugField;
use Base\Field\TranslationField;

use EasyCorp\Bundle\EasyAdminBundle\Collection\ActionCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;

class AbstractAdapterCrudController extends AbstractCrudController
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

        }, $args);
    }
}
