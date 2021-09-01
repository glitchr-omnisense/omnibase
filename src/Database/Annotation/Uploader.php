<?php

namespace Base\Database\Annotation;

use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;
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
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Class Uploader
 * package Base\Database\Annotation\Uploader
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("config",  type = "array"),
 *   @Attribute("options", type = "array"),
 *   @Attribute("pool",    type = "string"),
 *
 *   @Attribute("size", type = "string"),
 *   @Attribute("mime", type = "array"),
 * })
 */
class Uploader extends AbstractAnnotation
{
    private $adapter;

    private string $poolDir;
    private string $path;

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
        $options = self::getOptions($data["options"] ?? []);

        $this->adapter  = self::getAdapter($options);
        $this->path     = $options["path"];
        $this->config   = $data["config"]  ?? [];

        $this->mimeTypes  = $data["mime"] ?? [];
        $this->maxSize  = self::str2bytes($data["size"] ?? UploadedFile::getMaxFilesize());
        $this->poolDir = $config["pool"] ?? "/storage/default";
    }

    protected static $projectDir;
    public static function getProjectDir()
    {
        if (!self::$projectDir)
            self::$projectDir = dirname(__DIR__, 6);

        return self::$projectDir;
    }

    public static function getOptions($options = [])
    {
        $options["adapter"] = $options["adapter"] ?? "local";
        $options["path"]    = $options["path"]    ?? self::getProjectDir() . "/var";
        return $options;
    }

    /**
     * This method can be overloaded to include more adapter
     */
    public static function getAdapter(array $options = []): FilesystemAdapter
    {
        $options = self::getOptions($options);
        $adapter = $options["adapter"] ?? "";
        switch ($adapter) {

            case "local":
                $path = $options["path"] ?? null;
                if(!$path) throw new Exception("Path variable missing for local adapter");

                return new LocalFilesystemAdapter($path);

            //case "aws":
            //case "ftp":
        }

        return null;
    }

    protected static $fsHashTable = [];
    public static function getFilesystem(FilesystemAdapter $adapter): Filesystem
    {
        $index = spl_object_hash($adapter);
        if (!array_key_exists($index, self::$fsHashTable))
            self::$fsHashTable[$index] = new Filesystem($adapter);

        return self::$fsHashTable[$index];
    }

    public function getPoolDir($entity): string {

        $projectDir = $this->getProjectDir();
        $poolDir = $this->poolDir;

        // Strip project directory path if found.. don't want to rely on absolute path
        if (substr($poolDir, 0, strlen($projectDir)) == $projectDir)
            $poolDir = substr($poolDir, strlen($projectDir)+1);

        $namespace = get_class($entity);
        $namespaceRoot = "Entity";
        $namespaceDir = strtolower(str_replace("\\", "/", substr($namespace, strpos($namespace, $namespaceRoot)-1)));

        return $poolDir . $namespaceDir;
    }

    public function generateLocation($entity, File $file): string
    {
        $uuid = Uuid::v4();
        $extension = $file->guessExtension();

        return rtrim($this->getPoolDir($entity) . "/" . $uuid . "." . $extension, ".");
    }

    public function getContents(): string
    {
        return file_get_contents($this->file["tmp_name"]);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    protected static $tmpHashTable = [];
    public static function getFile($entity, string $mapping)
    {
	if($entity === null) return null;

        $annotations = self::getAnnotationReader()->getPropertyAnnotations(get_class($entity), self::class);
        if(!array_key_exists($mapping, $annotations))
            throw new Exception("Annotation \"".self::class."\" not found in the mapped property \"$mapping\"");

        $uploaderAnnotation = end($annotations[$mapping]);
        $adapter  = $uploaderAnnotation->adapter;
        $config   = $uploaderAnnotation->config;

        $fieldValue = self::getFieldValue($entity, $mapping);
        if (!$fieldValue) return null;

        $fileList = [];
        foreach((is_array($fieldValue) ? $fieldValue : [$fieldValue]) as $file) {

            if($file instanceof File) {

                $fileList[] = $file;
                continue;
            }

            if(!is_string($file)) {

                $fileList[] = null;
                continue;
            }

            if (!self::getFilesystem($adapter)->fileExists($file)) {

                $fileList[] = null;
                continue;
            }

            // Special case fro location adapter, if not.. it requires a temp file..
            if($adapter instanceof LocalFilesystemAdapter) {

                $isAbsolutePath = ($file == realpath($file));
                if( !$isAbsolutePath ) $file = $uploaderAnnotation->path . $file;

                $fileList[] = new File($file);
                continue;
            }

            // Copy file content in a tmp file
            $index = spl_object_hash($adapter) . ":" . $file;
            if (!array_key_exists($index, self::$tmpHashTable))
                self::$tmpHashTable[$index] = tmpfile();

            $tmp = self::$tmpHashTable[$index];
            fwrite($tmp, self::readFile($file, $adapter, $config));
            $fileList[] = new File(stream_get_meta_data($tmp)['uri']); // .. and return a File
        }

        if(is_array($fieldValue)) return $fileList;
        else if(count($fileList) < 1) return null;
        else if(count($fileList) < 2) return $fileList[0];
        else return $fileList;
    }

    public static function readFile(string $location, ?FilesystemAdapter $adapter = null): string
    {
        $adapter = $adapter ?? self::getAdapter();
        if(!self::getFilesystem($adapter)->fileExists($location))
            return null;

        try {
            return self::getFilesystem($adapter)->read($location);
        } catch (FilesystemError | UnableToReadFile $exception) {
            throw new Exception("Unable to read file \"$location\"");
        }
    }

    protected static function uploadFile(string $location, string $contents, ?FilesystemAdapter $adapter = null, array $config = [])
    {
        $adapter = $adapter ?? self::getAdapter();
        if (self::getFilesystem($adapter)->fileExists($location))
            return false;

        try {
            self::getFilesystem($adapter)->write($location, $contents, $config);
            return true;
        } catch (FilesystemError | UnableToWriteFile $exception) {
            throw new Exception("Unable to write file \"$location\"..");
            return false;
        }
    }

    public function uploadFiles($entity, ?string $property = null)
    {
        $fieldValue = $this->getFieldValue($entity, $property);

        if ($fieldValue == null) {

            $this->setFieldValue($entity, $property, null);
            return true;
        }

        $locationList = [];
        foreach ((is_array($fieldValue) ? $fieldValue : [$fieldValue]) as $index => $uploadedFile) {

            // In case of string casting, and UploadedFile might be returned as a string..
            if (is_string($uploadedFile) && file_exists($uploadedFile))
                $uploadedFile = new File($uploadedFile);

            if (!$uploadedFile instanceof File) continue;

            // Check size restriction
            if ($uploadedFile->getSize() > $this->maxSize) continue;

            // Check mime restriction
            $compatibleMimeType = empty($this->mimeTypes);

            foreach($this->mimeTypes as $mimeType)
                $compatibleMimeType |= preg_match( "/".str_replace("/", "\/", $mimeType)."/", $uploadedFile->getMimeType());

            if(!$compatibleMimeType) continue;

            // Upload files
            $location = $this->generateLocation($entity, $uploadedFile);
            $contents = ($uploadedFile ? file_get_contents($uploadedFile->getPathname()) : "");

            if ($this->uploadFile($location, $contents, $this->adapter))
                $locationList[] = $location;
        }

        if (!empty($locationList)) {

            // Reshape depending on the output
            if (is_array($fieldValue)) $value = $locationList;
            else if (count($locationList) < 1) $value = null;
            else if (count($locationList) < 2) $value = $locationList[0];
            else $value = $locationList;

            $this->setFieldValue($entity, $property, $value);
            return true;
        }

        $this->setFieldValue($entity, $property, null);
        return false;
    }

    protected static function deleteFile(string $location, FilesystemAdapter $adapter = null)
    {
        $adapter = $adapter ?? self::getAdapter();
        if (!self::getFilesystem($adapter)->fileExists($location))
            return false;

        try {
            self::getFilesystem($adapter)->delete($location);
            return true;
        } catch (FilesystemError | UnableToDeleteMetadata $exception) {
            throw new Exception("Unable to delete file \"$location\"..");
        }
    }

    public function deleteFiles($entity, ?string $property = null)
    {
        $fieldValue = $this->getFieldValue($entity, $property);
        foreach ((is_array($fieldValue) ? $fieldValue : [$fieldValue]) as $location)
            if ($location) $this->deleteFile($location, $this->adapter);

        $this->setFieldValue($entity, $property, null);
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null):bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, $entity, ?string $property = null)
    {
        $this->uploadFiles($entity, $property);
    }

    public function preUpdate(LifecycleEventArgs $event, $entity, ?string $property = null)
    {
        $oldEntity = $this->getOldEntity($entity);
        if ($this->uploadFiles($entity, $property))
            $this->deleteFiles($oldEntity, $property);
    }

    public function postRemove(LifecycleEventArgs $event, $entity, ?string $property = null)
    {
        $this->deleteFiles($entity, $property);
    }
}
