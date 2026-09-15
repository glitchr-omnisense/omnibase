<?php

namespace Base\Controller\Backoffice\Crud\Layout\Widget;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Entity\Layout\Widget\Page;
use Base\Field\IdField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use Base\Field\Type\WysiwygType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * Layout pages served at /page/{slug} (WidgetController::Page).
 *
 * Also what makes that page render for administrators at all: its template
 * links to this CRUD through crudify(), which had no controller to resolve
 * for a Page and handed null to AdminUrlGenerator::setController().
 *
 * The content is HTML (WysiwygType), like the pages already stored.
 */
class PageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-file-lines';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('slug');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield SlugField::new('slug')->setColumns(6)->setTargetFieldName('translations.title');

        yield TranslationField::new()
            ->showOnIndex('title')
            ->setFields([
                'title' => ['required' => true],
                'headline' => [],
                'excerpt' => ['form_type' => TextareaType::class, 'required' => false],
                'content' => ['form_type' => WysiwygType::class],
            ]);
    }
}
