<?php

namespace Tests\Base\Database\Entity\Extension;

use Base\Entity\Thread\TagIntl;
use PHPUnit\Framework\TestCase;

/**
 * TranslationTrait::isEmpty() decides whether a submitted-but-blank locale
 * gets persisted or pruned before flush - a false "not empty" either
 * fatals on a NOT NULL translatable column (TagIntl::label) or silently
 * litters the database with junk rows (nullable columns like
 * ThreadIntl::title just absorb it quietly). Real regression: a
 * multi-value choice widget ("keywords") left untouched by the user still
 * submits [""] (one blank string), not the literal [] the widget
 * conceptually means.
 */
class TranslationTraitTest extends TestCase
{
    public function testGenuinelyEmptyTranslationIsEmpty(): void
    {
        $translation = new TagIntl();

        $this->assertTrue($translation->isEmpty());
    }

    public function testBlankStringKeywordsArrayIsStillEmpty(): void
    {
        $translation = new TagIntl();
        $translation->setKeywords(['']);

        $this->assertTrue(
            $translation->isEmpty(),
            'An array of only blank strings must count as no real content, same as a blank scalar string does.'
        );
    }

    public function testMultipleBlankStringKeywordsIsStillEmpty(): void
    {
        $translation = new TagIntl();
        $translation->setKeywords(['', '  ', '']);

        $this->assertTrue($translation->isEmpty());
    }

    public function testRealKeywordMakesItNonEmpty(): void
    {
        $translation = new TagIntl();
        $translation->setKeywords(['travel']);

        $this->assertFalse($translation->isEmpty());
    }

    public function testRealLabelMakesItNonEmpty(): void
    {
        $translation = new TagIntl();
        $translation->setLabel('Everyday life');

        $this->assertFalse($translation->isEmpty());
    }

    public function testWhitespaceOnlyLabelIsStillEmpty(): void
    {
        $translation = new TagIntl();
        $translation->setLabel('   ');

        $this->assertTrue($translation->isEmpty());
    }

    public function testLiterallyEmptyKeywordsArrayIsEmpty(): void
    {
        $translation = new TagIntl();
        $translation->setKeywords([]);

        $this->assertTrue($translation->isEmpty());
    }
}
