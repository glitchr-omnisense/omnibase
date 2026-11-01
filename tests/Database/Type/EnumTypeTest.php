<?php

namespace Tests\Base\Database\Type;

use Base\Bridge\Doctrine\EnumTypeAdapter;
use Base\Database\Type\EnumType;
use Base\Enum\ThreadState;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Its conversions and SQL declaration through Base\Bridge\Doctrine\EnumTypeAdapter, the enumeration's
 * Doctrine type since step F2a.
 *
 * Exercised through a real concrete subclass (ThreadState) rather than a
 * throwaway fixture: this is EnumType's actual contract as every subclass
 * in the bundle uses it, and ThreadState conveniently already implements
 * both IconizeInterface and ColorizeInterface with real multi-value data.
 */
class EnumTypeTest extends TestCase
{
    public function testGetPermittedValuesReturnsAllConstants(): void
    {
        $values = ThreadState::getPermittedValues();

        $this->assertContains(ThreadState::PUBLISH, $values);
        $this->assertContains(ThreadState::DRAFT, $values);
        $this->assertCount(7, $values);
    }

    public function testHasKeyAndGetValue(): void
    {
        $this->assertTrue(ThreadState::hasKey('PUBLISH'));
        $this->assertFalse(ThreadState::hasKey('NOT_A_REAL_KEY'));

        $this->assertSame(ThreadState::PUBLISH, ThreadState::getValue('PUBLISH'));
        $this->assertNull(ThreadState::getValue('NOT_A_REAL_KEY'));
    }

    public function testHasValue(): void
    {
        $this->assertTrue(ThreadState::hasValue(ThreadState::PUBLISH));
        $this->assertFalse(ThreadState::hasValue('not-a-real-value'));
    }

    public function testGetStaticNameIsSnakeCaseOfClassName(): void
    {
        $this->assertSame('thread_state', ThreadState::getStaticName());
    }

    /**
     * The bug this locks in: getIcon() used to bypass closest() entirely
     * for the default (negative) $index, returning the raw icon array and
     * violating its own ?string return type for every multi-icon entry —
     * i.e. every real IconizeInterface implementation. closest() already
     * resolves a negative index to the first entry on its own.
     */
    public function testGetIconDefaultsToTheFirstIcon(): void
    {
        $this->assertSame('fa-solid fa-book', ThreadState::getIcon(ThreadState::PUBLISH));
    }

    public function testGetIconWithExplicitIndex(): void
    {
        $this->assertSame('fa-solid fa-book', ThreadState::getIcon(ThreadState::PUBLISH, 0));
        $this->assertSame('fa-solid fa-check', ThreadState::getIcon(ThreadState::PUBLISH, 1));
    }

    public function testGetIconUnknownIdReturnsNull(): void
    {
        $this->assertNull(ThreadState::getIcon('not-a-real-state'));
    }

    public function testGetColorReturnsFirstColor(): void
    {
        $this->assertSame('#198754', ThreadState::getColor(ThreadState::PUBLISH));
        $this->assertNull(ThreadState::getColor('not-a-real-state'));
    }

    public function testConvertToDatabaseValueAcceptsAPermittedValue(): void
    {
        $enum = new EnumTypeAdapter(ThreadState::class);

        $this->assertSame(
            ThreadState::PUBLISH,
            $enum->convertToDatabaseValue(ThreadState::PUBLISH, new SQLitePlatform())
        );
    }

    public function testConvertToDatabaseValueAcceptsNull(): void
    {
        $enum = new EnumTypeAdapter(ThreadState::class);

        $this->assertNull($enum->convertToDatabaseValue(null, new SQLitePlatform()));
    }

    public function testConvertToDatabaseValueRejectsUnknownValue(): void
    {
        $enum = new EnumTypeAdapter(ThreadState::class);

        $this->expectException(InvalidArgumentException::class);
        $enum->convertToDatabaseValue('not-a-real-state', new SQLitePlatform());
    }

    public function testConvertToDatabaseValueRejectsArrays(): void
    {
        $enum = new EnumTypeAdapter(ThreadState::class);

        $this->expectException(InvalidArgumentException::class);
        $enum->convertToDatabaseValue([ThreadState::PUBLISH], new SQLitePlatform());
    }

    public function testConvertToPHPValueIsAPassthrough(): void
    {
        $enum = new EnumTypeAdapter(ThreadState::class);

        $this->assertSame(ThreadState::PUBLISH, $enum->convertToPHPValue(ThreadState::PUBLISH, new SQLitePlatform()));
    }

    /**
     * The enumeration is a plain PHP class (plan "omnibase agnostic", step F2a): no Doctrine type in its
     * ancestry; its own toDatabaseValue() and toPHPValue() are what the adapter converts with.
     */
    public function testTheEnumerationIsNoDoctrineType(): void
    {
        $this->assertSame([EnumType::class], array_values(class_parents(ThreadState::class)));
        $this->assertSame(ThreadState::PUBLISH, ThreadState::toDatabaseValue(ThreadState::PUBLISH));
        $this->assertSame(ThreadState::PUBLISH, ThreadState::toPHPValue(ThreadState::PUBLISH));
        $this->assertSame(ThreadState::class, (new EnumTypeAdapter(ThreadState::class))->enumeration());

        $this->expectException(\InvalidArgumentException::class);
        new EnumTypeAdapter(\stdClass::class);
    }

    /** SQLite's text column, CLOB: what a TEXT column is read back as, so a schema compares unchanged (C6b). */
    public function testSqlDeclarationIsPlainTextOnSqlite(): void
    {
        $enum = new EnumTypeAdapter(ThreadState::class);

        $this->assertSame('CLOB', $enum->getSQLDeclaration([], new SQLitePlatform()));
    }
}
