<?php

namespace Tests\Base\Bridge\Doctrine;

use Base\Attributes\AttributeReader;
use Base\Bridge\Doctrine\MappingDriver;
use Base\Bridge\Doctrine\MetadataSnapshot;
use Base\Entity\Thread;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\AttachEntityListenersListener;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The Doctrine mapping omnibase assembles at runtime (plan "omnibase agnostique", C6).
 *
 * - An entity manager built by hand - no kernel: Composer's autoloader, a DBAL connection,
 *   omnibridge/doctrine's EntityManagers::create() and Base\Bridge\Doctrine\Mapping::register(), in a
 *   PHP process of its own (hand-built.php) - gives the metadata Symfony's gives, class by class
 *   (table, inheritance, discriminator map and value, fields and their types, associations, cache,
 *   the filters' SQL, the completors), on the same database, and reads what Symfony wrote.
 * - Symfony's entity manager gives the metadata it gave before C6: recorded in
 *   fixtures/doctrine-metadata.json by this test on the code of before (DOCTRINE_SNAPSHOT=1).
 */
final class HandBuiltEntityManagerTest extends KernelTestCase
{
    private const FIXTURE = __DIR__.'/fixtures/doctrine-metadata.json';

    /** @var list<string> the classes Symfony's attribute drivers map */
    private array $attributeClasses = [];

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

    public function testTheHandBuiltEntityManagerGivesSymfonysMetadata(): void
    {
        $entityManager = $this->entityManager();
        $symfony = json_decode((string) json_encode($this->symfonySnapshot()), true);

        // A thread written by Symfony, in the page's language, read by hand.
        $thread = new Thread();
        $thread->setTitle('C6 '.bin2hex(random_bytes(4)));
        $entityManager->persist($thread);
        $entityManager->flush();

        try {
            $answer = self::handBuilt($this->options([
                'thread' => ['dql' => 'SELECT t FROM '.Thread::class.' t WHERE t.id = :id', 'parameters' => ['id' => $thread->getId()], 'fields' => ['getSlug', 'getTranslations.getTitle']],
                'repository' => ['repository' => Thread::class, 'criteria' => ['id' => $thread->getId()], 'fields' => ['getSlug', 'getTranslations.getTitle']],
            ]));
        } finally {
            // Removed without the soft deletion (a trashed thread is a row still): its translations, then itself.
            $entityManager->createQuery('DELETE FROM '.$thread::getTranslationEntityClass().' i WHERE i.translatable = :thread')->setParameter('thread', $thread->getId())->execute();
            $entityManager->createQuery('DELETE FROM '.Thread::class.' t WHERE t.id = :thread')->setParameter('thread', $thread->getId())->execute();
        }

        self::assertSame(\Doctrine\ORM\Mapping\ClassMetadataFactory::class, $answer['factory'], 'Doctrine\'s own factory, as published');
        // No kernel, no DoctrineBundle: of the framework, the process loaded at most a few interfaces some classes still name.
        self::assertSame([], preg_grep('~^(?:Doctrine\\\\Bundle\\\\|Symfony\\\\Component\\\\HttpKernel\\\\(?:Kernel|HttpKernel)$|Symfony\\\\Component\\\\DependencyInjection\\\\Container$|Symfony\\\\Bundle\\\\)~', $answer['framework']), 'neither the kernel nor a bundle');
        if (getenv('C6_FRAMEWORK')) {
            fwrite(STDERR, "\nframework loaded by hand-built.php: ".implode(', ', $answer['framework'])."\n");
        }
        // The same classes, but those a bundle maps with a driver of its own (XML: WebauthnBundle's credentials),
        // which a site without the framework does not have.
        self::assertSame([], array_values(array_diff(array_keys($answer['classes']), array_keys($symfony['classes']))), 'no class Symfony does not map');
        $own = array_values(array_diff(array_keys($symfony['classes']), array_keys($answer['classes'])));
        foreach ($own as $className) {
            self::assertNotContains($className, $this->attributeClasses, $className.' is mapped by attributes');
            unset($symfony['classes'][$className]);
        }
        // And the fields an entity inherits from one of them (Base\Entity\User\Passkey from Webauthn's
        // CredentialRecord), declared there.
        foreach ($symfony['classes'] as $className => $metadata) {
            foreach ($metadata['fieldMappings'] as $field => $mapping) {
                if (in_array($mapping['declared'] ?? null, $own, true)) {
                    unset($symfony['classes'][$className]['fieldMappings'][$field], $symfony['classes'][$className]['columnNames'][$field], $symfony['classes'][$className]['propertyAccessors'][$field]);
                    $symfony['classes'][$className]['fieldNames'] = array_diff($symfony['classes'][$className]['fieldNames'], [$field]);
                }
            }
        }
        $differences = [];
        foreach ($symfony['classes'] as $className => $metadata) {
            $differences = array_merge($differences, self::differences($metadata, $answer['classes'][$className] ?? null, $className));
        }
        self::assertSame([], $differences, 'the metadata of '.count($symfony['classes']).' classes');
        self::assertSame($symfony['filters'], $answer['filters'], 'the same filters enabled');

        // Every type registered by hand is Symfony's; Symfony has its doctrine-bridge's own besides (uuid, ulid...).
        foreach ($answer['types'] as $name => $className) {
            self::assertSame($symfony['types'][$name] ?? null, $className, 'type '.$name);
        }
        foreach (array_diff_key($symfony['types'], $answer['types']) as $name => $className) {
            self::assertStringStartsWith('Symfony\\Bridge\\Doctrine\\Types\\', $className, 'type '.$name.' only Symfony\'s');
        }

        self::assertSame([[
            'class' => Thread::class,
            'id' => $thread->getId(),
            'getSlug' => $thread->getSlug(),
            'getTranslations.getTitle' => array_map(fn ($translation) => $translation->getTitle(), $thread->getTranslations()->toArray()),
        ]], $answer['reads']['thread'], 'the thread Symfony wrote, read by hand');
        self::assertSame($answer['reads']['thread'], $answer['reads']['repository'], 'and through its repository');
        self::assertSame(get_class($entityManager->getRepository(Thread::class)), $answer['repositories']['repository'], 'the same repository class');
    }

    /**
     * What the hand-built process is given: the application's autoloader and folders, Symfony's connection
     * parameters, entity folders (its drivers'), omnibase's parameters, the types of DoctrineBundle's
     * configuration, the targets it resolves and the entity listeners it attaches - configuration, as a site without the framework would write it.
     */
    private function options(array $reads = []): array
    {
        $container = static::getContainer();
        $entityManager = $this->entityManager();
        $projectDir = $container->getParameter('kernel.project_dir');

        $driver = $entityManager->getConfiguration()->getMetadataDriverImpl();
        $driver = $driver instanceof MappingDriver ? $driver->getDriver() : $driver;
        $paths = [];
        $this->attributeClasses = [];
        foreach ($driver instanceof MappingDriverChain ? $driver->getDrivers() : [$driver] as $inner) {
            if ($inner instanceof AttributeDriver) {
                $paths = array_merge($paths, $inner->getPaths());
                $this->attributeClasses = array_merge($this->attributeClasses, $inner->getAllClassNames());
            }
        }

        $parameters = [];
        foreach (AttributeReader::getInstance(false)->getParameterBag()->all() as $name => $value) {
            if (str_starts_with($name, 'base.') && (is_scalar($value) || $value === null)) {
                $parameters[$name] = $value;
            }
        }

        $types = [];
        foreach ($container->hasParameter('doctrine.dbal.connection_factory.types') ? $container->getParameter('doctrine.dbal.connection_factory.types') : [] as $name => $type) {
            $types[$name] = $type['class'];
        }
        $types = array_filter($types, fn ($className) => !str_starts_with($className, 'Symfony\\'));

        $targets = [];
        $entityListeners = [];
        foreach ($entityManager->getEventManager()->getListeners(Events::loadClassMetadata) as $listener) {
            if ($listener instanceof ResolveTargetEntityListener) {
                foreach ((fn () => $this->resolveTargetEntities)->call($listener) as $interface => $mapping) {
                    $targets[$interface] = $mapping['targetEntity'];
                }
            }
            if ($listener instanceof AttachEntityListenersListener) {
                $entityListeners = (fn () => $this->entityListeners)->call($listener);
            }
        }

        $connection = array_filter($entityManager->getConnection()->getParams(), fn ($value) => !is_object($value));

        return [
            'autoload' => $projectDir.'/vendor/autoload.php',
            'connection' => $connection,
            'paths' => array_values(array_unique($paths)),
            'project_dir' => $projectDir,
            'environment' => $container->getParameter('kernel.environment'),
            'parameters' => $parameters,
            'types' => $types,
            'resolve_target_entities' => $targets,
            'entity_listeners' => $entityListeners,
            'reads' => $reads,
        ];
    }

    private static function handBuilt(array $options): array
    {
        $in = tempnam(sys_get_temp_dir(), 'c6in');
        $out = tempnam(sys_get_temp_dir(), 'c6out');
        file_put_contents($in, json_encode($options, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        try {
            $process = proc_open([PHP_BINARY, __DIR__.'/hand-built.php', $in, $out], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            $status = proc_close($process);
            self::assertSame(0, $status, "hand-built.php failed:\n".$output.$errors);

            return json_decode((string) file_get_contents($out), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            @unlink($in);
            @unlink($out);
        }
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
