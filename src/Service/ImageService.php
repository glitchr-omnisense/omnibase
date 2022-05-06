<?php

namespace Base\Service;

use Base\Filter\Advanced\Thumbnail\HighDefinitionFilter;
use Base\Filter\Advanced\ThumbnailFilter;
use Base\Filter\Base\SvgFilter;
use Base\Filter\LastFilterInterface;
use Exception;
use Hashids\Hashids;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Palette\CMYK;
use Imagine\Image\Palette\RGB;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\RouterInterface;
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

    protected static $projectDir;
    protected static $publicDir;

    public function __construct(Environment $twig, AssetExtension $assetExtension, RouterInterface $router, ParameterBagInterface $parameterBag, ImagineInterface $imagine, Filesystem $filesystem)
    {
        self::$projectDir = dirname(__FILE__, 6);
        self::$publicDir  = self::$projectDir."/public";

        $this->twig       = $twig;
        $this->assetExtension    = $assetExtension;
        $this->imagine    = $imagine;
        $this->router     = $router;
        
        $this->filesystem = $filesystem->set("local.cache");
        try { $this->filesystem->mkdir("imagine"); } 
        catch(\Exception $e) {}

        $this->maxResolution = $parameterBag->get("base.image.max_resolution");
        $this->maxQuality    = $parameterBag->get("base.image.max_quality");
        $this->enableWebp    = $parameterBag->get("base.image.enable_webp");
        $this->noImage       = $parameterBag->get("base.image.no_image");

        $this->hashIds = new Hashids($parameterBag->get("kernel.secret"));
        self::$mimeTypes = new MimeTypes();
    }

    public function getMaximumQuality() { return $this->maxQuality; } 
    public function isWebpEnabled() { return $this->enableWebp; }

    protected $hashIds;
    public function encode(array $array): string { return $this->hashIds->encodeHex(bin2hex(serialize($array))); }
    public function decode(string $hash): mixed  { return unserialize(hex2bin($this->hashIds->decodeHex($hash))); }

    public function webp   (array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->resolve("ux_imageWebp", $path, $filters, $config); }
    public function image  (array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->resolve("ux_image", $path, $filters, $config); }
    public function imagine(array|string|null $path, array $filters = [], array $config = []): array|string|null { return browser_supports_webp() ? $this->webp($path, $filters, $config) : $this->image($path, $filters, $config); }

    public function thumbnail_inset   (array|string|null $path, ?int $width = null , ?int $height = null, array $filters = [], array $config = []): array|string|null { return $this->thumbnail($path, $width, $height, $filters, array_merge($config, ["mode" => ImageInterface::THUMBNAIL_INSET])); }
    public function thumbnail_outbound(array|string|null $path, ?int $width = null , ?int $height = null, array $filters = [], array $config = []): array|string|null { return $this->thumbnail($path, $width, $height, $filters, array_merge($config, ["mode" => ImageInterface::THUMBNAIL_OUTBOUND])); }
    public function thumbnail_noclone (array|string|null $path, ?int $width = null , ?int $height = null, array $filters = [], array $config = []): array|string|null { return $this->thumbnail($path, $width, $height, $filters, array_merge($config, ["mode" => ImageInterface::THUMBNAIL_FLAG_NOCLONE])); }
    public function thumbnail_upscale (array|string|null $path, ?int $width = null , ?int $height = null, array $filters = [], array $config = []): array|string|null { return $this->thumbnail($path, $width, $height, $filters, array_merge($config, ["mode" => ImageInterface::THUMBNAIL_FLAG_UPSCALE])); }
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

    public function getHashId(string|null $source, array $filters = [], array $config = []): ?string
    {
        if($source === null ) return null;
        $path = "imagine/".str_strip($source, $this->assetExtension->getAssetUrl(""));

        $config["path"] = $path;
        $config["options"] = array_merge(["quality" => $this->getMaximumQuality()], $config["options"] ?? []);
        if(!empty($filters)) $config["filters"] = $filters;

        while ( ($sourceConfig = $this->decode(basename($source))) ) {

            $source = $sourceConfig["path"] ?? $source;
            $config["path"] = $source;
            $config["filters"] = ($sourceConfig["filters"] ?? []) + ($config["filters"] ?? []);
            $config["options"] = ($sourceConfig["options"] ?? []) + ($config["options"] ?? []);
        }

        return $this->encode($config);
    }

    public function resolveArguments(string $hashid, array $filters = [], array $args = [])
    {
        $path = null;
        $args = [];

        do {
        
            // Path fallback
            $args0 = null;
            $hashid0 = $hashid;
            while(strlen($hashid0) > 1) {

                $args0 = $this->decode(basename($hashid0));
                if($args0) break;

                $hashid0 = dirname($hashid0);
            }

            if(!is_array($args0)) $path = $hashid;
            else {

                $hashid = array_pop_key("path", $args0) ?? $hashid;
                $filters = array_key_exists("filters", $args0) ? array_merge($args0["filters"], $filters) : $filters;
                $args = array_merge($args, $args0);
            }

        } while(is_array($args0));

        $args["path"]    = $path;
        $args["filters"] = $filters;
        $args["options"] = $args["options"] ?? [];
        return $args;
    }
    
    public function resolve(string $route, array|string|null $source, array $filters = [], array $config = []): array|string|null
    {
        if(!$source || $source == "/") return $source;
        if(is_array($source)) return array_map(fn($s) => $this->resolve($route, $s, $filters, $config), $source);

        return $this->router->generate($route, ["hashid" => $this->getHashId($source, $filters, $config)]);
    }

    public static function getPublic(?string $path) 
    {
        if(!self::$projectDir)
            self::$projectDir = dirname(__FILE__, 6);
        if(!self::$publicDir)
            self::$publicDir  = self::$projectDir."/public";

        $stripPath = str_lstrip($path, [self::$publicDir, "imagine/"]);
        if($path == $stripPath && str_starts_with($stripPath, "/")) return null;

        return $path !== null ? self::$publicDir."/".$stripPath : null; 
    }

    public function filter(?string $path, array $filters = [], bool $httpCache = true): null|bool|Response
    {
        //
        // Resolve nested paths
        $args = $this->resolveArguments($path, $filters);
        $path = $args["path"];
        $filters = $args["filters"];

        //
        // Apply image resolution limitation
        if(!is_instanceof($this->maxResolution, ThumbnailFilter::class))
            throw new Exception("Resolution filter \"".$this->maxResolution."\" must inherit from ".ThumbnailFilter::class);

        array_unshift($filters, new $this->maxResolution());

        //
        // Extract last filter
        $filters = array_filter($filters, fn($f) => class_implements_interface($f, FilterInterface::class));
        $lastFilter = end($filters);
        
        $content = null;
        $pathPublic = null;
        if($lastFilter !== null) {
            
            if(!class_implements_interface($lastFilter, LastFilterInterface::class))
                throw new \Exception("Last filter \"".($lastFilter ? get_class($lastFilter) : null)."\" must implement \"".LastFilterInterface::class."\"");
            
            $filtersButLast = array_slice($filters, 0, count($filters)-1);
            foreach($filtersButLast as $filter) {
                
                if(class_implements_interface($filter, LastFilterInterface::class))
                    throw new \Exception("Only last filter must implement \"".LastFilterInterface::class."\"");
            }

            // No public path can be created.. so just apply filter to the image
            $pathPublic = self::getPublic($path);
            if($pathPublic === null) {

                try { $image = $this->imagine->open($path); } 
                catch (Exception $e) {

                    $path = $this->noImage;
                    $pathPublic = self::getPublic($this->noImage);

                    $image = $this->imagine->open($path);
                }
                
                // GD does not support other palette than RGB..
                //if($this->imagine instanceof \Imagine\Gd\Imagine && is_cmyk($path))
                //   cmyk2rgb($path); // Not working..

                // Trigger exception on purpose (if GD is used)
                $image->usePalette(is_cmyk($path) ? new CMYK() : new RGB());
                foreach ($filters as $filter)
                    $image = $filter->apply($image);

                return true;
            }

            //
            // Compute a response..
            // Cache not found
            $pathSuffixes = array_map(fn ($f) => is_stringeable($f) ? strval($f) : null, $filters);
            $pathPublic = self::getPublic($path);

            if (!$this->filesystem->getOperator()->fileExists($path)) {

                if($lastFilter instanceof SvgFilter) {

                    // No filter can be applied to SVG images
                    $content = file_get_contents($pathPublic);

                } else {

                    // GD does not support other palette than RGB..
                    //if($this->imagine instanceof \Imagine\Gd\Imagine && is_cmyk($pathPublic))
                    //   cmyk2rgb($pathPublic); // Not working yet..

                    /**
                     * @var ImageInterface
                     */

                    try { $image = $this->imagine->open($pathPublic); } 
                    catch (Exception $e) {

                        $path = $this->noImage;
                        $pathPublic = self::getPublic($this->noImage);

                        $image = $this->imagine->open($pathPublic);
                    }

                    // Trigger exception on purpose (if GD is used with CMYK palette)
                    $image->usePalette(is_cmyk($pathPublic) ? new CMYK() : new RGB()); 
                    foreach ($filters as $filter)
                        $image = $filter->apply($image);

                    $content = $image->get(self::extension($lastFilter->getPath()));
                }

                $this->filesystem->mkdir(dirname($path));
                $content = $this->filesystem->write(path_suffix($path, $pathSuffixes), $content);
            }
        }

        $path = path_suffix($path, $pathSuffixes);
        //
        // Read image content 
        $content = $path == $pathPublic ? @file_get_contents($path) : $this->filesystem->read($path);
        $pathPrefixer = $this->filesystem->getPathPrefixer();
        $mimetype = $this->getMimeType($pathPrefixer ? $pathPrefixer->prefixPath($path) : $path);

        unlink_tmpfile($path);
        unlink_tmpfile($pathPublic);

        //
        // Prepare response
        $response = new Response();
        $response->setContent($content);
        
        if($httpCache) {

            $response->setMaxAge(365*24*3600);
            $response->setPublic();
            $response->setEtag(md5($response->getContent()));
            $response->headers->addCacheControlDirective('must-revalidate', true);
        }

        $response->headers->set('Content-Type', $mimetype);
        return $response;
    }

    public function getMimeType(?string $path):?string { return $path !== null ? (self::mimetype($path) ?? $this->filesystem->mimetype($path)) : null; }
    public static function mimetype(null|string|array $fileOrArray):null|string|array  {

        if($fileOrArray === null) return null;
        if(is_array($fileOrArray)) return array_map(fn($f) => self::mimetype($f), $fileOrArray);

        $file = self::getPublic($fileOrArray);
        if(file_exists($file))
            return mime_content_type($file);

        try { return self::$mimeTypes->guessMimeType($fileOrArray); }
        catch (Exception $e) { return null; }
    }

    public function getExtension(string $path):null|string|array { return self::extension($this->getMimeType($path) ?? $path); }
    public static function extension(null|string|array $mimetypeOrFileOrArray):null|string|array 
    {
        if(is_array($mimetypeOrFileOrArray))
            return array_filter(array_map(fn($mimetype) => self::extension($mimetype), $mimetypeOrFileOrArray));

        if(!$mimetypeOrFileOrArray) return null;

        $file = self::getPublic($mimetypeOrFileOrArray);
        if(file_exists($file)) {

            try { $imagetype = exif_imagetype($file); }
            catch (Exception $e) { $imagetype = false; }
            return $imagetype !== false ? mb_substr(image_type_to_extension($imagetype), 1) : pathinfo($file, PATHINFO_EXTENSION) ?? null;
        }
        
        return self::$mimeTypes->getExtensions($mimetypeOrFileOrArray)[0] ?? pathinfo($mimetypeOrFileOrArray, PATHINFO_EXTENSION) ?? null;
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

    public function imagify(null|array|string $path, array $attributes = []): ?string
    {
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($p) => $this->imagify($p), $path);

        if($attributes["src"] ?? false)
            unset($attributes["src"]);

        return "<img ".html_attributes($attributes)." src='".$this->imagine($path)."' />";
    }
}
