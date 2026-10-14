<?php

namespace Tests\Base\Translation;

use Base\Admin\Filter\Filters;
use Base\Field\FieldDescriptor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * A field that names no label shows its property humanised ("createdAt" ->
 * "Created at"), translated through the `forms` domain by the form, the
 * list's columns and its filters. "State" and "Slug" were in no language, so
 * every StateField and SlugField of every bundle read English: the fields
 * and filters of omnibase's own screens, and the names the bundles' screens
 * share, are in each language the domain carries.
 */
class FieldLabelsCatalogueTest extends TestCase
{
    /** Names every bundle's screens share, whatever omnibase's own use. */
    private const SHARED = ['Slug', 'State', 'Status', 'Created at', 'Updated at', 'Published at', 'Title', 'Parent', 'Featured', 'Visible'];

    /** Symfony's FormRenderer::humanize(), which gives a field without a label its text. */
    private static function humanize(string $text): string
    {
        return ucfirst(strtolower(trim(preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $text))));
    }

    /** @return array<string, array<string, mixed>> language => the domain's catalogue */
    private static function catalogues(): array
    {
        $catalogues = [];
        foreach (glob(\dirname(__DIR__, 2).'/translations/forms+intl-icu.*.yaml') as $file) {
            $catalogues[explode('.', basename($file))[1]] = Yaml::parseFile($file) ?? [];
        }

        return $catalogues;
    }

    /** @return list<string> the humanised texts omnibase's own screens show, from their fields and filters */
    private function screenTexts(): array
    {
        $texts = [];
        foreach (glob(\dirname(__DIR__, 2).'/src/Controller/Backoffice/Crud/{,*/,*/*/}*CrudController.php', GLOB_BRACE) as $file) {
            $class = 'Base\\Controller\\Backoffice\\Crud\\'.str_replace('/', '\\', substr($file, \strlen(\dirname(__DIR__, 2).'/src/Controller/Backoffice/Crud/'), -4));
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }
            // configureFields() and configureFilters() need none of the controller's services.
            $crud = $reflection->newInstanceWithoutConstructor();
            foreach ([FieldDescriptor::PAGE_INDEX, FieldDescriptor::PAGE_NEW, FieldDescriptor::PAGE_EDIT, FieldDescriptor::PAGE_DETAIL] as $page) {
                foreach ($crud->configureFields($page) as $field) {
                    $descriptor = $field->getAsDto();
                    if (null === $descriptor->getLabel() && null !== $descriptor->getProperty()) {
                        $texts[] = self::humanize($descriptor->getProperty());
                    }
                }
            }
            foreach ($crud->configureFilters(Filters::new())->getAll() as $filter) {
                if (null === $filter->getLabel()) {
                    $texts[] = self::humanize($filter->getProperty());
                }
            }
        }

        return array_values(array_unique($texts));
    }

    public function testTheDomainHasMoreThanOneLanguage(): void
    {
        self::assertContains('fr', array_keys(self::catalogues()));
        self::assertContains('en', array_keys(self::catalogues()));
    }

    public function testStateAndSlugAreTranslated(): void
    {
        self::assertSame('Statut', self::catalogues()['fr']['State'] ?? null);
        self::assertSame('Adresse', self::catalogues()['fr']['Slug'] ?? null);
    }

    public function testEveryLabelOmnibaseLeavesToItsPropertyIsInEveryLanguage(): void
    {
        if (!class_exists(Filters::class)) {
            self::markTestSkipped('Requires omnibase/admin (the omnibase harness).');
        }

        $texts = $this->screenTexts();
        self::assertContains('Slug', $texts, 'the screens are read');

        $missing = [];
        foreach (self::catalogues() as $language => $catalogue) {
            foreach (array_unique([...$texts, ...self::SHARED]) as $text) {
                if (!\is_string($catalogue[$text] ?? null)) {
                    $missing[] = $language.': "'.$text.'"';
                }
            }
        }
        self::assertSame([], $missing, 'shown in English whatever the language');
    }
}
