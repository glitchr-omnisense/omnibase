<?php

namespace Tests\Base\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * omnibase/admin's FieldLabelsCatalogueTest reads the screens (its own, those
 * of the core's entities among them) against this catalogue.
 *
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

    /** @return array<string, array<string, mixed>> language => the domain's catalogue */
    private static function catalogues(): array
    {
        $catalogues = [];
        foreach (glob(\dirname(__DIR__, 2).'/translations/forms+intl-icu.*.yaml') as $file) {
            $catalogues[explode('.', basename($file))[1]] = Yaml::parseFile($file) ?? [];
        }

        return $catalogues;
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

    public function testTheNamesEveryBundleSharesAreInEveryLanguage(): void
    {
        $missing = [];
        foreach (self::catalogues() as $language => $catalogue) {
            foreach (self::SHARED as $text) {
                if (!\is_string($catalogue[$text] ?? null)) {
                    $missing[] = $language.': "'.$text.'"';
                }
            }
        }
        self::assertSame([], $missing, 'shown in English whatever the language');
    }
}
