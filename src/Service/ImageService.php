<?php

namespace Base\Service;

use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Base\Imagine\Filter\Format\BitmapFilterInterface;

use Base\Imagine\Filter\Format\SvgFilter;
use Base\Imagine\Filter\FormatFilterInterface;
use Base\Routing\RouterInterface;
use Base\Twig\Environment;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class ImageService extends FileService implements ImageServiceInterface
{
    /**
     * @var ImagineInterface
     */
    protected $imagine;

    /**
     * @var Profiler
     */
    protected $profiler;

    /**
     * @var ImagineInterface
     */
    protected $imagineBitmap;

    /**
     * @var ImagineInterface
     */
    protected $imagineSvg;

    protected string $publicDir;
    protected string $projectDir;
    protected string $localCache;

    /** @var ?int */
    protected ?int $timeout;
    /** @var ?string */
    protected ?int $fallback;
    /** @var string */
    protected string $maxResolution;
    /** @var string */
    protected string $maxQuality;
    /** @var ?bool */
    protected ?bool $noImage;
    /** @var ?bool */
    protected ?bool $debug;

    /** @var ?bool */
    protected ?bool $enableWebp;

    public function __construct(
        Environment $twig, RouterInterface $router, ObfuscatorInterface $obfuscator, FlysystemInterface $flysystem,
        ParameterBagInterface $parameterBag, ImagineInterface $imagineBitmap, ImagineInterface $imagineSvg, ?Profiler $profiler)
    {
        parent::__construct($twig, $router, $obfuscator, $flysystem);

        $this->profiler      = $parameterBag->get("base.images.profiler") ? $profiler : null;

        $this->imagineBitmap = $imagineBitmap;
        $this->imagineSvg    = $imagineSvg;

        $this->timeout       = $parameterBag->get("base.images.timeout");
        $this->fallback      = $parameterBag->get("base.images.fallback");
        $this->maxResolution = $parameterBag->get("base.images.max_resolution");
        $this->maxQuality    = $parameterBag->get("base.images.max_quality");
        $this->enableWebp    = $parameterBag->get("base.images.enable_webp");
        $this->noImage       = $parameterBag->get("base.images.no_image");
        $this->debug         = $parameterBag->get("base.images.debug");

        $this->twig          = $twig;

        // Local cache directory for filtered images
        $this->localCache = "local.cache";
    }

    public function getMaximumQuality() { return $this->maxQuality; }
    public function isWebpEnabled() { return $this->enableWebp; }

    public function webp   (array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->generate("ux_imageWebp", [], $path, array_merge($config, ["filters" => $filters])); }
    public function image  (array|string|null $path, array $filters = [], array $config = []): array|string|null { return $this->generate(array_key_exists("extension", $config) ? "ux_imageExtension" : "ux_image"    , [], $path, array_merge($config, ["filters" => $filters])); }
    public function imagine(array|string|null $path, array $filters = [], array $config = []): array|string|null
    {
        $supports_webp  = array_pop_key("webp", $config) ?? browser_supports_webp();

        $extension = array_pop_key("extension", $config) ?? first($this->getExtensions($path));
        if($extension) $supports_webp &= ($extension != "svg");

        return $supports_webp ? $this->webp($path, $filters, $config) : $this->image($path, $filters, array_merge($config, ["extension" => $extension]));
    }

    public function imagify(null|array|string $path, array $attributes = [], ...$srcset): ?string
    {
        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($s) => $this->imagify($s, $attributes), $path);

        $lazyload = array_pop_key("lazy", $attributes);
        $lazybox  = array_pop_key("lazy-box", $attributes);

        $srcset = array_map(fn($src) => array_pad(is_array($src) ? $src : [$src,$src], 2, null), $srcset);
        $srcset = implode(", ", array_map(fn($src) => $this->thumbnail($path, $src[0], $src[1]). " ".$src[0]."w ".$src[1]."h", $srcset));
        $attributes[$lazyload ? "data-srcset" : "srcset"] = str_strip(($attributes["srcset"] ?? $attributes["data-srcset"] ?? "").",".$srcset, ",");

        return $this->twig->render("@Base/image/default.html.twig", [
            "path"     => $this->imagine($path),
            "attr"     => $attributes,
            "lazyload" => $lazyload,
            "lazybox"  => $lazybox
        ]);
    }

    public function lightbox(
        null|array|string $path,
        array $attributes = [],
        array|string $lightboxId = null,
        array|string $lightboxTitle = null,
        array $lightboxAttributes = [],
        ...$srcset): ?string
    {
        $lightboxPathType = gettype($path);
        $lightboxIdType = gettype($lightboxId);
        $lightboxTitleType = gettype($lightboxTitle);

        if($path != NULL && $lightboxPathType !== $lightboxIdType && $lightboxIdType !== gettype(NULL))
            throw new Exception("Unexpected `lightboxId` type parameter received: ".$lightboxIdType." (expected:".$lightboxPathType.")");
        if($path != NULL && $lightboxPathType !== $lightboxTitleType && $lightboxTitleType !== gettype(NULL))
            throw new Exception("Unexpected `lightboxTitle` type parameter received: ".$lightboxIdType." (expected:".$lightboxPathType.")");

        if(!$path) return $path;
        if(is_array($path)) return array_map(fn($k) => $this->lightbox($path[$k], $attributes, $lightboxId[$k], $lightboxTitle[$k], $lightboxAttributes), array_keys($path));

        $path = $this->imagine($path);
        $lightboxAttributes["loading"] ??= "lazy";
        $lightboxAttributes["class"] = trim(($lightboxAttributes["class"] ?? "")." lightbox-wrapper");
        $lightboxAttributes["data-lightbox"] = $lightboxId ?? "lightbox";
        if ($lightboxTitle !== null)
            $lightboxAttributes["data-title"] = $lightboxTitle;

        $lazyload = array_pop_key("lazy", $attributes);
        $lazybox = array_pop_key("lazy-box", $attributes);

        $srcset = array_map(fn($src) => array_pad(is_array($src) ? $src : [$src,$src], 2, null), $srcset);
        $srcset = implode(", ", array_map(fn($src) => $this->thumbnail($path, $src[0], $src[1]). " ".$src[0]."w ".$src[1]."h", $srcset));
        $attributes[$lazyload ? "data-srcset" : "srcset"] = str_strip(($attributes["srcset"] ?? $attributes["data-srcset"] ?? "").",".$srcset, ",");

        return $this->twig->render("@Base/image/lightbox.html.twig", [
            "path" => $path,
            "attr" => $attributes,
            "attr_lightbox" => $lightboxAttributes,
            "lazyload" => $lazyload,
            "lazybox"  => $lazybox
        ]);
    }

    public function crop(array|string|null $path, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null, string $position = "leftop", array $filters = [], array $config = []): array|string|null
    {
        $filters[] = new CropFilter(
            $x, $y,
            $width, $height,
            $position
        );

        $config = array_key_removes($config, "width", "height", "x", "y", "position");
        return $this->imagine($path, $filters, $config);
    }

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

    public function obfuscate(string|null $path, array $config = [], array $filters = []): ?string
    {
        if($path === null ) return null;
        $path = "/".str_strip($path, $this->router->getAssetUrl(""));

        $config["path"] = $path;
        $config["options"] = array_merge(["quality" => $this->getMaximumQuality()], $config["options"] ?? []);
        $config["local_cache"] = $config["local_cache"] ?? null;
        if(!empty($filters)) $config["filters"] = $filters;

        while ( ($pathConfig = $this->obfuscator->decode(basename($path))) ) {

            $path = $pathConfig["path"] ?? $path;
            $config["path"] = $path;
            $config["filters"] = array_merge_recursive($pathConfig["filters"] ?? [], $config["filters"] ?? []);
            $config["options"] = array_merge_recursive($pathConfig["options"] ?? [], $config["options"] ?? []);
            $config["local_cache"] = $pathConfig["local_cache"] ?? $config["local_cache"];
        }

        $hashid = $this->obfuscator->encode($config);
        $hashid = path_subdivide($hashid, self::CACHE_SUBDIVISION, self::CACHE_SUBDIVISION_LENGTH);
        return $hashid;
    }

    public function generate(string $proxyRoute, array $proxyRouteParameters = [], ?string $path = null, array $config = []): ?string
    {
        if(!$path) return null;

        $config["filters"] ??= [];

        return parent::generate($proxyRoute, $proxyRouteParameters, $path, $config);
    }

    public function resolve(string $hashid, array $filters = []): array
    {
        $config = parent::resolve($hashid) ?? [];
        $config["filters"]  = array_merge($config["filters"] ?? [], $filters);
        return $config;
    }

    public function serve(?string $file, int $status = 200, array $headers = []): ?Response
    {
        if(!file_exists($file)) {

            if(!$this->fallback)
                throw new NotFoundHttpException($file ? "Image \"$file\" not found." : "Empty path provided.");

            $file = $this->noImage;
            array_pop_key("http_cache", $headers);
        }

        if ($this->profiler !== null)
            $this->profiler->disable();

        return parent::serve($file, $status, $headers);
    }

    public function isCached(?string $path, FilterInterface|array $filters = [], array $config = []): bool
    {
        if(!is_array($filters)) $filters = [$filters];

       //
        // Resolve nested paths
        $options = $this->resolve($path, $filters);
        $path    = $config["path"]    ?? $options["path"]    ?? $path; // Cache directory location
        $filters = $config["filters"] ?? $options["filters"] ?? [];
        $storage = $config["storage"] ?? $options["storage"] ?? null;
        $output  = $config["output"]  ?? $options["output"]  ?? realpath($path);

        //
        // Apply image resolution limitation
        if(!is_instanceof($this->maxResolution, ThumbnailFilter::class))
            throw new NotFoundHttpException("Resolution filter \"".$this->maxResolution."\" must inherit from ".ThumbnailFilter::class);

        //
        // Extract last filter
        $filters = array_filter($filters, fn($f) => class_implements_interface($f, FilterInterface::class));
        $formatter = end($filters);
        if($formatter === null)
            throw new NotFoundHttpException("Last filter is missing.");

        //
        // Apply size limitation to bitmap only
        if(class_implements_interface($formatter, BitmapFilterInterface::class)) {

            $definitionFilters = array_filter($formatter->getFilters(), fn($f) => $f instanceof ThumbnailFilter);
            if(empty($definitionFilters))
                $formatter->addFilter(new $this->maxResolution);

            if(!class_implements_interface($formatter, FormatFilterInterface::class))
                throw new NotFoundHttpException("Last filter \"".($formatter ? get_class($formatter) : null)."\" must implement \"".FormatFilterInterface::class."\"");
        }

        $filtersButLast = array_slice($filters, 0, count($filters)-1);
        foreach($filtersButLast as $filter) {

            if(class_implements_interface($filter, FormatFilterInterface::class))
                throw new NotFoundHttpException("Only last filter must implement \"".FormatFilterInterface::class."\"");
        }

        $pathRelative = $this->flysystem->stripPrefix($output, $storage);
        $pathCache = $pathRelative;

        // NB: Encode path using hashid only: make sure the path is matching route generator
        // ... Otherwise, the controller will take over
        // $pathExtras   = array_map(fn ($f) => is_stringeable($f) ? strval($f) : null, $filters);
        // $pathCache    = path_suffix($pathRelative, $pathExtras  );

        //
        // Compute a response.. (if cache not found)
        if ($config["local_cache"] ?? true) {

            $localCache = array_pop_key("local_cache", $config);
            if(!is_string($localCache)) $localCache = $this->localCache;
            if(!$this->flysystem->hasStorage($this->localCache))
                throw new InvalidArgumentException("\"".$this->localCache."\" storage not found in your Flysystem configuration.");

            return $this->flysystem->fileExists($pathCache, $localCache);
        }

        return false;
    }

    public function filter(?string $path, FilterInterface|array $filters = [], array $config = []): ?string
    {
        if($this->debug) return $this->noImage;
        if(!is_array($filters)) $filters = [$filters];

        //
        // Resolve nested paths
        $options = $this->resolve($path, $filters);
        $path    = $config["path"]    ?? $options["path"]    ?? $path; // Cache directory location
        $filters = $config["filters"] ?? $options["filters"] ?? [];
        $storage = $config["storage"] ?? $options["storage"] ?? null;
        $output  = $config["output"]  ?? $options["output"]  ?? realpath($path);

        //
        // Apply image resolution limitation
        if(!is_instanceof($this->maxResolution, ThumbnailFilter::class))
            throw new NotFoundHttpException("Resolution filter \"".$this->maxResolution."\" must inherit from ".ThumbnailFilter::class);

        //
        // Extract last filter
        $filters = array_filter($filters, fn($f) => class_implements_interface($f, FilterInterface::class));
        $formatter = end($filters);
        if($formatter === false)
            throw new NotFoundHttpException("Last filter is missing.");

        //
        // Apply size limitation to bitmap only
        if(class_implements_interface($formatter, BitmapFilterInterface::class)) {

            $definitionFilters = array_filter($formatter->getFilters(), fn($f) => $f instanceof ThumbnailFilter);
            if(empty($definitionFilters))
                $formatter->addFilter(new $this->maxResolution);

            if(!class_implements_interface($formatter, FormatFilterInterface::class))
                throw new NotFoundHttpException("Last filter \"".($formatter ? get_class($formatter) : null)."\" must implement \"".FormatFilterInterface::class."\"");
        }

        $filtersButLast = array_slice($filters, 0, count($filters)-1);
        foreach($filtersButLast as $filter) {

            if(class_implements_interface($filter, FormatFilterInterface::class))
                throw new NotFoundHttpException("Only last filter must implement \"".FormatFilterInterface::class."\"");
        }

        $pathRelative = $this->flysystem->stripPrefix($output, $storage);
        $pathCache = $pathRelative;

        // Encode path using hashid only: make sure the path is matching route generator
        // ... Otherwise, the controller will take over. Lines below make sure suffix is applied including filter operations
        // $pathExtras   = array_map(fn ($f) => is_stringeable($f) ? strval($f) : null, $filters);
        // $pathCache    = path_suffix($pathRelative, $pathExtras  );

        if(!$pathRelative) {

            if(!$this->fallback)
                throw new NotFoundHttpException($path ? "Image not found behind system path \"$path\"." : "Empty path provided.");

            return $this->noImage;
        }

        //
        // Compute a response.. (if cache not found)
        if ($config["local_cache"] ?? true) {

            $localCache = array_pop_key("local_cache", $config);
            if(!is_string($localCache)) $localCache = $this->localCache;
            if(!$this->flysystem->hasStorage($this->localCache))
                throw new InvalidArgumentException("\"".$this->localCache."\" storage not found in your Flysystem configuration.");

            if(!$this->flysystem->fileExists($pathCache, $localCache)) {

                set_time_limit($this->timeout);

                $filteredPath = $this->filter($path, $filters, array_merge($config, ["local_cache" => false])) ?? $path;
                if(!file_exists($filteredPath)) {

                    if(!$this->fallback)
                        throw new NotFoundHttpException($pathCache  ? "Image \"$pathCache\" not found." : "Empty path provided.");

                    return $this->noImage;
                }

                $this->flysystem->mkdir(dirname($pathCache), $localCache);
                $this->flysystem->write($pathCache, file_get_contents($filteredPath), $localCache);

                set_time_limit(30);

                if($formatter->getPath() === null) unlink_tmpfile($filteredPath);
            }

            return $this->flysystem->prefixPath($pathCache, $localCache);
        }

        //
        // Use proper imagine service depending on the format
        $imagine = $formatter instanceof SvgFilter ? $this->imagineSvg : $this->imagineBitmap;

        //
        // GD does not support other palette than RGB..
        //if($this->imagine instanceof \Imagine\Gd\Imagine && is_cmyk($pathPublic))
        //   cmyk2rgb($pathPublic); // Not working yet..
        try { $image = $imagine->open($path); }
        catch (Exception $e) { return null; }

        if($formatter instanceof BitmapFilterInterface) {
            $image->usePalette(new \Imagine\Image\Palette\RGB());
            $image->strip();
        }

        // Apply filters
        foreach ($filters as $filter) {

            $oldImage = $image;
            $image = $filter->apply($oldImage);
            if(spl_object_id($image) != spl_object_id($oldImage))
                $oldImage->__destruct();
        }

        // Last filter is in charge of saving the final image
        // So we can safely destroy it
        $image->__destruct();

        return $formatter->getPath();
    }
}
