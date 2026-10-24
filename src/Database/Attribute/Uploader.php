<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Base\Exception\InvalidMimeTypeException;
use Base\Exception\InvalidSizeException;
use Base\Service\Locator;
use Base\Validator\Constraints\File as ConstraintsFile;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;


use Doctrine\Persistence\Event\LifecycleEventArgs;
use League\Flysystem\FilesystemException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Uid\Uuid;

use Doctrine\ORM\Event\PreFlushEventArgs;

use function is_file;

/**
 * Class Uploader
 * package Base\Database\Attribute\Uploader
 */

 #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class Uploader extends AbstractAttribute implements ExtensionOptionInterface
{
    protected string $storage;
    protected string $pool;

    protected bool $fetch;
    protected bool $missable;
    protected array $config;
    protected array $formats;
    protected array $mimeTypes;
    protected int $maxSize;

    public function __construct(
        ?string $pool = "_",
        ?string $max_size = null, 
        ?string $storage = null, 
        bool $missable = false, 
        bool $fetch = false,
        array $config = [], 
        array $mime_types = [], 
        array $formats = [])
    {
        $this->pool = $pool;
        $this->storage = $storage ?? $this->getParameterBag()->get("base.uploader.storage");
        $this->missable = $missable;
        $this->fetch = $fetch;
        $this->config = $config;
        $this->mimeTypes = $mime_types;
        $this->formats = $formats;

        $this->maxSize = str2dec($max_size ?? (8 * self::getUploadMaxFilesize()) ) / 8;
    }

    /**
     * The file an upload is handed as (plan "omnibase agnostique", step C3d): what the
     * uploader takes is any \SplFileInfo - Symfony's File and UploadedFile are ones -
     * and what it gives back is Symfony's HttpFoundation File where that component is
     * installed (every Symfony application: an entity's getter typed ?File, a form's
     * FileType get the class they always had), a plain \SplFileInfo elsewhere. The
     * class is named by its string, so the uploader loads nothing of the framework.
     */
    public const SYMFONY_FILE = 'Symfony\\Component\\HttpFoundation\\File\\File';

    public static function file(string $path): \SplFileInfo
    {
        $class = self::SYMFONY_FILE;

        return class_exists($class) ? new $class($path) : new \SplFileInfo($path);
    }

    /** The MIME type of a file: its own getMimeType() when it has one (Symfony's File), symfony/mime's guess otherwise. */
    public static function getFileMimeType(\SplFileInfo $file): ?string
    {
        if (method_exists($file, 'getMimeType')) {
            return $file->getMimeType();
        }
        if (class_exists(MimeTypes::class)) {
            return MimeTypes::getDefault()->guessMimeType($file->getPathname());
        }

        return (is_file($file->getPathname()) ? mime_content_type($file->getPathname()) : false) ?: null;
    }

    /**
     * The largest upload PHP accepts, in bytes: the smaller of post_max_size and
     * upload_max_filesize, as Symfony's UploadedFile::getMaxFilesize() reads them
     * (the same parsing: k, m, g, t suffixes; octal and hexadecimal; 0 for no limit).
     */
    public static function getUploadMaxFilesize(): int|float
    {
        $sizePostMax = self::parseFilesize((string) ini_get('post_max_size'));
        $sizeUploadMax = self::parseFilesize((string) ini_get('upload_max_filesize'));

        return min($sizePostMax ?: \PHP_INT_MAX, $sizeUploadMax ?: \PHP_INT_MAX);
    }

    private static function parseFilesize(string $size): int|float
    {
        if ('' === $size) {
            return 0;
        }

        $size = strtolower($size);

        $max = ltrim($size, '+');
        if (str_starts_with($max, '0x')) {
            $max = intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($size, -1)) {
            case 't': $max *= 1024;
                // no break
            case 'g': $max *= 1024;
                // no break
            case 'm': $max *= 1024;
                // no break
            case 'k': $max *= 1024;
        }

        return $max;
    }

    protected array $ancestorEntity = [];

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        if (!$event->getObjectManager()->contains($entity)) {
            return;
        }

        if (array_key_exists(spl_object_id($entity), $this->getUnitOfWork()->getScheduledEntityInsertions())) {
            // A new entity reached by cascade (a product's images, a review's
            // pictures) is scheduled after preFlush, where uploads happen: its
            // files are still File instances here. Store them now, or the row
            // gets no path and the second-level cache chokes on the File.
            $value = self::getFieldValue($entity, $property);
            $pending = array_filter(is_array($value) ? $value : [$value], fn ($file) => $file instanceof \SplFileInfo);
            if ($pending && $this->uploadFiles($entity, null, $property)) {
                $this->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
            }

            return;
        }

        $this->ancestorEntity[spl_object_id($entity)] = $this->getOldEntity($entity);
    }

    protected function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return mixed|string|null
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @return array|mixed
     */
    public function getFormats()
    {
        return $this->formats;
    }

    /**
     * @return mixed|string|null
     */
    public function storage()
    {
        return $this->storage;
    }

    /**
     * @return array|mixed
     */
    public function formats()
    {
        return $this->formats;
    }

    /**
     * @return array|mixed
     */
    public function mimeTypes()
    {
        return $this->mimeTypes;
    }

    /**
     * @return bool
     */
    public function isImage()
    {
        return !empty(array_filter($this->mimeTypes, fn($type) => str_starts_with($type, "image/")));
    }

    /**
     * @return mixed|string
     */
    public function getPool()
    {
        return $this->pool;
    }

    /**
     * @return bool|mixed
     */
    public function getMissable()
    {
        return $this->missable;
    }

    /**
     * @param mixed $entity
     * @param string $fieldName
     * @return mixed|string|null
     * @throws Exception
     */
    public function getDeclaringEntity(mixed $entity, string $fieldName)
    {
        return $this->getClassMetadataManipulator()->getDeclaringEntity($entity, $fieldName);
    }

    public function getPath(mixed $entity, string $fieldPath, ?string $uuid = null): ?string
    {
        $pool = $this->pool;
        $uuid = $uuid ?? Uuid::v4();

        if ($uuid && !preg_match('/^[a-f0-9\-]{36}$/i', $uuid)) {
            return null;
        }

        // Find field declaring entity
        $entity = $this->getDeclaringEntity($entity, $fieldPath);

        $namespaceDir = "";
        if ($entity) {
            $namespace = (is_string($entity) ? $entity : get_class($entity));
            $namespaceRoot = "Entity";
            $namespaceDir = implode("/", array_map(
                "lcfirst",
                explode(
                    "\\",
                    substr($namespace, strpos($namespace, $namespaceRoot) + strlen($namespaceRoot))
                )
            ));
        }

        $fieldName = $fieldPath;
        if (($dot = strpos($fieldPath, ".")) > 0) {
            $fieldName = trim(substr($fieldPath, $dot + 1));
        }

        return rtrim($pool . $namespaceDir . "/_" . camel2snake($fieldName) . "/" . $uuid, ".");
    }

    /**
     * @param $entity
     * @param $fieldName
     * @return array|mixed|\SplFileInfo|null
     * @throws Exception
     */
    public static function getPublic($entity, $fieldName)
    {
        if (!self::hasAttribute($entity, $fieldName, self::class)) {
            return null;
        }

        /**
         * @var Uploader $that
         */
        $that = self::getAttribute($entity, $fieldName, self::class);
        if (!$that) {
            return null;
        }

        $field = self::getFieldValue($entity, $fieldName);
        if (!$field) {
            return null;
        }

        if (is_array($field)) {

            // An #[Uploader]-attributed field isn't always exclusively a
            // file/UUID list: SettingIntl::$value is the general-purpose
            // JSON storage for EVERY setting type, file-backed or not, so
            // an array value here can just as easily be an arbitrary
            // nested structure (e.g. a stored admin.layout.* config -
            // {version, columns, items: [...]}) that was never meant to
            // go through file resolution at all.
            //
            // Validate ALL elements before touching any of them, rather
            // than processing per-element with a per-element skip: an
            // element that isn't itself a string or File (e.g. a nested
            // array - one item of that layout config, or a plain scalar
            // like the 'columns' int alongside it) isn't a uuid/File
            // reference, so the WHOLE field isn't a file list. Two
            // distinct failure modes were found live, both from the same
            // root cause: passing a nested ARRAY element straight into
            // getPath()'s ?string $uuid parameter is a hard TypeError
            // (crashed every SECOND save of any dashboard/sidebar layout -
            // preUpdate, not prePersist, is what reads this decorated
            // getter, so the very first save/insert never touched this
            // code path at all); merely SKIPPING that one bad element
            // while still processing plain scalar siblings (e.g.
            // 'columns') is not enough either - resolvePublicOrRoute()'s
            // missable:true fallback echoes an unresolvable scalar back
            // as itself, silently reconstructing a corrupted, flattened
            // $pathList (e.g. [5, 1] - just the scalar siblings) instead
            // of preserving the real nested value. Returning null here
            // instead lets getValue()'s own `?? $this->value` fallback
            // supply the untouched raw value, exactly like the scalar
            // branch below already does for a single non-file value.
            foreach ($field as $uuidOrFile) {
                if (!is_string($uuidOrFile) && !($uuidOrFile instanceof \SplFileInfo)) {
                    return null;
                }
            }

            $pathList = [];
            foreach ($field as $uuidOrFile) {
                $uuidOrFile = is_string($uuidOrFile) && !str_contains($uuidOrFile, "://") && is_file($uuidOrFile) ? self::file($uuidOrFile) : $uuidOrFile;
                if ($uuidOrFile instanceof \SplFileInfo) {
                    $pathList[] = $that->getFlysystem()->getPublic($uuidOrFile->getPathname(), $that->getStorage());
                    continue;
                }

                $path = $that->getPath($entity, $fieldName, $uuidOrFile);

                $pathPublic = self::resolvePublicOrRoute($that, $path, $uuidOrFile);
                if ($pathPublic) {
                    $pathList[] = $pathPublic;
                }
            }

            $pathList = array_filter($pathList);
            return empty($pathList) ? null : $pathList;

        } else {

            $uuidOrFile = is_string($field) && !str_contains($field, "://") && is_file($field) ? self::file($field) : $field;
            if ($uuidOrFile instanceof \SplFileInfo) {
                return $that->getFlysystem()->getPublic($uuidOrFile->getPathname(), $that->getStorage());
            }

            if (!is_stringeable($uuidOrFile)) {
                return null;
            }

            $path = $that->getPath($entity, $fieldName, $uuidOrFile);
            return self::resolvePublicOrRoute($that, $path, $uuidOrFile);
        }
    }

    /**
     * Resolve an entity-file storage path to a servable URL.
     *
     * Local storages expose files through public/ symlinks, so
     * Flysystem::getPublic() returns a direct static URL — unchanged behavior.
     * Remote storages (S3/MinIO) have no local file to symlink, so getPublic()
     * returns null; route those through the /images resolver instead, carrying
     * the source storage in-config so MediaService::filter() streams the source
     * from the remote, applies filters, and caches the derivative locally
     * (fast on repeat). Remote is checked FIRST so a stale local copy left over
     * from a storage migration cannot shadow the configured source of truth.
     * Falls back to the raw value when the field is "missable", otherwise null.
     */
    protected static function resolvePublicOrRoute(Uploader $that, ?string $path, mixed $fallback): mixed
    {
        if ($path === null) {
            return $that->getMissable() ? $fallback : null;
        }

        if ($that->getFlysystem()->isRemote($that->getStorage())) {
            $routed = Locator::getMediaService()?->image($path, ["storage" => $that->getStorage()]);
            if ($routed) {
                return $routed;
            }
        }

        $pathPublic = $that->getFlysystem()->getPublic($path, $that->getStorage());
        if ($pathPublic) {
            return $pathPublic;
        }

        return $that->getMissable() ? $fallback : null;
    }

    /**
     * @param $entity
     * @param $fieldName
     * @return array
     */
    public static function getMimeTypes($entity, $fieldName): array
    {
        if (!self::hasAttribute($entity, $fieldName, self::class)) {
            return [];
        }

        $that = self::getAttribute($entity, $fieldName, self::class);
        if (!$that) {
            return [];
        }

        return $that->mimeTypes;
    }

    /**
     * @param $entity
     * @param $fieldName
     * @return int
     */
    public static function getMaxFilesize($entity, $fieldName): int
    {
        $maxSize = self::getUploadMaxFilesize();
        if (self::hasAttribute($entity, $fieldName, self::class)) {
            $that = self::getAttribute($entity, $fieldName, self::class);
            $maxSize = min($that->maxSize ?: $maxSize, $maxSize);
        }

        if (self::hasAttribute($entity, $fieldName, ConstraintsFile::class)) {
            $that = self::getAttribute($entity, $fieldName, ConstraintsFile::class);
            $maxSize = min($that->getMaxSize() ?: $maxSize, $maxSize);
        }

        return $maxSize;
    }

    protected static $tmpHashTable = [];

    /**
     * Remote objects are copied into a tmpfile() so callers get a real file (see file()). One
     * handle per (adapter, uuid), kept open for the request because closing a
     * tmpfile() deletes it.
     *
     * Until 2026-09-14 every call fwrite()'d the object into the SAME handle again
     * without rewinding: a second get() of the same field within one request - an
     * entity getter and FileType rendering the same upload, say - handed back a
     * file holding the content twice over, i.e. a corrupt image or document, after
     * paying for a second full download. An upload never changes under its uuid, so
     * once materialized the copy is simply reused.
     *
     * The table is request-scoped; see forgetTemporaryFiles().
     */
    public static function materializeTemporaryFile(string $index, callable $read): ?\SplFileInfo
    {
        if (!array_key_exists($index, self::$tmpHashTable)) {
            // Read BEFORE opening the handle, so a failing read leaves nothing behind.
            $content = (string) $read();

            $handle = tmpfile();
            if ($handle === false) {
                return null;
            }

            fwrite($handle, $content);
            fflush($handle);
            self::$tmpHashTable[$index] = $handle;
        }

        return self::file(stream_get_meta_data(self::$tmpHashTable[$index])['uri']);
    }

    /**
     * Close every materialized copy; closing a tmpfile() deletes it.
     *
     * Under FPM, or FrankenPHP classic mode, statics die with the request and this
     * never mattered. A process that outlives the request - FrankenPHP worker mode,
     * a messenger consumer - kept every handle open forever (file descriptors and
     * /tmp space growing without bound), and because spl_object_hash() values are
     * recycled once an adapter is freed, a later request could even be handed one
     * object's bytes under another's key.
     *
     * Called at the start of every main request and on kernel.reset, by
     * Base\Subscriber\RequestScopedStateSubscriber.
     */
    public static function forgetTemporaryFiles(): void
    {
        foreach (self::$tmpHashTable as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        self::$tmpHashTable = [];
    }

    /**
     * @param $entity
     * @param string $fieldName
     * @return array|mixed|\SplFileInfo|null
     * @throws FilesystemException
     */
    public static function get($entity, string $fieldName)
    {
        if ($entity === null) {
            return null;
        }

        if (!self::hasAttribute($entity, $fieldName, self::class)) {
            return null;
        }

        /**
         * @var Uploader $that
         */
        $that = self::getAttribute($entity, $fieldName, self::class);
        if (!$that) {
            return null;
        }

        // The storage as omnidrive's drive (step R28): a local one hands its file, any other a temporary copy.
        $drive = $that->getFlysystem()->getDrive($that->storage);
        $local = $drive->capabilities()->local;

        $fieldValue = self::getFieldValue($entity, $fieldName);
        if (!$fieldValue) {
            return null;
        }
        if (!is_array($fieldValue)) {
            $fieldValue = [$fieldValue];
        }

        $fileList = [];
        foreach ($fieldValue as $uuidOrFile) {
            if ($uuidOrFile instanceof \SplFileInfo) {
                $fileList[] = $uuidOrFile;
                continue;
            }

            // Special case for local adapter, if not found.. it requires a temp file..
            $path = $that->getPath($entity, $fieldName, $uuidOrFile);
            if (!$path) {
                $fileList[] = null;
                continue;
            }

            if ($local) {
                if ($drive->exists($path)) {
                    $fileList[] = self::file($that->getFlysystem()->prefixPath($path, $that->getStorage()));
                }

                continue;
            }

            // Copy file content in a tmp file
            $index = $that->storage . ":" . $uuidOrFile;
            $fileList[] = self::materializeTemporaryFile(
                $index,
                fn () => $that->getFlysystem()->read($path, $that->getStorage())
            );
        }

        if (count($fileList) < 1) {
            return null;
        }
        if (count($fileList) < 2) {
            return $fileList[0];
        }
        return $fileList;
    }

    /**
     * @param $entity
     * @param $oldEntity
     * @param string|null $fieldName
     * @return bool
     * @throws InvalidMimeTypeException
     * @throws InvalidSizeException
     */
    protected function uploadFiles($entity, $oldEntity, ?string $fieldName = null)
    {
        $new = self::getFieldValue($entity, $fieldName);

        $newList = is_array($new) ? $new : [$new];
        $newListStringable = array_filter(array_map(fn($e) => is_stringeable($e), $newList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // These elements are not meant to be uploaded
        if (count($newList) != count($newListStringable)) {
            return false;
        }

        // File instances are filtered below, in case of UOW manipulation..
        $old = array_key_exists(spl_object_id($entity), $this->ancestorEntity) ? self::getFieldValue($this->ancestorEntity[spl_object_id($entity)], $fieldName) : self::getFieldValue($oldEntity, $fieldName);
        $oldList = is_array($old) ? $old : [$old];
        //$oldListStringable = array_filter(array_map(fn ($e) => is_stringeable($e), $oldList));

        // No change in the list.. (NB: Not good approach in case of UOW manipulation)
        $potentialMemoryLeak = array_filter($oldList, fn($f) => $f instanceof \SplFileInfo);
        if ($potentialMemoryLeak && $newList !== $oldList) {
            throw new Exception(get_class(reset($potentialMemoryLeak)) . " instance found the old list of " . get_class($entity) . "::" . $fieldName . "\n Did you called unit of work change set ? Please process file manually");
        } elseif (!$potentialMemoryLeak && $newList === $oldList) {
            return true;
        }

        $oldList = array_filter($oldList, fn($f) => !$f instanceof \SplFileInfo);

        // Nothing to upload, empty field..
        if ($newList === null) {
            self::setPropertyValue($entity, $fieldName, null);
            return true;
        }

        //
        // Go fetch URL if allowed
        foreach ($newList as $index => $entry) {
            if (filter_var($entry, FILTER_VALIDATE_URL)) {
                if (!$this->fetch) {
                    return true;
                }
                $newList[$index] = self::file(fetch_url($entry));
            }
        }

        $entityId = $entity->getId();
        $entityId = $entityId ? "#" . $entityId : "";

        $fileList = []; // Field value can be an array or just a single path
        $uploadList = array_values(array_intersect($newList, $oldList));
        foreach (array_union($uploadList, array_diff($newList, $oldList)) as $index => $entry) {

            //
            // In case of string casting, and UploadedFile might be returned as a string..
            $file = is_string($entry) && !str_contains($entry, "://") && is_file($entry) ? self::file($entry) : $entry;
            if (!$file instanceof \SplFileInfo) {
                if ($this->getMissable()) {
                    $fileList[] = $entry;
                } elseif (is_uuidv4($entry)) {
                    $fileList[] = $entry;
                }
                continue;
            }

            //
            // Check size restriction
            if (!file_exists($file->getPathname())) {
                throw new FileNotFoundException("File got erased \"$fieldName\" in " . get_class($entity) . ".");
            }

            if ($file->getSize() > $this->maxSize) {
                throw new InvalidSizeException("Invalid filesize \"" . $entry . "\" exception for field \"$fieldName\" in " . get_class($entity) . " " . $entityId . " (" . $file->getSize() . "B > " . $this->maxSize . "B)");
            }

            //
            // Check mime restriction
            $compatibleMimeType = empty($this->mimeTypes);
            foreach ($this->mimeTypes as $mimeType) {
                $compatibleMimeType |= preg_match("/" . str_replace("/", "\/", $mimeType) . "/", self::getFileMimeType($file));
            }

            if (!$compatibleMimeType) {
                throw new InvalidMimeTypeException("Invalid MIME type \"" . self::getFileMimeType($file) . "\" received for field \"$fieldName\" in " . get_class($entity) . " " . $entityId . " (expected: \"" . implode(", ", $this->mimeTypes) . "\").");
            }

            //
            // Upload files
            $path = $this->getPath($entity, $fieldName);
            $contents = (file_get_contents($file->getPathname()));

            if (!$this->getFlysystem()->write($path, $contents, $this->getStorage(), $this->getConfig())) {
                throw new InvalidMimeTypeException("Failed to write \"" . $path . "\" in " . get_class($entity) . ".");
            }

            $fileList[] = basename($path);
        }

        self::setPropertyValue($entity, $fieldName, !is_array($new) ? $fileList[0] ?? null : $fileList);
        return true;
    }

    /**
     * @param $entity
     * @param $oldEntity
     * @param string $fieldName
     * @return bool
     * @throws Exception
     */
    protected function deleteFiles($entity, $oldEntity, string $fieldName)
    {
        $new = self::getFieldValue($entity, $fieldName);
        $newList = is_array($new) ? $new : [$new];
        $newListStringable = array_filter(array_map(fn($e) => is_stringeable($e), $newList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // This means that these elements are not meant to be uploaded
        if (count($newList) != count($newListStringable)) {
            return false;
        }

        $old = $entity !== null && array_key_exists(spl_object_id($entity), $this->ancestorEntity) ? self::getFieldValue($this->ancestorEntity[spl_object_id($entity)], $fieldName) : self::getFieldValue($oldEntity, $fieldName);
        $oldList = is_array($old) ? $old : [$old];
        $oldListStringable = array_filter(array_map(fn($e) => is_stringeable($e), $oldList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // This means that these elements are not meant to be uploaded
        if (count($oldList) != count($oldListStringable)) {
            return false;
        }

        if (!$oldList) {
            return false;
        }

        foreach (array_diff($oldList, $newList) as $file) {
            if (!$file) {
                continue;
            }

            if ($file instanceof \SplFileInfo) {
                $path = $file->getRealPath();
            } else {
                $path = $this->getPath($entity ?? $oldEntity ?? null, $fieldName, $file);
            }

            if ($path) {
                $this->getFlysystem()->delete($path, $this->getStorage());
            }
        }

        return true;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AttributeReader::TARGET_PROPERTY);
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     * @throws InvalidMimeTypeException
     * @throws InvalidSizeException
     */
    public function preFlush(PreFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null): void
    {
        try {
            $this->uploadFiles($entity, null, $property);
        } catch (Exception $e) {
            $this->deleteFiles(null, $entity, $property);
            self::setFieldValue($entity, $property, null);

            throw $e;
        }
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     * @throws Exception
     */
    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $oldEntity = $this->getOldEntity($entity);
        try {
            if ($this->uploadFiles($entity, $oldEntity, $property)) {
                $this->deleteFiles($entity, $oldEntity, $property);
            }
        } catch (Exception $e) {
            $this->deleteFiles($oldEntity, $entity, $property);
            $old = self::getFieldValue($oldEntity, $property);

            self::setFieldValue($entity, $property, $old);
        }

        $this->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     */
    public function postRemove(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $this->deleteFiles(null, $entity, $property);
    }
}
