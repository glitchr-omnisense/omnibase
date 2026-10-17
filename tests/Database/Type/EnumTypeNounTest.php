<?php

namespace Tests\Base\Database\Type;

use Base\Enum\ThreadState;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use PHPUnit\Framework\TestCase;

/**
 * An enumeration's text asks the translator for the singular noun of its
 * value by TranslatorInterface's constant (plan "omnibase agnostique", step
 * C3a): EnumType names no class that imports the framework (Translator
 * imports the kernel), and the 20 enumerations of the core, which inherit
 * from it, neither. Translator::NOUN_SINGULAR still names the same constant.
 */
final class EnumTypeNounTest extends TestCase
{
    public function testTheNounsAreTheInterfacesAndTranslatorStillNamesThem(): void
    {
        self::assertSame('noun', TranslatorInterface::TRANSLATION_NOUN);
        self::assertSame('_singular', TranslatorInterface::NOUN_SINGULAR);
        self::assertSame('_plural', TranslatorInterface::NOUN_PLURAL);

        self::assertSame(TranslatorInterface::TRANSLATION_NOUN, Translator::TRANSLATION_NOUN);
        self::assertSame(TranslatorInterface::NOUN_SINGULAR, Translator::NOUN_SINGULAR);
        self::assertSame(TranslatorInterface::NOUN_PLURAL, Translator::NOUN_PLURAL);
    }

    public function testGetTextAsksTheSingularNounOfTheValue(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())
            ->method('transEnum')
            ->with(ThreadState::PUBLISH, ThreadState::class, TranslatorInterface::NOUN_SINGULAR)
            ->willReturn('Publié');

        self::assertSame('Publié', ThreadState::getText(ThreadState::PUBLISH, $translator));
        self::assertSame(ThreadState::PUBLISH, ThreadState::getText(ThreadState::PUBLISH));
    }

    public function testTheTypeNamesNoTranslatorClass(): void
    {
        $source = (string) file_get_contents((string) (new \ReflectionClass(\Base\Database\Type\EnumType::class))->getFileName());

        self::assertStringNotContainsString('use Base\\Service\\Translator;', $source);
        self::assertStringNotContainsString('Translator::', str_replace('TranslatorInterface::', '', $source));
    }
}
