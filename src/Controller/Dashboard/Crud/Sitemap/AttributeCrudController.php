<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Field\TranslationField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Entity\Sitemap\Attribute;
use Base\Field\DiscriminatorField;
use Base\Field\FontAwesomeField;
use Base\Field\SlugField;

class AttributeCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, $callbacks);
    }
}