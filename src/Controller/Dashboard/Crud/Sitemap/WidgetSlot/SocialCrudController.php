<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\WidgetSlot;

use Base\Field\FontAwesomeField;
use Base\Field\LinkIdField;
use Base\Field\SlugField;
use Base\Field\TranslatableField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\AbstractCrudController;

class SocialCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-share-alt"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield TextField::new("urlPattern")->hideOnIndex();
        foreach ( ($callbacks["urlPattern"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield FontAwesomeField::new('icon');
        foreach ( ($callbacks["icon"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield SlugField::new('socialName')->setTargetFieldName("translations.label");
        foreach ( ($callbacks["socialName"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslatableField::new("label");
    }
}