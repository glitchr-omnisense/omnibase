<?php

namespace Tests\Base\Controller;

use Base\Admin\Form\FieldFormBuilder;
use Base\Controller\Backoffice\Crud\Layout\Attribute\AdapterCrudController;
use Base\Controller\Backoffice\Crud\Thread\TaxonCrudController;
use Base\Entity\Layout\Attribute\Adapter\TextAdapter;
use Base\Entity\Thread\Taxon;
use Base\Field\FieldDescriptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * A taxon - a level, a subject, a section of a FAQ, a category of products -
 * is made with the label of the site's language alone: the screen asked the
 * label in every language ('required' => true on each translation), so none
 * could be saved with one. The slug follows that label; a taxon without any
 * label is refused. An attribute adapter's label is asked the same way.
 */
class TaxonLabelTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists(FieldFormBuilder::class)) {
            self::markTestSkipped('Requires a host application with omnibase/admin (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    public function testOnlyTheLabelOfTheSiteLanguageIsAsked(): void
    {
        foreach ([TaxonCrudController::class => new Taxon(), AdapterCrudController::class => new TextAdapter()] as $controller => $entity) {
            $view = $this->form($controller, $entity)->createView();
            $default = $view['translations']->vars['default_locale'];
            $locales = array_keys($view['translations']->children);
            self::assertContains($default, $locales);
            self::assertGreaterThan(1, \count($locales), 'the harness has more than one language');

            foreach ($view['translations'] as $locale => $translation) {
                self::assertSame($locale === $default, $translation['label']->vars['required'], $controller.': the label in '.$locale);
            }
        }
    }

    public function testATaxonIsMadeWithTheLabelOfTheSiteLanguage(): void
    {
        $taxon = new Taxon();
        $form = $this->form(TaxonCrudController::class, $taxon);
        $view = $form->createView();
        $default = $view['translations']->vars['default_locale'];
        self::assertSame(['translations', 'label'], $view['slug']->vars['target'], 'the slug follows the label (of the default language, SlugType::finishView())');

        $label = 'Cours élémentaire '.bin2hex(random_bytes(3));
        $translations = [];
        foreach (array_keys($view['translations']->children) as $locale) {
            $translations[$locale] = ['label' => $locale === $default ? $label : '', 'description' => ''];
        }
        $form->submit(['translations' => $translations, 'slug' => '']);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame([$default], array_keys($taxon->getTranslations()->toArray()), 'an empty translation is dropped');

        $this->em()->persist($taxon);
        $this->em()->flush();
        try {
            $this->em()->clear();
            $saved = $this->em()->find(Taxon::class, $taxon->getId());
            self::assertNotNull($saved);
            self::assertSame($label, $saved->getTranslations()->get($default)?->getLabel());
            self::assertStringStartsWith('cours-elementaire-', (string) $saved->getSlug(), 'the slug is made from the label');
        } finally {
            $this->em()->remove($this->em()->find(Taxon::class, $taxon->getId()));
            $this->em()->flush();
        }
    }

    public function testATaxonWithoutAnyLabelIsRefused(): void
    {
        $taxon = new Taxon();
        $form = $this->form(TaxonCrudController::class, $taxon);
        $translations = [];
        foreach (array_keys($form->get('translations')->all()) as $locale) {
            $translations[$locale] = ['label' => '', 'description' => ''];
        }
        $form->submit(['translations' => $translations, 'slug' => '']);

        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->isValid());
        $origins = [];
        foreach ($form->getErrors(true) as $error) {
            $origins[] = $error->getOrigin()?->getName();
        }
        self::assertSame(['translations'], $origins, 'the refusal is told on the labels');
    }

    private function form(string $controller, object $entity): FormInterface
    {
        // configureFields() needs none of the controller's services.
        $crud = (new \ReflectionClass($controller))->newInstanceWithoutConstructor();

        return (new FieldFormBuilder(static::getContainer()->get('form.factory'), static::getContainer()->get('doctrine')))
            ->createForm($entity, $crud->configureFields(FieldDescriptor::PAGE_NEW), FieldDescriptor::PAGE_NEW, ['csrf_protection' => false]);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }
}
