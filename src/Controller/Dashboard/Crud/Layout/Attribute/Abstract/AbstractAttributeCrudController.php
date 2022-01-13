<?php

namespace Base\Controller\Dashboard\Crud\Layout\Attribute\Abstract;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\ArrayField;
use Base\Field\AssociationField;
use Base\Field\DiscriminatorField;
use Base\Field\IconField;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use Base\Model\Icon\BootstrapTwitter;
use Base\Model\Icon\FontAwesome;
use EasyCorp\Bundle\EasyAdminBundle\Collection\ActionCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
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

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DiscriminatorField::new("type")->setTextAlign(TextAlign::RIGHT);

        yield IconField::new('icon')->setProvider(BootstrapTwitter::class)->setTextAlign(TextAlign::LEFT)->setColumns(6);
        foreach ( ($callbacks["icon"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield SlugField::new('code')->setColumns(6)->setTargetFieldName("translations.label");
        foreach ( ($callbacks["code"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new("label");
        yield TranslationField::new()->showOnIndex("help");
        foreach ( ($callbacks["translations"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield AssociationField::new("attributes")->justDisplay();
        foreach ( ($callbacks["attributes"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}
