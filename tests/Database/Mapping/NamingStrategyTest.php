<?php

namespace Tests\Base\Database\Mapping;

use Base\Database\Mapping\NamingStrategy;
use PHPUnit\Framework\TestCase;

/**
 * omnibase's names are omnibridge/doctrine's (Base\Database\Mapping\
 * NamingStrategy extends Omnibridge\Doctrine\NamingStrategy): checked here as
 * omnibridge/doctrine checks its own, against a real site - every entity of
 * lesringostars, the table its Symfony entity manager gives it, its join
 * tables and their columns (lesringostars-tables.json, the same file as
 * omnibridge/doctrine's tests/). A class omnibase has (Base\...) is read from
 * its attributes, as in an application; the tables a class of the site names
 * itself (#[Table(name: ...)]) are said by the file.
 */
final class NamingStrategyTest extends TestCase
{
    /** @return array<string, array{table: string, declared: bool, joins: array<string, list<mixed>>|list<never>}> */
    private static function site(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/lesringostars-tables.json'), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function strategy(): NamingStrategy
    {
        $declared = array_map(static fn (array $row) => $row['table'], array_filter(self::site(), static fn (array $row) => $row['declared']));

        return new class($declared) extends NamingStrategy {
            public function __construct(private readonly array $declared)
            {
            }

            public function classToTableName($className): string
            {
                return $this->declared[$className] ?? parent::classToTableName($className);
            }
        };
    }

    public function testEveryTableOfTheSite(): void
    {
        $strategy = new NamingStrategy();
        $checked = 0;
        $skipped = 0;
        $base = 0;
        foreach (self::site() as $class => $row) {
            // A class of the site that names its table, absent here (or the harness's own App\ class of that name).
            if ($row['declared'] && !str_starts_with($class, 'Base\\')) {
                continue;
            }
            // An application's own App\ class (the harness's, or the site's own entities when this
            // suite runs in an application: its stubs and entities load, or need its kernel to)
            // is not the fixture.
            try {
                if (str_starts_with($class, 'App\\') && class_exists($class)) {
                    ++$skipped;
                    continue;
                }
            } catch (\Error) {
                ++$skipped;
                continue;
            }
            self::assertSame($row['table'], $strategy->classToTableName($class), $class);
            ++$checked;
            $base += str_starts_with($class, 'Base\\') ? 1 : 0;
        }

        // Every class of omnibase the site maps is checked, wherever the suite runs; the site's own
        // classes only where they are not the application's (the harness: all but its own few, more
        // than 100 in all; in lesringostars' vendor suite its 19 App\ entities load and are skipped).
        self::assertSame(\count(array_filter(array_keys(self::site()), static fn (string $class) => str_starts_with($class, 'Base\\'))), $base);
        self::assertGreaterThan(100 - $skipped, $checked);
    }

    public function testEveryJoinTableAndItsColumns(): void
    {
        $strategy = self::strategy();
        $checked = 0;
        foreach (self::site() as $row) {
            foreach ($row['joins'] ?: [] as $property => [$table, $column, $inverse, $target, $owner]) {
                // A table joining a class to itself (connexes) names its columns: two thread_id cannot be.
                if ($strategy->classToTableName($owner) === $strategy->classToTableName($target)) {
                    continue;
                }
                self::assertSame([$table, $column, $inverse], [
                    $strategy->joinTableName($owner, $target, $property),
                    $strategy->joinKeyColumnName($owner, null),
                    $strategy->joinKeyColumnName($target, null),
                ], $owner.'::$'.$property);
                ++$checked;
            }
        }
        self::assertGreaterThan(20, $checked);
    }

    public function testOmnibaseAdditionsAreKept(): void
    {
        $strategy = new NamingStrategy();

        self::assertInstanceOf(\Omnibridge\Doctrine\NamingStrategy::class, $strategy);
        // An object is its class; nothing is nothing.
        self::assertSame('threadTag', $strategy->classToTableName((new \ReflectionClass(\Base\Entity\Thread\Tag::class))->newInstanceWithoutConstructor()));
        self::assertSame('', $strategy->classToTableName(null));
        self::assertSame('threadIntl', $strategy->classToTableName('Base\Entity\ThreadIntl'));
        self::assertSame('thread_tags', $strategy->joinTableName('Base\Entity\Thread', 'App\Entity\Thread\Tag', 'tags'));
        self::assertSame('translatable_id', $strategy->joinColumnName('translatable', 'Base\Entity\ThreadIntl'));
        self::assertSame('Intl', NamingStrategy::TABLE_I18N_SUFFIX);

        // A name over 64 characters is refused, an \Exception as before.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Table name will be truncated');
        $strategy->classToTableName('App\Entity\\'.implode('', array_map(static fn (int $i) => 'Part'.$i, range(1, 15))));
    }
}
