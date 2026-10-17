<?php

namespace Tests\Base\Bridge\Doctrine;

use Base\Bridge\Doctrine\MetadataSnapshot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The Doctrine mapping omnibase assembles at runtime (plan "omnibase agnostique", C6).
 *
 * Symfony's entity manager gives the metadata it gave before C6, class by class (table, inheritance,
 * discriminator map and value, fields and their types, associations, cache, the filters' SQL, the
 * completors): recorded in fixtures/doctrine-metadata.json by this test on the code of before
 * (DOCTRINE_SNAPSHOT=1).
 */
final class HandBuiltEntityManagerTest extends KernelTestCase
{
    private const FIXTURE = __DIR__.'/fixtures/doctrine-metadata.json';

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testSymfonysMetadataIsThatOfBefore(): void
    {
        $snapshot = $this->symfonySnapshot();
        $snapshot = ['application' => static::getContainer()->getParameter('kernel.project_dir'), 'platform' => $this->platform()] + $snapshot;

        if (getenv('DOCTRINE_SNAPSHOT')) {
            @mkdir(dirname(self::FIXTURE), 0777, true);
            file_put_contents(self::FIXTURE, self::json($snapshot));
            self::markTestSkipped('Recorded in '.self::FIXTURE.'.');
        }

        self::assertFileExists(self::FIXTURE);
        $recorded = json_decode((string) file_get_contents(self::FIXTURE), true);
        if (($recorded['application'] ?? null) !== $snapshot['application'] || ($recorded['platform'] ?? null) !== $snapshot['platform']) {
            self::markTestSkipped('Recorded in the omnibase harness ('.($recorded['application'] ?? '?').', '.($recorded['platform'] ?? '?').'); this application maps its own entities.');
        }

        $snapshot = json_decode((string) json_encode($snapshot), true);
        self::assertSame(array_keys($recorded['classes']), array_keys($snapshot['classes']), 'the same classes');
        $differences = [];
        foreach ($recorded['classes'] as $className => $metadata) {
            $differences = array_merge($differences, self::differences($metadata, $snapshot['classes'][$className] ?? null, $className));
        }
        self::assertSame([], $differences, 'the metadata of '.count($recorded['classes']).' classes');
        self::assertSame($recorded['filters'], $snapshot['filters'], 'the filters');
        self::assertSame($recorded['types'], $snapshot['types'], 'the types');
    }

    /** @return list<string> where two values differ, each as its path, what was expected and what came */
    private static function differences(mixed $expected, mixed $actual, string $path): array
    {
        if (is_array($expected) && is_array($actual)) {
            if (array_is_list($expected) !== array_is_list($actual) || (array_is_list($expected) && count($expected) !== count($actual))) {
                return [$path.': '.json_encode($expected, JSON_UNESCAPED_SLASHES).' / '.json_encode($actual, JSON_UNESCAPED_SLASHES)];
            }
            if (array_keys($expected) !== array_keys($actual)) {
                $missing = array_diff_key($expected, $actual);
                $extra = array_diff_key($actual, $expected);
                if ($missing || $extra) {
                    return [$path.': keys '.json_encode(array_keys($missing)).' missing, '.json_encode(array_keys($extra)).' more'];
                }

                return [$path.': keys in another order, '.json_encode(array_keys($actual))];
            }

            $differences = [];
            foreach ($expected as $key => $value) {
                $differences = array_merge($differences, self::differences($value, $actual[$key], $path.'.'.$key));
            }

            return $differences;
        }

        return $expected === $actual ? [] : [$path.': '.json_encode($expected, JSON_UNESCAPED_SLASHES).' / '.json_encode($actual, JSON_UNESCAPED_SLASHES)];
    }

    /** One class a line: a difference shows as the line of its class. */
    private static function json(array $snapshot): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $lines = [];
        foreach ($snapshot['classes'] as $className => $metadata) {
            $lines[] = '        '.json_encode($className, $flags).': '.json_encode($metadata, $flags);
        }
        unset($snapshot['classes']);
        $head = substr(json_encode($snapshot, $flags | JSON_PRETTY_PRINT), 0, -2);

        return $head.",\n    \"classes\": {\n".implode(",\n", $lines)."\n    }\n}\n";
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function platform(): string
    {
        return $this->entityManager()->getConnection()->getDatabasePlatform()::class;
    }

    /** @return array{classes: array<string, array<string, mixed>>, filters: array<string, string>, types: array<string, string>} */
    private function symfonySnapshot(): array
    {
        return MetadataSnapshot::of($this->entityManager());
    }
}
