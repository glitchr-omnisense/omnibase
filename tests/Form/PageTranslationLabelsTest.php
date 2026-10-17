<?php

namespace Tests\Base\Form;

use Base\Admin\Form\FieldFormBuilder;
use Base\Controller\Backoffice\Crud\Layout\Widget\PageCrudController;
use Base\Entity\Layout\Widget\Page;
use Base\Field\FieldDescriptor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The page screen of a French back office is in French: its fields name no
 * label, so the theme shows their names humanised ("Title", "Headline",
 * "Excerpt", "Content", "Slug") and translates them in the field's domain,
 * "fields", where they are not - the back office's form ("forms",
 * FieldFormBuilder) has them. A field without a label now takes its form's
 * domain when its humanised name is found there, the fields of each
 * translation (TranslationType, two forms below) included.
 */
class PageTranslationLabelsTest extends KernelTestCase
{
    private const ENGLISH = ['Slug', 'Title', 'Headline', 'Excerpt', 'Content'];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists(FieldFormBuilder::class)) {
            self::markTestSkipped('Requires the host application kernel and omnibase/admin (the omnibase harness).');
        }

        self::bootKernel();
    }

    /** @return list<string> the texts of the form's labels, as the back office prints them */
    private function labels(string $locale): array
    {
        $container = static::getContainer();
        $request = Request::create('/admin/pages/new');
        $request->setLocale($locale);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);
        $container->get('translator')->setLocale($locale);

        // configureFields() needs none of the controller's services.
        $crud = (new \ReflectionClass(PageCrudController::class))->newInstanceWithoutConstructor();
        $form = (new FieldFormBuilder($container->get('form.factory'), $container->get('doctrine')))
            ->createForm(new Page(), $crud->configureFields(FieldDescriptor::PAGE_NEW), formOptions: ['csrf_protection' => false]);

        $html = $container->get('twig')->createTemplate(
            "{% form_theme form with ['@Admin/crud/form_theme.html.twig'] %}".
            "<div class=\"form-grid\">{% for child in form %}{% include '@Admin/crud/_form_child.html.twig' with {child: child} only %}{% endfor %}</div>"
        )->render(['form' => $form->createView()]);

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $labels = [];
        foreach ((new \DOMXPath($document))->query('//label') as $label) {
            $text = trim(preg_replace('/\s+/u', ' ', $label->textContent), " \u{a0}");
            if ('' !== $text) {
                $labels[] = $text;
            }
        }

        return $labels;
    }

    public function testAFrenchPageFormHasNoEnglishLabel(): void
    {
        $labels = $this->labels('fr');

        $this->assertSame([], array_values(array_intersect($labels, self::ENGLISH)), 'shown in English in a French back office');
        foreach (['Adresse', 'Titre', 'Sous-titre', 'Extrait', 'Contenu'] as $french) {
            $this->assertContains($french, $labels);
        }
        $this->assertGreaterThan(1, \count(array_keys($labels, 'Titre')), 'the title of each language');
    }

    public function testAnEnglishPageFormIsInEnglish(): void
    {
        $labels = $this->labels('en');

        foreach (self::ENGLISH as $english) {
            $this->assertContains($english, $labels);
        }
    }
}
