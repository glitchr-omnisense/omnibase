<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Exception\InvalidMimeTypeException;
use Base\Exception\InvalidSizeException;
use Base\Exception\InvalidUuidException;
use Base\Exception\MissingPublicPathException;
use Base\Exception\NotDeletableException;
use Base\Exception\NotReadableException;
use Base\Exception\NotWritableException;
use Base\Exception\UploaderAmbiguityException;
use Base\Service\BaseService;
use DateTime;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Finder\Finder;
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
 *   @Attribute("keepNotFound", type = "boolean"),
 *
 *   @Attribute("size", type = "string"),
 *   @Attribute("mime", type = "array"),
 * })
 */
class Uploader extends AbstractAnnotation
{
    private $filesystem;
    private string $storage;
    private string $pool;

    private array $config;
    private array $mimeTypes;
    private int $maxSize;

    public static function str2bytes(string $val): int
    {
        $last = strtolower($val[strlen($val) - 1]);
        $val = intval(trim($val));

        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    public function __construct( array $data )
    {
        $this->public       = (!empty($data["public"] ?? null) ? $data["public"] : null);
        $this->pool         = (!empty($data["pool"]   ?? null) ? $data["pool"] : "default");

        $this->storage      = $data["storage"] ?? null;
        $this->keepNotFound = $data["keepNotFound"] ?? false;
        $this->config       = $data["config"] ?? [];
        $this->mimeTypes    = $data["mime"] ?? [];
        $this->maxSize      = self::str2bytes($data["size"] ?? UploadedFile::getMaxFilesize());
    }

    protected function getContents(): string
    {
        return file_get_contents($this->file["tmp_name"]);
    }

    protected function getConfig(): array
    {
        return $this->config;
    }

    public function getStorage()
    {
        return $this->storage;
    }

    public function getPool()
    {
        return $this->pool;
    }

    public function getStorageFilesystem()
    {
        return parent::getFilesystem($this->storage);
    }

    public function getPath($entity, ?string $uuid = null): ?string
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
                                substr($namespace, strpos($namespace, $namespaceRoot)-1)) 
                            ));
        }

        return rtrim("/" . $pool . $namespaceDir . "/" . $uuid, ".");
    }
    
    public static function getPublicPath($entity, $mapping)
    {
        $that = self::getAnnotation($entity, $mapping);
        if(!$that) return null;
        
        if($that->public === null)
            throw new MissingPublicPathException("No public path provided for \"$mapping\" property annotation for \"".get_class($entity)."\".");

        $field = self::getFieldValue($entity, $mapping);
        if(!$field) return null;
        
        if(is_array($field)) {

            $pathList = [];
            foreach($field as $uuidOrFile) {

                if($uuidOrFile instanceof File) {

                    $pathList[] = null;
                    continue;
                }
                
                $path = $that->getPath($entity, $uuidOrFile);
                if(!$path) $pathList[] = null;
                else if(!$that->getStorageFilesystem()->fileExists($path)) $pathList[] = null;
                else $pathList[] = rtrim($that->public . $path, ".");
            }

            return $pathList;

        } else {
        
            $uuidOrFile = $field;
            if($uuidOrFile instanceof File)
                return null;

            $path = $that->getPath($entity, $uuidOrFile);
            if(!$path) return null;

            if(!$that->getStorageFilesystem()->fileExists($path)) 
                return null;

            return rtrim($that->public . $path, ".");
        }
    }

    public static function getMimeTypes($entity, $mapping): array
    {
        $that = self::getAnnotation($entity, $mapping);
        if(!$that) return [];
        return $that->mimeTypes;
    }

    protected static $tmpHashTable = [];
    public static function getFile($entity, string $mapping)
    {
        if($entity === null) return null;

        $that       = self::getAnnotation($entity, $mapping);
        if(!$that) return null;

        $config     = $that->config;
        $filesystem = $that->getStorageFilesystem();
        $adapter    = $that->getAdapter($filesystem);
        $pathPrefixer = $that->getPathPrefixer($that->storage);

        $fieldValue = self::getFieldValue($entity, $mapping);
        if (!$fieldValue) return null;
        if (!is_array($fieldValue)) $fieldValue = [$fieldValue];

        $fileList = [];
        foreach($fieldValue as $uuidOrFile) {

            if($uuidOrFile instanceof File) {

                $fileList[] = $uuidOrFile;
                continue;
            }

            // Special case for local adapter, if not found.. it requires a temp file..
            $path = $that->getPath($entity, $uuidOrFile);
            if(!$path) {
                $fileList[] = null;
                continue;
            }

            if($adapter instanceof LocalFilesystemAdapter) {

                if ($filesystem->fileExists($path))
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

    protected function readFile(string $location, ?FilesystemOperator $filesystem = null): ?string
    {
        $filesystem = $filesystem ?? $this->getStorageFilesystem();
        if(!$filesystem->fileExists($location))
            return null;

        try {
            return $filesystem->read($location);
        } catch (FilesystemError | UnableToReadFile $exception) {
            throw new NotReadableException("Unable to read file \"$location\"");
        }
    }

    protected function uploadFile(string $location, string $contents, ?FilesystemOperator $filesystem = null, array $config = [])
    {
        $filesystem = $filesystem ?? $this->getStorageFilesystem();
        if ($filesystem->fileExists($location))
            return false;

        try {
            $filesystem->write($location, $contents, $config);
            return true;
        } catch (FilesystemError | UnableToWriteFile $exception) {
            throw new NotWritableException("Unable to write file \"$location\"..");
            return false;
        }
    }

    protected function uploadFiles($entity, $oldEntity, ?string $property = null)
    {
        $new = self::getFieldValue($entity, $property);
        $old = self::getFieldValue($oldEntity, $property);

        if(!is_array($new)) $new = [$new];
        if(!is_array($old)) $old = [$old];
        if($new == $old) return true;

        // Nothing to upload, empty field..
        if ($new == null) {

            $this->setFieldValue($entity, $property, null);
            return true;
        }

        // Field value can be an array or just a single path
        $fileList = array_intersect($new,$old);
        foreach (array_diff($new,$old) as $index => $file) {

            // In case of string casting, and UploadedFile might be returned as a string..
            if (is_string($file) && file_exists($file))
                $file = new File($file);

            if (!$file instanceof File) continue;

            // Check size restriction
            if ($file->getSize() > $this->maxSize) 
                throw new InvalidSizeException("Invalid filesize exception in property \"$property\" in ".get_class($entity).".");

            // Check mime restriction
            $compatibleMimeType = empty($this->mimeTypes);
            foreach($this->mimeTypes as $mimeType)
                $compatibleMimeType |= preg_match( "/".str_replace("/", "\/", $mimeType)."/", $file->getMimeType());

            if(!$compatibleMimeType) 
                throw new InvalidMimeTypeException("Invalid MIME type exception for property \"$property\" in ".get_class($entity).".");

            // Upload files
            $pathPrefixer = $this->getPathPrefixer($this->storage);
            $path = $this->getPath($entity);

            $contents = ($file ? file_get_contents($file->getPathname()) : "");
            if ($this->uploadFile($path, $contents, $this->getStorageFilesystem()))
                $fileList[] = ($pathPrefixer ? $pathPrefixer->prefixPath($path) : $path);
        }

        if (!empty($fileList)) {

            $value = array_map("basename", $fileList);
            $isArray = $this->getTypeOfField($entity, $property) == "array";
            if(!$isArray) {

                if(is_array($value) && count($value) > 1)
                    throw new UploaderAmbiguityException("Too many files for \"$property\", column must be an \"array\"");

                $value = array_pop($value); 
            }

            $this->setFieldValue($entity, $property, $value);
            return true;
        }

        if(!$this->keepNotFound)
            $this->setFieldValue($entity, $property, null);
        
        return true;
    }

    protected function deleteFile(string $location, ?FilesystemOperator $filesystem = null)
    {
        $filesystem = $filesystem ?? $this->getStorageFilesystem();
        if (!$filesystem->fileExists($location))
            return false;

        try {
        
            $filesystem->delete($location);
            return true;
        
        } catch (FilesystemError | UnableToDeleteMetadata $exception) {

            throw new NotDeletableException("Unable to delete file \"$location\"..");
        }
    }

    protected function deleteFiles($entity, $oldEntity, string $property)
    {
        $new = self::getFieldValue($entity, $property);
        $old = self::getFieldValue($oldEntity, $property);
        if(!$old) return;

        if(!is_array($new)) $new = [$new];
        if(!is_array($old)) $old = [$old];
        foreach (array_diff($old,$new) as $file) {

            if(!$file) continue;

            if($file instanceof File) $path = $file->getRealPath();
            else $path = $this->getPath($entity, $file);

            if($path) $this->deleteFile($path, $this->getStorageFilesystem());
        }
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null):bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        try {

            $this->uploadFiles($entity, null, $property);

        } catch(Exception $e) {

            if(!$this->keepNotFound)
                self::setFieldValue($entity, $property, null);
        }
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        dump($property);
        $oldEntity = $this->getOldEntity($entity);

        try {

            if ($this->uploadFiles($entity, $oldEntity, $property))
                $this->deleteFiles($entity, $oldEntity, $property);

        } catch(Exception $e) {

            $this->deleteFiles($oldEntity, $entity, $property);
        }
    }

    public function postRemove(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $this->deleteFiles(null, $entity, $property);
    }
}
