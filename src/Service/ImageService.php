<?php

namespace Base\Service;

use Base\Filter\Advanced\ThumbnailFilter;
use Base\Filter\ImageFilter;
use Base\Filter\LastFilterInterface;
use Base\Filter\WebpFilter;
use Hashids\Hashids;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;

use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Twig\Environment;

class ImageService implements ImageServiceInterface
{
    /**
     * @var ImagineInterface
     */
    protected $imagine;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var MimeTypes
     */
    protected static $mimeTypes;

    public function __construct(Environment $twig, AssetExtension $assetExtension, ParameterBagInterface $parameterBag, ImagineInterface $imagine, Filesystem $filesystem)
    {
        $this->projectDir = dirname(__FILE__, 6);
        $this->publicDir  = $this->projectDir."/public";
        $this->twig           = $twig;
        $this->imagine        = $imagine;
        $this->assetExtension = $assetExtension;
        $this->filesystem     = $filesystem->set("local.cache");

        $this->maxQuality = $parameterBag->get("base.image.max_quality") ?? 1;
        $this->enableWebp     = $parameterBag->get("base.image.enable_webp") ?? true;

        $this->hashIds = new Hashids($parameterBag->get("kernel.secret"));
        self::$mimeTypes = new MimeTypes();
    }

    public function getMaximumQuality() { return $this->maxQuality; } 
    public function isWebpEnabled() { return $this->enableWebp; }

    protected $hashIds;
    public function encode(array $array): string { return $this->hashIds->encodeHex(bin2hex(serialize($array))); }
    public function decode(string $hash): mixed  { return unserialize(hex2bin($this->hashIds->decodeHex($hash))); }

    public function webp   (array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->resolve("webp", $path, $filters, $config); }
    public function image  (array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->resolve("images", $path, $filters, $config); }
    public function imagine(array|string|null $path, array $filters = [], array $config = []): array|string|null { return browser_supports_webp() ? $this->webp($path, $filters, $config) : $this->image($path, $filters, $config); }

    public function thumbnail(array|string|null $path, ?int $width = null , ?int $height = null, array $filters = [], array $config = []): array|string|null 
    { 
        $filters[] = new ThumbnailFilter(
            $width, $height ?? null, 
            $config["mode"]  ?? ImageInterface::THUMBNAIL_INSET, 
            $config["resampling"] ?? ImageInterface::FILTER_UNDEFINED
        );

        $config = array_key_removes($config, "width", "height", "mode", "resampling");
        return $this->imagine($path, $filters, $config); 
    }

    public static $i = 0;
    public function resolve(string $prefix, array|string|null $source, array $filters = [], array $config = []): array|string|null
    {
        if(!$source) return $source;
        if(is_array($source)) return array_map(fn($s) => $this->resolve($prefix, $s, $filters, $config), $source);

        $path = "imagine/".str_strip($source, $this->assetExtension->getAssetUrl(""));

        $config["path"] = $path;
        $config["options"] = array_merge(["quality" => $this->getMaximumQuality()], $config["options"] ?? []);
        if(!empty($filters)) $config["filters"] = $filters;

        // Config convolution
        while ( ($sourceConfig = $this->decode(basename($source))) ) {

            $source = $sourceConfig["path"] ?? $source;
            $config["path"] = $source;
            $config["filters"] = ($sourceConfig["filters"] ?? []) + ($config["filters"] ?? []);
            $config["options"] = ($sourceConfig["options"] ?? []) + ($config["options"] ?? []);
        }

        return $this->assetExtension->getAssetUrl($prefix."/").$this->encode($config);
    }

    public function filter(string $path, array $filters = []): Response
    {
        if(!$path) return null;

        $filters = array_filter($filters, fn($f) => class_implements_interface($f, FilterInterface::class));

        $lastFilter = array_slice($filters, -1, 1)[0] ?? null;
        if($lastFilter !== null) {

            if(!class_implements_interface($lastFilter, LastFilterInterface::class))
                throw new \Exception("Last filter \"".($lastFilter ? get_class($lastFilter) : null)."\" must implement \"".LastFilterInterface::class."\"");

            $filters = array_slice($filters, 0, count($filters)-1);
            foreach($filters as $filter) {

                if(!class_implements_interface($filters, LastFilterInterface::class))
                    throw new \Exception("Only last filter must implement \"".LastFilterInterface::class."\"");
            }

            $pathSuffixes = array_map(fn($f) => is_stringeable($f) ? strval($f) : null, $filters+[$lastFilter]);
            $pathSource = $this->publicDir."/".str_strip($path, "imagine/");
            $path = path_suffix($path, $pathSuffixes);

            if(!$this->filesystem->getOperator()->fileExists($path)) {

                /**
                 * @var ImageInterface
                 */
                try {

                    $image = $this->imagine->open($pathSource);
                    foreach($filters+[$lastFilter] as $filter)
                        $image = $filter->apply($image);

                    $this->filesystem->mkdir(dirname($path));

                    $content = $image->get(self::extension($lastFilter->getPath()));
                    $content = $this->filesystem->write($path, $content);

                } catch (\Exception $e) { 

                    $path = $pathSource;
                }
            }
        }

        if($path == $pathSource) {

            $content = file_get_contents($pathSource); 
            $mimetype = self::mimetype($path);
            
        } else {

            $content = $this->filesystem->read($path);
            $mimetype = self::mimetype($this->filesystem->getPathPrefixer()->prefixPath($path));
        }

        $response = new Response();
        $response->setContent($content);
        $response->setMaxAge(365*24*3600);
        $response->setPublic();
        $response->setEtag(md5($response->getContent()));

        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->set('Content-Type', $mimetype);

        return $response;
    }

    public static function mimetype(null|string|array $fileOrArray):null|string|array  {

        if(is_array($fileOrArray)) return array_map(fn($f) => self::mimetype($f), $fileOrArray);

        if(!file_exists($fileOrArray)) 
            return self::$mimeTypes->guessMimeType($fileOrArray);

        $imagetype = exif_imagetype($fileOrArray);
        return $imagetype ? image_type_to_mime_type($imagetype) : self::$mimeTypes->guessMimeType($fileOrArray);
    }

    public static function extension(null|string|array $mimetypeOrFileOrArray):null|string|array 
    {
        if(!$mimetypeOrFileOrArray) return [];
        if(is_array($mimetypeOrFileOrArray))
            return array_map(function($mimetype) { return self::extension($mimetype); }, $mimetypeOrFileOrArray);

        if(file_exists($mimetypeOrFileOrArray)) {

            $imagetype = exif_imagetype($mimetypeOrFileOrArray);
            return $imagetype ? substr(image_type_to_extension($imagetype), 1) : null;
        }

        return self::$mimeTypes->getExtensions($mimetypeOrFileOrArray)[0] ?? null;
    }

    public static function extensions(null|string|array $mimetypeOrArray):null|string|array 
    {
        if(!$mimetypeOrArray) return [];
        if(is_array($mimetypeOrArray)) {

            $extensions = [];
            $extensionList = array_map(function($mimetype) { return self::extensions($mimetype); }, $mimetypeOrArray);
            foreach ( $extensionList as $extension )
                $extensions = array_merge($extensions,$extension);

            return array_unique($extensions);
        }

        return self::$mimeTypes->getExtensions($mimetypeOrArray);
    }

    public function imagify(null|array|string $path, array $attributes = []) 
    {
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($p) => $this->imagify($p), $path);

        if(filter_var($path, FILTER_VALIDATE_URL) === FALSE)  return null;
        if($attributes["src"] ?? false)
            unset($attributes["src"]);

        return "<img ".html_attributes($attributes)." src='".$path."' />";
    }
}