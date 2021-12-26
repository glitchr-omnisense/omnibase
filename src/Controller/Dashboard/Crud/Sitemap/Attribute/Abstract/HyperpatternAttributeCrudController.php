<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Attribute\Abstract;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Field\AssociationField;

class HyperpatternAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
    
        return parent::configureFields($pageName, array_merge($callbacks, [
            "id" => function () use ($defaultCallback) {
                yield TextField::new('pattern')->onlyOnForms()->setColumns(12);
            },
            "translations" => function () use ($defaultCallback) {
                
                // yield AssociationField::new('tags')
                //         ->showFirst()
                //         /*->setFields([
                //             "translations" => ["form_type" => TranslationType::class],
                //             // "slug" => ["form_type" => SlugType::class, "target" => "translations.name"],                            
                //         ])*/;

                // yield AssociationField::new("hyperlinks")->renderAsCount()/*->hideOnForm()*/;

                yield TextField::new('pattern')->hideOnForm();
                foreach ( ($callbacks["pattern"] ?? $defaultCallback)() as $yield)
                    yield $yield;
            }
        ]));
    }
}
