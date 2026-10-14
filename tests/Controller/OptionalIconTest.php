<?php

namespace Tests\Base\Controller;

use Base\Controller\Backoffice\Crud\Layout\Attribute\AdapterCrudController;
use Base\Controller\Backoffice\Crud\Thread\TagCrudController;
use Base\Controller\Backoffice\Crud\Thread\TaxonCrudController;
use Base\Controller\Backoffice\Crud\User\PermissionCrudController;
use Base\Field\FieldDescriptor;
use PHPUnit\Framework\TestCase;

/**
 * A taxon, a tag, a permission, an attribute adapter need no icon (the
 * column is nullable): the screen marked it required, so a taxon of the
 * fixtures - omnibase/classroom's
 * levels and subjects, a blog's topics, none with an icon - could not be
 * saved again as it was ("This value should not be blank" on Icon).
 */
class OptionalIconTest extends TestCase
{
    public function testTheIconMayBeLeftEmpty(): void
    {
        foreach ([TaxonCrudController::class, TagCrudController::class, PermissionCrudController::class, AdapterCrudController::class] as $controller) {
            foreach ([FieldDescriptor::PAGE_NEW, FieldDescriptor::PAGE_EDIT] as $page) {
                // configureFields() needs none of the controller's services.
                $crud = (new \ReflectionClass($controller))->newInstanceWithoutConstructor();
                $icon = null;
                foreach ($crud->configureFields($page) as $field) {
                    if ('icon' === $field->getAsDto()->getProperty()) {
                        $icon = $field->getAsDto();
                    }
                }
                $this->assertNotNull($icon, $controller.' has an icon field');
                $this->assertFalse($icon->isRequired(), $controller.' '.$page.': the icon may be left empty');
            }
        }
    }
}
