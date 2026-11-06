<?php

namespace Base\Console\Task;

use Base\Attributes\AttributeReader;
use Base\Console\Report;
use Base\Database\Attribute\Uploader;
use Base\Database\Repository\Repositories;
use Base\Service\Model\ClassFinder;
use Omnidrive\Model\Entry;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * uploader:entities (plan "omnibase agnostic", step F5e, slice 3): the properties holding uploaded files
 * (#[Uploader]) of the application's entities and the core's, the files each one's folder holds, and - asked - the
 * orphans: the files no row names, and the names no file answers; deleted if asked (demo:reset's nightly step).
 *
 * uploader:images and uploader:images:crop are this task with more to do on each property (process()).
 */
class UploaderEntities
{
    /** Made when the orphans are looked for (its making reads symfony/property-info, which nothing else here needs). */
    protected ?PropertyAccessorInterface $propertyAccessor = null;

    /** @var array<string, list<object>> */
    private array $entries = [];

    /** @var array<string, list<string>> */
    private array $fileList = [];

    public function __construct(protected readonly Repositories $repositories)
    {
    }

    public function run(UploaderEntitiesInput $input, ?\Closure $sink = null): Report
    {
        return $this->list($input, new Report($sink));
    }

    protected function list(UploaderEntitiesInput $input, Report $report): Report
    {
        $entityName = $input->entityName();
        $report->line("\n <info>Looking for \"".Uploader::class.'"</info> attributes..');

        $nTotalFiles = $nTotalOrphans = $nTotalFields = 0;
        $attributes = [];
        foreach (['App\\Entity\\'.$entityName => $input->source.'/Entity', 'Base\\Entity\\'.$entityName => \dirname((new \ReflectionClass(\Base\Services::class))->getFileName()).'/Entity'] as $namespace => $folder) {
            $found = $this->uploaderAttributes($folder.'/'.($entityName ? str_replace('\\', '/', $entityName).'.php' : ''));
            if (!$found) {
                $report->line("\t<warning>Uploader attribute not found for \"$namespace\"</warning>");
            }
            $attributes = array_merge($attributes, $found);
        }

        $report->line('', Report::VERBOSE);

        foreach ($attributes as $class => $properties) {
            if (!str_starts_with($class, 'Base\\Entity\\'.$entityName) && !str_starts_with($class, 'App\\Entity\\'.$entityName)) {
                continue;
            }

            $noPropertyFound = true;
            $field = null;
            foreach ($properties as $field => $attribute) {
                if ($input->property && $field != $input->property) {
                    continue;
                }
                ++$nTotalFields;

                $attribute = array_end($attribute);
                if ($attribute->getDeclaringEntity($class, $field) != $class) {
                    continue;
                }

                if ($attribute->getMissable()) {
                    $report->line("             $class::$field <warning> is missable.. cannot have orphan files..</warning>", Report::VERY_VERBOSE);
                    continue;
                }
                $report->line("             Processing <info>$class::$field</info>..");

                $publicPath = $attribute->getFlysystem()->getPublic('', $attribute->getStorage());
                $fileList = $this->fileList($class, $field, $attribute, $input->uuid);
                $nTotalFiles += \count($fileList);
                $noPropertyFound = false;

                $report->line($input->uuid ? "\t     $class::$field <ln>UUID \"$input->uuid\" found.</ln>" : "\t     $class::$field <ln>".\count($fileList).' file(s) found.</ln>', Report::VERBOSE);
                foreach ($fileList as $file) {
                    $report->line("\t           <ln>* .".str_lstrip(realpath($file), realpath($publicPath)).'</ln>', Report::DEBUG);
                }

                if ($input->orphans || $input->deleteOrphans) {
                    $orphanFiles = $this->orphanFiles($class, $field, $attribute, $input->uuid);
                    $nTotalOrphans += \count($orphanFiles);

                    $report->line("\t           <info>Looking for orphan files</info> in $publicPath <warning>".\count($orphanFiles).' orphan file(s) found.</warning>', Report::VERY_VERBOSE);
                    foreach ($orphanFiles as $file) {
                        $report->line("\t           <warning>* .".str_lstrip(realpath($file), realpath($publicPath)).'</warning>', Report::VERY_VERBOSE);
                    }

                    if ($input->deleteOrphans) {
                        $this->deleteOrphanFiles($attribute, $orphanFiles);
                        $report->line($orphanFiles ? "\t           <red>* Orphan files deleted..</red>" : "\t           <warning>* No orphan files to be deleted..</warning>", Report::VERY_VERBOSE);
                    }
                }

                $this->process($input, $report, $class, $field, $attribute, $fileList);
            }

            if ($noPropertyFound && !$input->property) {
                $report->line("             $class::$field <warning>not declared in this class..</warning>", Report::DEBUG);
                --$nTotalFields;
            }
        }

        $msg = ' [OK] '.$nTotalFields.' fields found: '.$nTotalFiles.' file(s)'.($input->orphans ? '; '.$nTotalOrphans.' orphan(s)' : null).' ! ';
        self::block($report, $msg, 'info,bkg');

        return $report;
    }

    /** What more a task does on a property's files (uploader:images: warm them up). */
    protected function process(UploaderEntitiesInput $input, Report $report, string $class, string $field, Uploader $attribute, array $fileList): void
    {
    }

    /** A block of one line, as the commands draw their results ([OK], [WARN], [ERR]). */
    protected static function block(Report $report, string $msg, string $style): void
    {
        $report->line('');
        $report->line('<'.$style.'>'.str_blankspace(\strlen($msg)));
        $report->line($msg);
        $report->line(str_blankspace(\strlen($msg)).'</'.$style.'>');
        $report->line('');
    }

    /**
     * The #[Uploader] properties of the classes of a folder (or of one file).
     *
     * @return array<string, array<string, list<Uploader>>>
     */
    private function uploaderAttributes(string $path): array
    {
        $reader = AttributeReader::getInstance();
        $attributes = [];
        foreach (ClassFinder::listed($path) as $class) {
            if ($found = $reader?->getPropertyAttributes($class, Uploader::class) ?? self::reflected($class)) {
                $attributes[$class] = $found;
            }
        }

        return $attributes;
    }

    /**
     * A class's #[Uploader] properties read by reflection, where no AttributeReader was built (no persistence's
     * metadata to read them through): what the reader answers, without its cache.
     *
     * @return array<string, list<Uploader>>
     */
    private static function reflected(string $class): array
    {
        $found = [];
        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            foreach ($property->getAttributes(Uploader::class) as $attribute) {
                $found[$property->name][] = $attribute->newInstance();
            }
        }

        return $found;
    }

    /** @return list<object> */
    protected function entries(string $class): array
    {
        return $this->entries[$class] ??= $this->repositories->of($class)->findAll();
    }

    /** @return list<string> the public paths of the files of the attribute's storage under the class's folder (omnidrive's listing) */
    private function fileList(string $class, string $field, Uploader $attribute, ?string $uuid): array
    {
        $property = $class.'::'.$field;
        if (!\array_key_exists($property, $this->fileList)) {
            // The attribute's storage (the former command listed the default one, the cache's: every row's file looked orphan).
            $entries = iterator_to_array(Uploader::getFlysystem()->getDrive($attribute->getStorage())->list(\dirname($attribute->getPath($class, $field))), false);
            $this->fileList[$property] = array_values(array_filter(array_map(
                static fn (Entry $f) => $f->isFile() ? $attribute->getFlysystem()->getPublic($f->path, $attribute->getStorage()) : null,
                $entries,
            )));
        }

        if ($uuid) {
            $this->fileList[$property] = array_filter($this->fileList[$property], static fn ($f) => basename($f) == $uuid);
        }

        return $this->fileList[$property];
    }

    /** @return list<string> the files no row names, and the names no file answers */
    private function orphanFiles(string $class, string $field, Uploader $attribute, ?string $uuid): array
    {
        if ($attribute->getMissable()) {
            return [];
        }

        $fileList = $this->fileList($class, $field, $attribute, $uuid);
        $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
        $inDatabase = array_flatten('.', array_map(fn ($e) => $this->propertyAccessor->getValue($e, $field), $this->entries($class)));

        return array_filter(array_unique(array_merge(array_values(array_diff($fileList, $inDatabase)), array_values(array_diff($inDatabase, $fileList)))));
    }

    private function deleteOrphanFiles(Uploader $attribute, array $fileList): void
    {
        $publicPath = realpath($attribute->getFlysystem()->getPublic('', $attribute->getStorage()));
        foreach ($fileList as $file) {
            // A name no file answers has nothing to delete (and an empty path would name the storage's root).
            $real = realpath($file);
            if (false === $real || false === $publicPath || !str_starts_with($real, $publicPath.'/')) {
                continue;
            }
            Uploader::getFlysystem()->delete(substr($real, \strlen($publicPath) + 1), $attribute->getStorage());
        }
    }
}
