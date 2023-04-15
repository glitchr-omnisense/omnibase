<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Exception\InvalidMimeTypeException;
use Base\Exception\InvalidSizeException;
use Base\Validator\Constraints\File as ConstraintsFile;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

use function is_file;

/**
 * Class Uploader
 * package Base\Annotations\Annotation\Uploader
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("storage",    type = "string"),
 *   @Attribute("pool",       type = "string"),
 *   @Attribute("missable",   type = "boolean"),
 *   @Attribute("fetch",      type = "boolean"),
 *   @Attribute("max_size",   type = "string"),
 *   @Attribute("mime_types", type = "array"),
 *   @Attribute("formats",    type = "array")
 * })
 */
class Uploader extends AbstractAnnotation
{
    protected string $storage;
    protected string $pool;

    protected bool $fetch;
    protected bool $missable;
    protected array $config;
    protected array $formats;
    protected array $mimeTypes;
    protected int   $maxSize;

    public function __construct(array $data)
    {
        $this->pool      = (!empty($data["pool"]   ?? null) ? $data["pool"] : "default");

        $this->storage   = $data["storage"]    ?? null;
        $this->missable  = $data["missable"]   ?? false;
        $this->fetch     = $data["fetch"]      ?? false;
        $this->config    = $data["config"]     ?? [];
        $this->mimeTypes = $data["mime_types"] ?? [];
        $this->formats   = $data["formats"]    ?? [];

        $this->maxSize   = str2dec($data["max_size"] ?? 8*UploadedFile::getMaxFilesize())/8;
    }

    protected array $ancestorEntity = [];
    public function onFlush(OnFlushEventArgs $args, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        if (!$this->getEntityManager()->contains($entity)) {
            return;
        }

        if (array_key_exists(spl_object_id($entity), $this->getUnitOfWork()->getScheduledEntityInsertions())) {
            return;
        }

        $this->ancestorEntity[spl_object_id($entity)] = $this->getOldEntity($entity);
    }

    protected function getConfig(): array
    {
        return $this->config;
    }
    public function getStorage()
    {
        return $this->storage;
    }
    public function getFormats()
    {
        return $this->formats;
    }

    public function storage()
    {
        return $this->storage;
    }
    public function formats()
    {
        return $this->formats;
    }
    public function mimeTypes()
    {
        return $this->mimeTypes;
    }

    public function isImage()
    {
        return !empty(array_filter($this->mimeTypes, fn ($type) => str_starts_with($type, "image/")));
    }
    public function getPool()
    {
        return $this->pool;
    }
    public function getMissable()
    {
        return $this->missable;
    }

    public function getDeclaringEntity(mixed $entity, string $fieldName)
    {
        return $this->getClassMetadataManipulator()->getDeclaringEntity($entity, $fieldName);
    }

    public function getPath(mixed $entity, string $fieldPath, ?string $uuid = null): ?string
    {
        $pool     = $this->pool;
        $uuid     = $uuid ?? Uuid::v4();

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
                substr($namespace, strpos($namespace, $namespaceRoot)+strlen($namespaceRoot))
            )
            ));
        }

        $fieldName = $fieldPath;
        if (($dot = strpos($fieldPath, ".")) > 0) {
            $fieldName = trim(substr($fieldPath, $dot+1));
        }

        return rtrim($pool . $namespaceDir . "/_".camel2snake($fieldName)."/" . $uuid, ".");
    }

    public static function getPublic($entity, $fieldName)
    {
        if (!self::hasAnnotation($entity, $fieldName, self::class)) {
            return null;
        }

        /**
         * @var Uploader
         */
        $that = self::getAnnotation($entity, $fieldName, self::class);
        if (!$that) {
            return null;
        }

        $field = self::getFieldValue($entity, $fieldName);
        if (!$field) {
            return null;
        }

        if (is_array($field)) {
            $pathList = [];
            foreach ($field as $uuidOrFile) {
                $uuidOrFile = is_string($uuidOrFile) && !str_contains($uuidOrFile, "://") && is_file($uuidOrFile) ? new File($uuidOrFile) : $uuidOrFile;
                if ($uuidOrFile instanceof File) {
                    $pathList[] = $that->getFlysystem()->getPublic($uuidOrFile->getPathname(), $that->getStorage());
                    continue;
                }

                $path = $that->getPath($entity, $fieldName, $uuidOrFile);

                $pathPublic = $that->getFlysystem()->getPublic($path, $that->getStorage());
                if ($pathPublic) {
                    $pathList[] = $pathPublic;
                } elseif ($that->getMissable()) {
                    $pathList[] = $uuidOrFile;
                }
            }

            $pathList = array_filter($pathList);
            return empty($pathList) ? null : $pathList;
        } else {
            $uuidOrFile = is_string($field) && !str_contains($field, "://") && is_file($field) ? new File($field) : $field;
            if ($uuidOrFile instanceof File) {
                return $that->getFlysystem()->getPublic($uuidOrFile->getPathname(), $that->getStorage());
            }

            if (!is_stringeable($uuidOrFile)) {
                return null;
            }

            $path = $that->getPath($entity, $fieldName, $uuidOrFile);
            $pathPublic = $that->getFlysystem()->getPublic($path, $that->getStorage());
            if ($pathPublic) {
                return $pathPublic;
            }

            return $that->getMissable() ? $uuidOrFile : null;
        }
    }

    public static function getMimeTypes($entity, $fieldName): array
    {
        if (!self::hasAnnotation($entity, $fieldName, self::class)) {
            return [];
        }

        $that = self::getAnnotation($entity, $fieldName, self::class);
        if (!$that) {
            return [];
        }

        return $that->mimeTypes;
    }

    public static function getMaxFilesize($entity, $fieldName): int
    {
        $maxSize = UploadedFile::getMaxFilesize();
        if (self::hasAnnotation($entity, $fieldName, self::class)) {
            $that = self::getAnnotation($entity, $fieldName, self::class);
            $maxSize = min($that->maxSize ?: $maxSize, $maxSize);
        }

        if (self::hasAnnotation($entity, $fieldName, ConstraintsFile::class)) {
            $that = self::getAnnotation($entity, $fieldName, ConstraintsFile::class);
            $maxSize = min($that->getMaxSize() ?: $maxSize, $maxSize);
        }

        return $maxSize;
    }

    protected static $tmpHashTable = [];
    public static function get($entity, string $fieldName)
    {
        if ($entity === null) {
            return null;
        }

        if (!self::hasAnnotation($entity, $fieldName, self::class)) {
            return null;
        }

        /**
         * @var Uploader
         */
        $that       = self::getAnnotation($entity, $fieldName, self::class);
        if (!$that) {
            return null;
        }

        $operator = $that->getFlysystem()->getOperator($that->storage);
        $adapter  = $that->getFlysystem()->getAdapter($operator);

        $fieldValue = self::getFieldValue($entity, $fieldName);
        if (!$fieldValue) {
            return null;
        }
        if (!is_array($fieldValue)) {
            $fieldValue = [$fieldValue];
        }

        $fileList = [];
        foreach ($fieldValue as $uuidOrFile) {
            if ($uuidOrFile instanceof File) {
                $fileList[] = $uuidOrFile;
                continue;
            }

            // Special case for local adapter, if not found.. it requires a temp file..
            $path = $that->getPath($entity, $fieldName, $uuidOrFile);
            if (!$path) {
                $fileList[] = null;
                continue;
            }

            if ($adapter instanceof LocalFilesystemAdapter) {
                if ($operator->fileExists($path)) {
                    $fileList[] = new File($that->getFlysystem()->prefixPath($path));
                }

                continue;
            }

            // Copy file content in a tmp file
            $index = spl_object_hash($adapter) . ":" . $uuidOrFile;
            if (!array_key_exists($index, self::$tmpHashTable)) {
                self::$tmpHashTable[$index] = tmpfile();
            }

            fwrite(self::$tmpHashTable[$index], $that->getFlysystem()->read($path, $that->getStorage()));
            $fileList[] = new File(stream_get_meta_data(self::$tmpHashTable[$index])['uri']);
        }

        if (count($fileList) < 1) {
            return null;
        }
        if (count($fileList) < 2) {
            return $fileList[0];
        }
        return $fileList;
    }

    protected function uploadFiles($entity, $oldEntity, ?string $fieldName = null)
    {
        $new = self::getFieldValue($entity, $fieldName);
        $newList = is_array($new) ? $new : [$new];
        $newListStringable = array_filter(array_map(fn ($e) => is_stringeable($e), $newList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // These elements are not meant to be uploaded
        if (count($newList) != count($newListStringable)) {
            return false;
        }

        // File instances are filtered below, in case of UOW manipulation..
        $old = array_key_exists(spl_object_id($entity), $this->ancestorEntity) ? self::getFieldValue($this->ancestorEntity[spl_object_id($entity)], $fieldName) : self::getFieldValue($oldEntity, $fieldName);
        $oldList = is_array($old) ? $old : [$old];
        $oldListStringable = array_filter(array_map(fn ($e) => is_stringeable($e), $oldList));

        // No change in the list.. (NB: Not good approach in case of UOW manipulation)
        $potentialMemoryLeak = array_filter($oldList, fn ($f) => $f instanceof File);
        if ($potentialMemoryLeak && $newList !== $oldList) {
            throw new Exception(File::class." instance found the old list of ".get_class($entity)."::".$fieldName."\n Did you called unit of work change set ? Please process file manually");
        } elseif (!$potentialMemoryLeak && $newList === $oldList) {
            return true;
        }

        $oldList = array_filter($oldList, fn ($f) => !$f instanceof File);

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
                $newList[$index] = new File(fetch_url($entry));
            }
        }

        $entityId = $entity->getId();
        $entityId = $entityId ? "#".$entityId : "";

        $fileList = []; // Field value can be an array or just a single path
        $uploadList = array_values(array_intersect($newList, $oldList));
        foreach (array_union($uploadList, array_diff($newList, $oldList)) as $index => $entry) {

            //
            // In case of string casting, and UploadedFile might be returned as a string..
            $file = is_string($entry) && !str_contains($entry, "://") && is_file($entry) ? new File($entry) : $entry;
            if (!$file instanceof File) {
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
                throw new FileNotFoundException("File got erased \"$fieldName\" in ".get_class($entity).".");
            }

            if ($file->getSize() > $this->maxSize) {
                throw new InvalidSizeException("Invalid filesize \"".$entry."\" exception for field \"$fieldName\" in ".get_class($entity)." ".$entityId." (".$file->getSize()."B > ".$this->maxSize."B)");
            }

            //
            // Check mime restriction
            $compatibleMimeType = empty($this->mimeTypes);
            foreach ($this->mimeTypes as $mimeType) {
                $compatibleMimeType |= preg_match("/".str_replace("/", "\/", $mimeType)."/", $file->getMimeType());
            }

            if (!$compatibleMimeType) {
                throw new InvalidMimeTypeException("Invalid MIME type \"".$file->getMimeType()."\" received for field \"$fieldName\" in ".get_class($entity)." ".$entityId." (expected: \"".implode(", ", $this->mimeTypes)."\").");
            }

            //
            // Upload files
            $path     = $this->getPath($entity ?? $oldEntity ?? null, $fieldName);
            $contents = ($file ? file_get_contents($file->getPathname()) : "");

            if (!$this->getFlysystem()->write($path, $contents, $this->getStorage(), $this->getConfig())) {
                throw new InvalidMimeTypeException("Failed to write \"".$path."\" in ".get_class($entity).".");
            }

            $fileList[] = basename($path);
        }

        self::setPropertyValue($entity, $fieldName, !is_array($new) ? $fileList[0] ?? null : $fileList);
        return true;
    }

    protected function deleteFiles($entity, $oldEntity, string $fieldName)
    {
        $new = self::getFieldValue($entity, $fieldName);
        $newList = is_array($new) ? $new : [$new];
        $newListStringable = array_filter(array_map(fn ($e) => is_stringeable($e), $newList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // This means that these elements are not meant to be uploaded
        if (count($newList) != count($newListStringable)) {
            return false;
        }

        $old = $entity !== null && array_key_exists(spl_object_id($entity), $this->ancestorEntity) ? self::getFieldValue($this->ancestorEntity[spl_object_id($entity)], $fieldName) : self::getFieldValue($oldEntity, $fieldName);
        $oldList = is_array($old) ? $old : [$old];
        $oldListStringable = array_filter(array_map(fn ($e) => is_stringeable($e), $oldList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // This means that these elements are not meant to be uploaded
        if (count($oldList) != count($oldListStringable)) {
            return false;
        }

        if (!$oldList) {
            return;
        }

        foreach (array_diff($oldList, $newList) as $file) {
            if (!$file) {
                continue;
            }

            if ($file instanceof File) {
                $path = $file->getRealPath();
            } else {
                $path = $this->getPath($entity ?? $oldEntity ?? null, $fieldName, $file);
            }

            if ($path) {
                $this->getFlysystem()->delete($path, $this->getStorage());
            }
        }
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $fieldName = null)
    {
        try {
            $this->uploadFiles($entity, null, $fieldName);
        } catch(Exception $e) {
            $this->deleteFiles(null, $entity, $fieldName);
            self::setFieldValue($entity, $fieldName, null);

            throw $e;
        }
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $fieldName = null)
    {
        $oldEntity = $this->getOldEntity($entity);
        try {

            if ($this->uploadFiles($entity, $oldEntity, $fieldName)) {
                $this->deleteFiles($entity, $oldEntity, $fieldName);
            }

        } catch(Exception $e) {

            $this->deleteFiles($oldEntity, $entity, $fieldName);
            $old = self::getFieldValue($oldEntity, $fieldName);

            self::setFieldValue($entity, $fieldName, $old);
            //throw $e;
        }

        $this->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }

    public function postRemove(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $fieldName = null)
    {
        $this->deleteFiles(null, $entity, $fieldName);
    }
}
