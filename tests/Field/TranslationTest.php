<?php

namespace Tests\Base\Field;

use Base\Entity\Thread;
use Base\Entity\ThreadIntl;
use Base\Field\Behaviour\RecordFields;
use Base\Field\Behaviour\Translation;
use Base\Field\Kind;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormView;
use Omnibridge\Contract\Locales;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A translations field on every host (plan "omnibase agnostic", step F5d, slice 3): what omnibase's
 * TranslationType decides - the languages drawn, the parts required, each language's fields - is
 * Base\Field\Behaviour\Translation, which TranslationType asks; the translation entity's fields come from
 * its shape (RecordFields), as ClassMetadataManipulator::getFields() lists them from Doctrine. The neutral
 * FormView draws one part per language with the record's values, FormBinder writes what is sent into the
 * record's translations.
 */
final class TranslationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        // The shapes the neutral side reads, through the contract a host without Symfony's forms gives.
        Runtime::set(UnitOfWork::class, static::getContainer()->get(UnitOfWork::class));
    }

    protected function tearDown(): void
    {
        Runtime::set(UnitOfWork::class, null);
        parent::tearDown();
    }

    /** @return array{string, list<string>} the default language and the offered ones */
    private static function locales(): array
    {
        $locales = Runtime::find(Locales::class);
        self::assertInstanceOf(Locales::class, $locales);

        return [$locales->getDefaultLocale(), $locales->getAvailableLocales()];
    }

    public function testTheLanguagesDrawnAndThePartsRequiredAreTranslationTypes(): void
    {
        $options = Translation::options(['available_locales' => ['fr-FR', 'en-GB', 'de-DE'], 'default_locale' => 'fr-FR', 'required_locales' => ['en-GB', 'ja-JP']]);
        self::assertSame(['en-GB'], array_values($options['required_locales']), 'only the offered languages are required');
        self::assertSame(['fr-FR' => 'fr-FR', 'en-GB' => 'en-GB', 'de-DE' => 'de-DE'], Translation::locales($options, [])['locales']);
        self::assertTrue(Translation::entryRequired('fr-FR', 'fr-FR', $options));
        self::assertTrue(Translation::entryRequired('en-GB', 'fr-FR', $options));
        self::assertFalse(Translation::entryRequired('de-DE', 'fr-FR', $options), 'required languages named: the others are not');
        self::assertNull(Translation::entryRequired('de-DE', 'fr-FR', ['required_locales' => []] + $options));

        $single = ['single_locale' => true] + $options;
        self::assertSame(['locales' => ['fr-FR' => 'fr-FR'], 'default' => 'fr-FR'], Translation::locales($single, ['fr-FR', 'en-GB']));
        self::assertSame(['locales' => ['en-GB' => 'fr-FR'], 'default' => 'en-GB'], Translation::locales($single, ['en-GB']), 'the record\'s one language, the default\'s fields');

        $fields = Translation::perLocale(['title' => ['required' => true], 'excerpt' => ['locale_options' => ['de-DE' => ['display' => false], 'en-GB' => ['label' => 'Summary']]]], ['fr-FR', 'en-GB', 'de-DE']);
        self::assertSame(['title', 'excerpt'], array_keys($fields['fr-FR']));
        self::assertSame(['label' => 'Summary'], $fields['en-GB']['excerpt']);
        self::assertSame(['title'], array_keys($fields['de-DE']));

        $field = Translation::required(['required' => fn (bool $required, string $locale, array $options): bool => $required && 'fr-FR' === $locale], true, 'fr-FR', $options);
        self::assertTrue($field['required']);
        $this->expectException(\InvalidArgumentException::class);
        Translation::required(['required' => fn ($required): bool => true], true, 'fr-FR', $options);
    }

    public function testTheTranslationEntitysFieldsAreDoctrinesFromItsShape(): void
    {
        $manipulator = static::getContainer()->get(ClassMetadataManipulator::class);
        foreach ([[[], []], [['title' => ['required' => true], 'content' => []], ['headline']], [['keywords' => []], []]] as [$given, $excluded]) {
            $doctrine = $manipulator->getFields(ThreadIntl::class, $given, $excluded);
            $neutral = RecordFields::of(ThreadIntl::class, $given, $excluded);
            self::assertSame(array_keys($doctrine), array_keys($neutral), 'the same fields, in the same order');
            foreach ($doctrine as $name => $field) {
                $kind = isset($field['form_type']) ? RecordFields::kindOf($field['form_type']) : null;
                self::assertSame($kind, $neutral[$name]['kind'] ?? (isset($neutral[$name]['form_type']) ? RecordFields::kindOf($neutral[$name]['form_type']) : null), $name);
            }
        }
        self::assertSame(Kind::CHOICE, RecordFields::kindOf('Base\Field\Type\SelectType'));
        self::assertSame(Kind::TEXTAREA, RecordFields::kindOf('Symfony\Component\Form\Extension\Core\Type\TextareaType'));
        self::assertSame(Kind::EDITOR, RecordFields::kindOf('Some\Other\Host\EditorType'), 'read by its name where no map knows it');
        self::assertSame(Kind::CUSTOM, RecordFields::kindOf('Symfony\Component\Form\Extension\Core\Type\RangeType'));
    }

    public function testEachLanguageIsDrawnWithTheRecordsValuesAndWhatIsSentIsWritten(): void
    {
        [$default, $available] = self::locales();
        $other = current(array_diff($available, [$default]));
        if (false === $other) {
            self::markTestSkipped('One language only.');
        }
        $thread = new Thread();
        $thread->addTranslation((new ThreadIntl())->setLocale($default)->setTitle('Abbey Road'));

        $form = FormDefinition::new('thread')->setCsrf(false)->setDataClass(Thread::class)
            ->add(FormDefinition::field('translations', Kind::TRANSLATION)->setFormTypeOptions(['fields' => ['title' => ['required' => true], 'excerpt' => []], 'autoload' => false]));
        $binder = new FormBinder();
        $view = FormView::of($form, $binder->read($form, $thread));

        $translations = $view['translations'];
        self::assertSame($available, array_keys($translations->children));
        self::assertSame(['title', 'excerpt'], array_keys($translations[$default]->children));
        self::assertSame('thread[translations]['.$default.'][title]', $translations[$default]['title']->vars['full_name']);
        self::assertSame('thread_translations_'.$default.'_title', $translations[$default]['title']->vars['id']);
        self::assertSame('Abbey Road', $translations[$default]['title']->vars['value']);
        self::assertSame(Kind::TEXTAREA, $translations[$default]['excerpt']->vars['kind']);
        self::assertTrue($translations[$other]['title']->vars['required'], 'its field says so, its part is required');
        self::assertFalse($translations[$default]['excerpt']->vars['required'], 'its column may be empty');
        self::assertSame([$default], $translations->vars['nonempty_locales']);

        $binding = $binder->bind($form, ['translations' => [$default => ['title' => 'Let It Be', 'excerpt' => ''], $other => ['title' => 'Help!', 'excerpt' => 'Yeah']]], $thread);
        self::assertSame([], $binding->violations);
        self::assertSame('Let It Be', $thread->translate($default)->getTitle());
        self::assertSame('Help!', $thread->translate($other)->getTitle());
        self::assertSame('Yeah', $thread->translate($other)->getExcerpt());
        self::assertSame($other, $thread->getTranslations()[$other]->getLocale());

        $binder->bind($form, ['translations' => [$other => ['title' => '', 'excerpt' => '']]], $thread);
        self::assertFalse($thread->getTranslations()->containsKey($other), 'a language left empty is not kept');
    }
}
