<?php

namespace Base\Controller\Backoffice\Crud\Layout;


use Base\Controller\Backoffice\AbstractCrudController;
use Base\Field\CollectionField;
use Base\Field\ImageField;
use Base\Field\QuadrantField;
use Base\Field\Type\CropperType;
use Base\Field\Type\NumberType;
use Base\Field\Type\SlugType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class ImageCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 
    
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
                
            yield QuadrantField::new('quadrant')->setColumns(2);
            yield ImageField::new('source')->setColumns(10)->setCropper();

            yield CollectionField::new('crops')
                    ->showCollapsed(false)
                    ->setEntryLabel(function($i, $e) { 

                        if($e === null) return false;
                        if($i === "__prototype__") return false;

                        $id = " #".(((int) $i) + 1);

                        return $this->getTranslator()->entity($e).$id;
                    })

                    ->setEntryType(CropperType::class)
                    ->setEntryOptions([
                        "target" => "source",
                        "quadrant"  => "quadrant.wind",
                        "fields" => [
                            "label"   => ["form_type" => TextType::class, "label" => "Label", "required"  => false],
                            "slug"    => ["form_type" => SlugType::class, "label" => false, "lock" => true, "keep" => ":", "target" => ".label"]
                        ]
                    ]);
        }, $args);
    }
}
