<?php

namespace Base\Field\Behaviour;

use Base\Database\Attribute\Uploader;
use Base\Database\Entity\EntityHydrator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\UnitOfWorks;
use Base\Field\Kind;
use Base\Form\Definition\Properties;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;

/**
 * Records made from uploads (Kind::ASSOCIATION_FILE, plan "omnibase agnostic", step F5d): a gallery's
 * photos, a photo's image - what omnibase's AssociationFileType decided, on every host. The field is a
 * compound holding one upload field (Upload: file, image or avatar) named by the first part of
 * `entity_file`, the path from the related record to its file ("image.source": a photo's image's
 * source). What the type decided itself is here, and it asks it:
 *
 * - options(): its defaults and normalizers (the class required, `entity_file` required, `form_type` an
 *   upload's: FileType, ImageType, AvatarType or a variant's name), variant();
 * - child() and path(): the upload field's name and the file's path in a related record;
 * - files(): the related records' public files, what the upload field shows;
 * - entries(): each stored file's name by its record's id (the block's data-entry-id);
 * - childOptions(): the upload field's options, as the type adds it;
 * - records(): what was sent made into the related records - a file sent a new record (hydrated, then
 *   given `entity_data`, a callable or values, else its file), a stored file's name its record kept.
 *
 * And for the hosts without Symfony's forms (the neutral FormView and FormBinder): bind(), the related
 * records written into the record (a to-many association replaced, its inverse side told its owner, as
 * the type writes a PersistentCollection), read from the entity's shape.
 */
final class AssociationFile
{
    /** AssociationFileType's defaults. */
    public const DEFAULTS = [
        'class' => null,
        'form_type' => Upload::FILE,
        'entity_file' => null,
        'entity_data' => [],
        'multiple' => null,
        'allow_delete' => false,
        'allow_delete[confirmation]' => true,
        'href' => null,
        'max_size' => null,
        'max_files' => null,
        'mime_types' => null,
        'sortable' => null,
    ];

    /** The upload kinds a related record's file is drawn with. */
    public const VARIANTS = [Upload::FILE => Kind::FILE, Upload::IMAGE => Kind::IMAGE, Upload::AVATAR => Kind::AVATAR];

    private function __construct()
    {
    }

    /**
     * The options with the type's defaults, normalized as it normalizes them: no data class for several
     * records, a class and the file's path required, an upload's form type.
     *
     * @param array<string, mixed> $given
     *
     * @return array<string, mixed>
     */
    public static function options(array $given): array
    {
        $options = array_replace(self::DEFAULTS, $given);
        if ($options['multiple'] ?? false) {
            $options['data_class'] = null;
        }
        self::variant($options);
        if (null === $options['class']) {
            throw new \InvalidArgumentException('Option "class" must be defined');
        }
        if (null === $options['entity_file']) {
            throw new \InvalidArgumentException('Option "entity_file" must be pointing to the file field');
        }

        return $options;
    }

    /**
     * The upload's variant its `form_type` names (Upload::FILE, IMAGE, AVATAR): a variant's name, or the
     * type's class - FileType, ImageType, AvatarType, under any namespace -; anything else is refused, as the type
     * refuses a form type that is no FileType.
     *
     * @param array<string, mixed> $options
     */
    public static function variant(array $options): string
    {
        $type = $options['form_type'] ?? Upload::FILE;
        if (\is_string($type) && isset(self::VARIANTS[$type])) {
            return $type;
        }
        $short = \is_string($type) ? strtolower(preg_replace('/Type$/', '', substr((string) strrchr('\\'.$type, '\\'), 1)) ?? '') : '';
        if (isset(self::VARIANTS[$short])) {
            return $short;
        }

        throw new \InvalidArgumentException('Option "form_type" must inherit from FileType');
    }

    /** The upload kind its file is drawn with (Kind::FILE, IMAGE, AVATAR). @param array<string, mixed> $options */
    public static function kind(array $options): string
    {
        return self::VARIANTS[self::variant($options)];
    }

    /** The upload field's name: the first part of `entity_file` ("image" of "image.source"). @param array<string, mixed> $options */
    public static function child(array $options): string
    {
        return explode('.', (string) ($options['entity_file'] ?? ''))[0];
    }

    /** The file's path in a related record (`entity_file`). @param array<string, mixed> $options */
    public static function path(array $options): string
    {
        return (string) ($options['entity_file'] ?? '');
    }

    /**
     * The related records as a list: the collection's, the record itself, none.
     *
     * @return list<object>
     */
    public static function list(mixed $records): array
    {
        if (null === $records) {
            return [];
        }
        if (is_iterable($records)) {
            return array_values(array_filter(\is_array($records) ? $records : iterator_to_array($records, false), 'is_object'));
        }

        return \is_object($records) ? [$records] : [];
    }

    /**
     * The related records' public files (Uploader::getPublic(), the first of several), what the upload
     * field shows (the type's PRE_SET_DATA).
     *
     * @return list<string>
     */
    public static function files(mixed $records, array $options): array
    {
        $path = self::path($options);

        return array_values(array_filter(array_map(static function (object $record) use ($path) {
            $public = Uploader::getPublic($record, $path);

            return \is_array($public) ? begin($public) ?? null : $public;
        }, self::list($records))));
    }

    /**
     * Each stored file's name by its record's id, as the block's data-entry-id carries it (the type's
     * finishView(): for several records only, [] otherwise); JSON-encoded.
     */
    public static function entries(mixed $records, array $options): string
    {
        $path = self::path($options);
        $entries = [];
        if (is_iterable($records) && !\is_array($records)) {
            foreach (self::list($records) as $record) {
                try {
                    $file = Properties::get($record, $path);
                } catch (\UnexpectedValueException) {
                    $file = null;
                }
                if (is_iterable($file)) {
                    $file = \is_array($file) ? $file : iterator_to_array($file, false);
                }
                $file = \is_array($file) ? begin($file) ?? null : $file;
                if (null !== $file) {
                    $entries[basename((string) $file)] = method_exists($record, 'getId') ? $record->getId() : null;
                }
            }
        }

        return (string) json_encode($entries);
    }

    /**
     * The upload field's options, as the type adds it: its class, whether it is removable, required
     * (where the record's column may not be empty), several and in order, its address, its size, its
     * number of files and types.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function childOptions(array $options, bool $required): array
    {
        return [
            'class' => $options['class'],
            'allow_delete' => $options['allow_delete'],
            'required' => $required,
            'multiple' => $options['multiple'] ?? false,
            'href' => $options['href'] ?? null,
            'max_size' => $options['max_size'],
            'max_files' => $options['max_files'],
            'mime_types' => $options['mime_types'],
            'sortable' => $options['sortable'],
        ];
    }

    /**
     * What was sent made into the related records (the type's mapFormsToData()): for several, each file
     * sent a new record, each stored file's name the record holding it; for one, the record (or a new one)
     * given the file. A record made is given `entity_data` - a callable ($record, $owner, $file), or values
     * hydrated with the file -, else its file. Null where nothing is sent for one.
     *
     * @param mixed       $files    what the upload field reads (Upload::reverse()): a file, a name, a list of them
     * @param list<object>|object|null $existing the records held before
     * @param array<string, mixed> $options
     *
     * @return list<object>|object|null
     */
    public static function records(mixed $files, mixed $existing, array $options, ?object $owner, EntityHydratorInterface $hydrator): array|object|null
    {
        $class = (string) $options['class'];
        $path = self::path($options);
        $data = $options['entity_data'] ?? [];
        $make = static fn (): object => $hydrator->hydrate($class, [], ['uuid', 'translations'], EntityHydrator::CONSTRUCT);

        if ($options['multiple'] ?? false) {
            $held = self::list($existing);
            $names = array_map(static function (object $record) use ($path): ?string {
                try {
                    $value = Properties::get($record, $path);
                } catch (\UnexpectedValueException) {
                    return null;
                }
                $value = \is_array($value) ? first($value) : $value;

                return null !== $value ? basename((string) $value) : null;
            }, $held);

            $records = [];
            foreach (\is_array($files) ? $files : (null !== $files ? [$files] : []) as $file) {
                $record = null;
                if ($file instanceof \SplFileInfo) {
                    $record = $make();
                } elseif (\is_string($file) && false !== ($position = array_search($file, $names, true))) {
                    $record = $held[$position];
                }
                if (null === $record) {
                    continue;
                }
                if ($data) {
                    $record = \is_callable($data) ? ($data($record, $owner, $file) ?? $record) : $hydrator->hydrate($record, array_merge($data, [$path => $file]), [], EntityHydrator::CONSTRUCT);
                } elseif (method_exists($record, 'getId') && null === $record->getId()) {
                    Properties::set($record, $path, $file);
                }
                $records[] = $record;
            }

            return $records;
        }

        if (empty($files)) {
            return null;
        }
        $record = \is_object($existing) && !is_iterable($existing) ? $existing : $make();

        return \is_callable($data) ? $data($record, $owner, $files) : $hydrator->hydrate($record, array_merge($data ?: [], [$path => $files]), [], EntityHydrator::CONSTRUCT);
    }

    /**
     * The related records written into the record (the neutral FormBinder): several replace the
     * collection's (an inverse side's records told their owner: its mappedBy, read from the entity's
     * shape; those left told none), one is set where something was sent.
     *
     * @param list<object>|object|null $records records() answer
     */
    public static function bind(object $owner, string $property, array|object|null $records, array $options): void
    {
        if (!($options['multiple'] ?? false)) {
            // Written where it is another record (Symfony's forms write a value held by reference only when it changes).
            if (null !== $records && (!Properties::isReadable($owner, $property) || Properties::get($owner, $property) !== $records)) {
                Properties::set($owner, $property, $records);
            }

            return;
        }
        $records = self::list($records);
        $mappedBy = self::mappedBy($owner, $property);
        $collection = Properties::get($owner, $property);
        if ($collection instanceof \Doctrine\Common\Collections\Collection) {
            if (null !== $mappedBy) {
                foreach ($collection as $entry) {
                    if (!\in_array($entry, $records, true)) {
                        Properties::set($entry, $mappedBy, null);
                    }
                }
            }
            $collection->clear();
            foreach ($records as $record) {
                $collection->add($record);
                if (null !== $mappedBy) {
                    Properties::set($record, $mappedBy, $owner);
                }
            }

            return;
        }
        foreach ($records as $record) {
            if (null !== $mappedBy) {
                Properties::set($record, $mappedBy, $owner);
            }
        }
        Properties::set($owner, $property, $records);
    }

    /**
     * Whether the upload field is required: where the record's column may not be empty (an association's,
     * as the type reads Doctrine's mapping, has no such word: required).
     */
    public static function required(?string $dataClass, string $property): bool
    {
        $shape = self::shapes()?->has((string) $dataClass) ? self::shapes()->shape((string) $dataClass) : null;
        if (null === $shape || !$shape->hasField($property)) {
            return true;
        }

        return !$shape->entity->field($property)->nullable;
    }

    /** Whether the record's property holds several (a to-many association, named by itself or its alias). */
    public static function isToMany(?string $dataClass, string $property): ?bool
    {
        $shapes = self::shapes();
        if (null === $dataClass || null === $shapes || !$shapes->has($dataClass)) {
            return null;
        }
        $property = (new \Base\Database\Mapping\EntityFields($shapes))->name($dataClass, $property) ?? $property;
        $shape = $shapes->shape($dataClass);

        return $shape->hasAssociation($property) ? $shape->isToMany($property) : null;
    }

    /** The property of a related record naming its owner, where the owner's side is the inverse one. */
    private static function mappedBy(object $owner, string $property): ?string
    {
        $shapes = self::shapes();
        if (null === $shapes || !$shapes->has($owner::class)) {
            return null;
        }
        $property = (new \Base\Database\Mapping\EntityFields($shapes))->name($owner::class, $property) ?? $property;
        $shape = $shapes->shape($owner::class);

        return $shape->hasAssociation($property) && !$shape->isOwningSide($property) ? $shape->mappedBy($property) : null;
    }

    private static function shapes(): ?\Base\Database\Mapping\EntityShapes
    {
        $unitOfWork = Runtime::find(UnitOfWork::class);
        if (!$unitOfWork instanceof UnitOfWork) {
            return null;
        }
        try {
            return UnitOfWorks::shapes($unitOfWork);
        } catch (\Throwable) {
            return null;
        }
    }
}
