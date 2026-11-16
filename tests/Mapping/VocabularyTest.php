<?php

namespace Tests\Base\Mapping;

use Base\Database\Schema\EntitySchema;
use Base\Mapping\MappingAttribute;
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\Vocabulary;
use PHPUnit\Framework\TestCase;

/**
 * omnibase's mapping vocabulary (Base\Mapping, step E3 / decision Q8): one class per attribute of
 * omnisense/omnibridge's Vocabulary, with its parameters, their order and defaults, and whether it repeats - the
 * same as doctrine/orm's when it is installed, targets included -; the core's entities written in it and in no ORM's;
 * and read by the declarations' reader (what Doctrine's DeclarationDriver and omnibridge/eloquent build from).
 *
 * Doctrine's metadata of the entities, byte for byte those of the entities written in Doctrine's vocabulary before:
 * the ensemble's _tools/e3/compare.sh (MetadataSnapshot, Description and Eloquent's maps, before and after).
 */
final class VocabularyTest extends TestCase
{
    public function testEveryAttributeOfTheVocabularyIsAClassOfBaseMapping(): void
    {
        foreach (Vocabulary::PARAMETERS as $name => $parameters) {
            $className = 'Base\\Mapping\\'.$name;
            self::assertTrue(class_exists($className), $className);
            $class = new \ReflectionClass($className);
            self::assertTrue($class->isFinal(), $name.' is final');
            self::assertTrue($class->implementsInterface(MappingAttribute::class), $name.' is a mapping attribute');
            self::assertSame([], array_values(array_filter(class_parents($className))), $name.' has no parent class');

            $declared = [];
            foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
                $declared[$parameter->getName()] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : Vocabulary::REQUIRED;
            }
            self::assertSame($parameters, $declared, $name.': the parameters, in order, and their defaults');

            $attribute = ($class->getAttributes(\Attribute::class)[0] ?? null)?->newInstance();
            self::assertSame(Vocabulary::isRepeatable($name), null !== $attribute && (bool) ($attribute->flags & \Attribute::IS_REPEATABLE), $name.': repeatable');
            self::assertSame($name, Vocabulary::nameOf($className));
        }
    }

    public function testBaseMappingHoldsNothingElse(): void
    {
        $names = [];
        foreach (glob(\dirname(__DIR__, 2).'/src/Mapping/*.php') ?: [] as $file) {
            $names[] = basename($file, '.php');
        }
        sort($names);
        $expected = array_merge(array_keys(Vocabulary::PARAMETERS), ['JoinColumnProperties', 'MappingAttribute', 'Types']);
        sort($expected);

        self::assertSame($expected, $names);
    }

    public function testTheTargetsAreDoctrinesWhereDoctrineIsInstalled(): void
    {
        if (!interface_exists('Doctrine\\ORM\\Mapping\\MappingAttribute')) {
            self::markTestSkipped('doctrine/orm is not installed.');
        }

        foreach (array_keys(Vocabulary::PARAMETERS) as $name) {
            $doctrine = (new \ReflectionClass(Vocabulary::NAMESPACE.$name))->getAttributes(\Attribute::class)[0] ?? null;
            $base = (new \ReflectionClass('Base\\Mapping\\'.$name))->getAttributes(\Attribute::class)[0] ?? null;
            self::assertSame($doctrine?->newInstance()->flags, $base?->newInstance()->flags, $name.': where it may be written');
        }
    }

    public function testTheColumnTypesAreDbalsNamesAndValues(): void
    {
        $types = (new \ReflectionClass(\Base\Mapping\Types::class))->getConstants();
        foreach (['UTC_DATETIME' => 'utc_datetime', 'UTC_DATETIME_IMMUTABLE' => 'utc_datetime_immutable', 'ARRAY' => 'array'] as $name => $value) {
            self::assertSame($value, $types[$name], 'omnibase\'s '.$name);
        }
        if (!class_exists('Doctrine\\DBAL\\Types\\Types')) {
            self::markTestSkipped('doctrine/dbal is not installed.');
        }

        $dbal = (new \ReflectionClass('Doctrine\\DBAL\\Types\\Types'))->getConstants();
        self::assertSame(array_keys($dbal), \Base\Mapping\Types::PORTABLE, 'every constant of doctrine/dbal\'s Types, in its order');
        self::assertSame($dbal, array_intersect_key($types, $dbal), 'the same values');
    }

    public function testTheCoresEntitiesNameNoOrm(): void
    {
        $src = \dirname(__DIR__, 2).'/src';
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src.'/Entity', \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[] = $file->getPathname();
        }
        // The traits whose properties the entities take, with their mapping.
        foreach (['Database/Entity/Extension/TranslationTrait.php', 'Database/Entity/Extension/TrasheableTrait.php', 'Database/Entity/Extension/VaultTrait.php', 'Traits/UserInfoTrait.php'] as $trait) {
            $files[] = $src.'/'.$trait;
        }

        $found = [];
        foreach ($files as $file) {
            if (preg_match('~\b(?:Doctrine\\\\(?:ORM|DBAL)|Illuminate\\\\Database)\b~', (string) file_get_contents($file))) {
                $found[] = substr($file, \strlen($src) + 1);
            }
        }

        self::assertSame([], $found, 'the entities are written in Base\\Mapping');
    }

    public function testTheEntitiesAreReadInTheirVocabulary(): void
    {
        $reader = new AttributeReader();
        $user = $reader->read('Base\\Entity\\User');

        self::assertSame('Entity', $user?->kind());
        self::assertSame('JOINED', $user->attribute('InheritanceType')?->get('value'), 'a positional argument named after its parameter');
        self::assertSame('layoutRedirection', $reader->read('Base\\Entity\\Layout\\Redirection')?->attribute('Table')?->get('name'));

        $options = ['project_dir' => sys_get_temp_dir().'/omnibase-no-project'];
        $description = EntitySchema::describe([EntitySchema::coreDirectory('Entity')], $options);
        self::assertSame('user', $description->entity('Base\\Entity\\User')->table);
        self::assertGreaterThan(90, \count($description->entities), 'every entity of the core described');
    }
}
