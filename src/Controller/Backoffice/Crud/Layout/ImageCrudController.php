<?php

namespace Base\Controller\Backoffice\Crud\Layout;


use Base\Controller\Backoffice\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\ImageField;
use Base\Field\Type\CropperType;
use Base\Field\Type\NumberType;
use Base\Field\Type\QuadrantType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ImageCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield ImageField::new('source')->setCropper([]);
            yield AssociationField::new('crops')->showCollapsed(false)->autoload(false)->setFields([
                "label" => [],
                "quadrant" => ["form_type" => QuadrantType::class],
                "cropper" => [
                    "form_type" => CropperType::class,
                    "target" => "source",
                    "parameters" => [
                        "x"      => ["form_type" => NumberType::class, "stepUp" => 10, "stepDown" => 10, "min" => -10],
                        "y"      => ["form_type" => NumberType::class, "stepUp" => 10, "stepDown" => 10, "min" => -10],
                        "width"  => ["form_type" => NumberType::class, "stepUp" => 10, "stepDown" => 10, "min" => -10],
                        "height" => ["form_type" => NumberType::class, "stepUp" => 10, "stepDown" => 10, "min" => -10],
                        "rotate" => [],
                        "scaleX" => [],
                        "scaleY" => []
                    ]
                ],
            ]);

        }, $args);
    }
}