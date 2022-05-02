<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Exception\InvalidMimeTypeException;
use Base\Exception\InvalidSizeException;
use Base\Exception\MissingPublicPathException;
use Base\Validator\Constraints\File as ConstraintsFile;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Class Uploader
 * package Base\Annotations\Annotation\Uploader
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("storage",      type = "string"),
 *   @Attribute("pool",         type = "string"),
 *   @Attribute("public",       type = "string"),
 *   @Attribute("missable", type = "boolean"),
 *
 *   @Attribute("max_size",         type = "string"),
 *   @Attribute("mime_types",         type = "array")
 * })
 */
class Uploader extends AbstractAnnotation
{
    private string $storage;
    private string $pool;

    private array $config;
    private array $mimeTypes;
    private int   $maxSize;

    public function __construct( array $data )
    {
        $this->public    = (!empty($data["public"] ?? null) ? ltrim($data["public"],"/") : null);
        $this->pool      = (!empty($data["pool"]   ?? null) ? $data["pool"] : "default");

        $this->storage   = $data["storage"] ?? null;
        $this->missable  = $data["missable"] ?? false;
        $this->config    = $data["config"] ?? [];
        $this->mimeTypes = $data["mime_types"] ?? [];

        $this->maxSize   = str2dec($data["max_size"] ?? UploadedFile::getMaxFilesize());
    }

    protected function getContents(): string { return file_get_contents($this->file["tmp_name"]); }
    protected function getConfig(): array { return $this->config; }
    
    public function getStorage() { return $this->storage; }
    public function getStorageFilesystem() { return parent::getFilesystem($this->storage); }

    public function getPool() { return $this->pool; }
    public function getPath(mixed $entity, string $fieldName, ?string $uuid = null): ?string
    {
        $pool     = $this->pool;
        $uuid     = $uuid ?? Uuid::v4();

        if($uuid && !preg_match('/^[a-f0-9\-]{36}$/i', $uuid))
            return null;

        $namespaceDir = "";
        if($entity) {

            $namespace = (is_string($entity) ? $entity : get_class($entity));
            $namespaceRoot = "Entity";
            $namespaceDir = implode("/", array_map("lcfirst", explode("\\", 
                                mb_substr($namespace, strpos($namespace, $namespaceRoot)-1)) 
                            ));
        }

        return rtrim("/" . $pool . $namespaceDir . "/_".camel2snake($fieldName)."/" . $uuid, ".");
    }
    
    public static function getPublic($entity, $fieldName)
    {
        if(!self::hasAnnotation($entity, $fieldName, self::class)) 
            return null;
        
        $that = self::getAnnotation($entity, $fieldName, self::class);

        if(!$that) return null;
        
        if($that->public === null)
            throw new MissingPublicPathException("No public path provided for \"$fieldName\" property annotation for \"".get_class($entity)."\".");

        $field = self::getFieldValue($entity, $fieldName);
        if(!$field) return null;
        
        if(is_array($field)) {

            $pathList = [];
            foreach($field as $uuidOrFile) {

                if($uuidOrFile instanceof File) {

                    $pathList[] = null;
                    continue;
                }

                $path = $that->getPath($entity, $fieldName, $uuidOrFile);
                if(!$path) $pathList[] = null;
                else if(!$that->getStorageFilesystem()->getOperator()->fileExists($path)) $pathList[] = null;
                else $pathList[] = rtrim($that->getAsset($that->public) . $path, ".");
            }

            $pathList = array_filter($pathList);
            return empty($pathList) ? null : $pathList;

        } else {
        
            $uuidOrFile = $field;
            if($uuidOrFile instanceof File)
                return null;

            if(!is_stringeable($uuidOrFile))
                return null;
                
            $path = $that->getPath($entity, $fieldName, strval($uuidOrFile));
            if(!$path) return null;

            if(!$that->getStorageFilesystem()->getOperator()->fileExists($path)) 
                return null;

            return rtrim($that->getAsset($that->public) . $path, ".");
        }
    }

    public static function getMimeTypes($entity, $fieldName): array
    {
        if(!self::hasAnnotation($entity, $fieldName, self::class)) 
            return [];
        
        $that = self::getAnnotation($entity, $fieldName, self::class);
        if(!$that) return [];

        return $that->mimeTypes;
    }

    public static function getMaxFilesize($entity, $fieldName): int
    {
        if(!self::hasAnnotation($entity, $fieldName, self::class)) 
            return UploadedFile::getMaxFilesize();
        
        $that = self::getAnnotation($entity, $fieldName, self::class);
        if(!$that) return UploadedFile::getMaxFilesize();

        return min($that->maxSize ?: \PHP_INT_MAX, UploadedFile::getMaxFilesize());
    }

    protected static $tmpHashTable = [];
    public static function get($entity, string $fieldName)
    {
        if($entity === null) return null;

        if(!self::hasAnnotation($entity, $fieldName, self::class)) return null;

        $that       = self::getAnnotation($entity, $fieldName, self::class);
        if(!$that) return null;

        $config       = $that->config;
        $filesystem   = $that->getStorageFilesystem();
        $adapter      = $that->getStorageFilesystem()->getAdapter();
        $pathPrefixer = $that->getStorageFilesystem()->getPathPrefixer();

        $fieldValue = self::getFieldValue($entity, $fieldName);
        if (!$fieldValue) return null;
        if (!is_array($fieldValue)) $fieldValue = [$fieldValue];

        $fileList = [];
        foreach($fieldValue as $uuidOrFile) {

            if($uuidOrFile instanceof File) {

                $fileList[] = $uuidOrFile;
                continue;
            }

            // Special case for local adapter, if not found.. it requires a temp file..
            $path = $that->getPath($entity, $fieldName, $uuidOrFile);
            if(!$path) {
                $fileList[] = null;
                continue;
            }

            if($adapter instanceof LocalFilesystemAdapter) {

                if ($filesystem->getOperator()->fileExists($path))
                    $fileList[] = new File($pathPrefixer ? $pathPrefixer->prefixPath($path) : $path);

                continue;
            }

            // Copy file content in a tmp file
            $index = spl_object_hash($adapter) . ":" . $uuidOrFile;
            if (!array_key_exists($index, self::$tmpHashTable))
                self::$tmpHashTable[$index] = tmpfile();

            fwrite(self::$tmpHashTable[$index], $that->readFile($path, $filesystem, $config));
            $fileList[] = new File(stream_get_meta_data(self::$tmpHashTable[$index])['uri']);
        }

        if(count($fileList) < 1) return null;
        if(count($fileList) < 2) return $fileList[0];
        return $fileList;
    }

    protected function uploadFiles($entity, $oldEntity, ?string $fieldName = null)
    {
        $new = self::getFieldValue($entity, $fieldName);
        $newList = is_array($new) ? $new : [$new];
        $newListStringable = array_filter(array_map(fn($e) => is_stringeable($e), $newList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // This means that these elements are not meant to be uploaded
        if(count($newList) != count($newListStringable))
            return false; 

        $old = self::getFieldValue($oldEntity, $fieldName);
        $oldList = is_array($old) ? $old : [$old];
        $oldListStringable = array_filter(array_map(fn($e) => is_stringeable($e), $oldList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // This means that these elements are not meant to be uploaded
        if(count($oldList) != count($oldListStringable))
            return false;

        // No change in the list..
        if($newList === $oldList) return true;

        // Nothing to upload, empty field..
        if ($newList === null) {

            $this->setPropertyValue($entity, $fieldName, null);
            return true;
        }

        //
        // Replace http urls in the list of new elements
        foreach ($newList as $index => $entry) {

            if (filter_var($entry, FILTER_VALIDATE_URL))
            $newList[$index] = new File(tempurl($entry));
        }

        $this->setPropertyValue($entity, $fieldName, !is_array($new) ? $newList[0] ?? null : $newList);

        // Field value can be an array or just a single path
        $fileList = array_values(array_intersect($newList, $oldList));
        foreach (array_diff($newList, $oldList) as $index => $entry) {

            //
            // In case of string casting, and UploadedFile might be returned as a string..
            $file = is_string($entry) && file_exists($entry) ? new File($entry) : $entry;
            if (!$file instanceof File) {
                
                if($this->missable) 
                    $fileList[] = $entry;
         
                continue;
            }

            //
            // Check size restriction
            if ($file->getSize() > $this->maxSize)
                throw new InvalidSizeException("Invalid filesize exception for field \"$fieldName\" in ".get_class($entity).".");

            //
            // Check mime restriction
            $compatibleMimeType = empty($this->mimeTypes);
            foreach($this->mimeTypes as $mimeType)
                $compatibleMimeType |= preg_match( "/".str_replace("/", "\/", $mimeType)."/", $file->getMimeType());

            if(!$compatibleMimeType)
                throw new InvalidMimeTypeException("Invalid MIME type \"".$file->getMimeType()."\" received for field \"$fieldName\" in ".get_class($entity)." (expected: \"".implode(", ", $this->mimeTypes)."\").");

            //
            // Upload files
            $path         = $this->getPath($entity ?? $oldEntity ?? null, $fieldName);
            $pathPrefixer = $this->getStorageFilesystem()->getPathPrefixer($this->storage);

            $contents = ($file ? file_get_contents($file->getPathname()) : "");
            if ($this->getStorageFilesystem()->write($path, $contents)) {
                $fileList[] = basename($pathPrefixer ? $pathPrefixer->prefixPath($path) : $path);
                unlink_tmpfile($file->getPathname());
            }
        }

        $this->setPropertyValue($entity, $fieldName, !is_array($new) ? $fileList[0] ?? null : $fileList);
        return true;
    }

    protected function deleteFiles($entity, $oldEntity, string $fieldName)
    {
        $new = self::getFieldValue($entity, $fieldName);
        $newList = is_array($new) ? $new : [$new];
        $newListStringable = array_filter(array_map(fn($e) => is_stringeable($e), $newList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // This means that these elements are not meant to be uploaded
        if(count($newList) != count($newListStringable))
            return false; 

        $old = self::getFieldValue($oldEntity, $fieldName);
        $oldList = is_array($old) ? $old : [$old];
        $oldListStringable = array_filter(array_map(fn($e) => is_stringeable($e), $oldList));

        // This list contains non is_stringeable element. (e.g. in case of a generic use)
        // This means that these elements are not meant to be uploaded
        if(count($oldList) != count($oldListStringable))
            return false;

        if(!$oldList) return;

        foreach (array_diff($oldList, $newList) as $file) {

            if(!$file) continue;

            if($file instanceof File) $path = $file->getRealPath();
            else $path = $this->getPath($entity ?? $oldEntity ?? null, $fieldName, $file);

            if($path) $this->getStorageFilesystem()->delete($path);
        }
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $fieldName = null)
    {
        try { $this->uploadFiles($entity, null, $fieldName); } 
        catch(Exception $e) {

            $this->deleteFiles([], $entity, $fieldName);
            self::setFieldValue($entity, $fieldName, null);

            throw $e;
        }
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $fieldName = null)
    {
        $oldEntity = $this->getOldEntity($entity);

        try {

            if ($this->uploadFiles($entity, $oldEntity, $fieldName))
                $this->deleteFiles($entity, $oldEntity, $fieldName);

        } catch(Exception $e) {

            $this->deleteFiles($oldEntity, $entity, $fieldName);
            $old = self::getFieldValue($oldEntity, $fieldName);
            self::setFieldValue($entity, $fieldName, $old);

            throw $e;
        }
    }

    public function postRemove(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $fieldName = null)
    {
        $this->deleteFiles([], $entity, $fieldName);
    }
}
