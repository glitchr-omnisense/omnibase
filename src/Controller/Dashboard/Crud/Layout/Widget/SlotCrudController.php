<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Widget\Slot;
use Base\Field\SelectField;
use Base\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class SlotCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function createEntity(string $entityFqcn)
    {
        return new Slot("");
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, [
            "id" => function () use ($defaultCallback, $callbacks, $pageName) {

                yield SlugField::new('path')->setColumns(6)->setTargetFieldName("label");
                foreach ( ($callbacks["path"] ?? $defaultCallback)() as $yield)
                    yield $yield;
                
                yield TextField::new("label")->setColumns(6);
                foreach ( ($callbacks["widgets"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield SelectField::new("widgets")->setColumns(12);
                foreach ( ($callbacks["widgets"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield TextareaField::new("help")->setColumns(12);
                foreach ( ($callbacks["help"] ?? $defaultCallback)() as $yield)
                    yield $yield;
        }]);
    }
}