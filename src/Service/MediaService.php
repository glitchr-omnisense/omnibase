<?php

namespace Base\Service;

use Base\Controller\UX\MediaController;
use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Base\Imagine\Filter\Format\BitmapFilterInterface;

use Base\Imagine\Filter\Format\SvgFilter;
use Base\Imagine\Filter\Format\SvgFilterInterface;
use Base\Imagine\Filter\FormatFilterInterface;
use Base\Routing\RouterInterface;
use Imagine\Exception\NotSupportedException;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Exception;
use Imagine\Filter\Basic\Autorotate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class MediaService extends FileService implements MediaServiceInterface
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

    protected string $localCache;

    /** @var ?int */
    protected ?int $timeout;
    /** @var ?string */
    protected ?int $fallback;
    /** @var string */
    protected string $maxResolution;
    /** @var string */
    protected string $maxQuality;
    /** @var array */
    protected array $noImage;
    /** @var ?bool */
    protected ?bool $debug;

    /** @var ?bool */
    protected ?bool $enableWebp;

    public function __construct(
        Environment $twig,
        RouterInterface $router,
        ObfuscatorInterface $obfuscator,
        FlysystemInterface $flysystem,
        ParameterBagInterface $parameterBag,
        ImagineInterface $imagineBitmap,
        ImagineInterface $imagineSvg,
        ?Profiler $profiler
    )
    {
        parent::__construct($twig, $router, $obfuscator, $flysystem);

        $this->profiler      = $profiler;

        $this->imagineBitmap = $imagineBitmap;
        $this->imagineSvg    = $imagineSvg;

        $this->timeout       = $parameterBag->get("base.images.timeout");
        $this->fallback      = $parameterBag->get("base.images.fallback");
        $this->maxResolution = $parameterBag->get("base.images.max_resolution");
        $this->maxQuality    = $parameterBag->get("base.images.max_quality");
        $this->enableWebp    = $parameterBag->get("base.images.enable_webp");
        $this->noImage       = $parameterBag->get("base.images.no_image") ?? [];
        $this->debug         = $parameterBag->get("base.images.debug");

        $this->twig          = $twig;

        // Local cache directory for filtered images
        $this->localCache = "local.cache";
    }

    public function getMaximumQuality()
    {
        return $this->maxQuality;
    }
    public function isWebpEnabled()
    {
        return $this->enableWebp;
    }

    public function audio(array|string|null $path, array $config = [], array $filters = []): array|string|null
    {
        if ($path === null) {
            return null;
        }

        $output = [];

        $pathList = is_array($path) ? $path : [$path];
        foreach ($pathList as $p) {

            $output[] = $this->generate("ux_serve", [], $path, $config);
        }

        return is_array($path) ? $output : first($output);
    }

    public function video(array|string|null $path, array $config = [], array $filters = []): array|string|null
    {
        if ($path === null) {
            return null;
        }

        $output = [];

        $pathList = is_array($path) ? $path : [$path];
        foreach ($pathList as $p) {

            $output[] = $this->generate("ux_serve", [], $path, $config);
        }

        return is_array($path) ? $output : first($output);
    }

    public function soundify(null|array|string $path, array $attributes = []): ?string
    {
        if (!$path) {
            return $path;
        }
        if (is_array($path)) {
            return array_map(fn ($s) => $this->soundify($s, $attributes), $path);
        }

        $sources = $this->audio($path);
        $sources = is_array($sources) ? $sources : [$sources];

        return $this->twig->render("@Base/media/audio.html.twig", [
            "sources"  => $sources,
            "attr"     => $attributes,
        ]);
    }

    public function vidify(null|array|string $path, array $attributes = []): ?string
    {
        if (!$path) {
            return $path;
        }
        if (is_array($path)) {
            return array_map(fn ($s) => $this->vidify($s, $attributes), $path);
        }

        $sources = $this->video($path);
        $sources = is_array($sources) ? $sources : [$sources];

        return $this->twig->render("@Base/media/video.html.twig", [
            "sources"  => $sources,
            "attr"     => $attributes,
        ]);
    }

    public function image(array|string|null $path, array $config = [], array $filters = []): array|string|null
    {
        $supports_webp   = array_pop_key("webp", $config) ?? browser_supports_webp();

        $extension = array_pop_key("extension", $config) ?? $this->getExtension($path);
        if ($extension) {
            $supports_webp &= ($extension != "svg");
        }

        if ($path === null) {
            return null;
        }

        $output = [];

        $pathList = is_array($path) ? $path : [$path];
        foreach ($pathList as $p) {

            if($supports_webp)
                $output[] = $supports_webp ? 
                    $this->generate("ux_imageWebp", [], $p, array_merge($config, ["filters" => $filters])) :
                    $this->generate(array_key_exists("extension", $config) ? "ux_imageExtension" : "ux_image", [], $path, array_merge($config, ["extension" => $extension, "filters" => $filters]));
        }

        return is_array($path) ? $output : first($output);
    }

    public function imageSet(null|array|string $path, ...$srcset): ?string
    {
        if (!$path) {
            return null;
        }
        if (is_array($path)) {
            return array_map(fn ($s) => $this->imageSet($s), $path);
        }

        $srcset = array_map(fn ($src) => array_pad(is_array($src) ? $src : [$src,$src], 2, null), $srcset);
        $srcset = implode(", ", array_map(fn ($src) => $this->thumbnail($path, $src[0], $src[1]). " ".$src[0]."w ".$src[1]."h", $srcset));
        return str_strip(($attributes["srcset"] ?? $attributes["data-srcset"] ?? "").",".$srcset, ",");
    }

    public function imagify(null|array|string $path, array $attributes = [], ...$srcset): ?string
    {
        if (!$path) {
            return $path;
        }
        if (is_array($path)) {
            return array_map(fn ($s) => $this->imagify($s, $attributes), $path);
        }

        $lazyload = array_pop_key("lazy", $attributes);
        $lazybox  = array_pop_key("lazy-box", $attributes);

        $srcset = array_map(fn ($src) => array_pad(is_array($src) ? $src : [$src,$src], 2, null), $srcset);
        $srcset = implode(", ", array_map(fn ($src) => $this->thumbnail($path, $src[0], $src[1]). " ".$src[0]."w ".$src[1]."h", $srcset));
        $attributes[$lazyload ? "data-srcset" : "srcset"] = str_strip(($attributes["srcset"] ?? $attributes["data-srcset"] ?? "").",".$srcset, ",");

        return $this->twig->render("@Base/media/image.html.twig", [
            "path"     => $this->image($path),
            "attr"     => $attributes,
            "lazyload" => $lazyload,
            "lazybox"  => $lazybox
        ]);
    }
    
    public function crop(array|string|null $path, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null, string $position = "leftop", array $config = [], array $filters = []): array|string|null
    {
        $filters[] = new CropFilter(
            $x,
            $y,
            $width,
            $height,
            $position
        );

        $config = array_key_removes($config, "width", "height", "x", "y", "position");
        return $this->image($path, $config, $filters);
    }

    public function thumbnailInset(array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($path, $width, $height, $filters, array_merge($config, ["mode" => ImageInterface::THUMBNAIL_INSET]));
    }
    public function thumbnailOutbound(array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($path, $width, $height, $filters, array_merge($config, ["mode" => ImageInterface::THUMBNAIL_OUTBOUND]));
    }
    public function thumbnailNoclone(array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($path, $width, $height, $filters, array_merge($config, ["mode" => ImageInterface::THUMBNAIL_FLAG_NOCLONE]));
    }
    public function thumbnailUpscale(array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($path, $width, $height, $filters, array_merge($config, ["mode" => ImageInterface::THUMBNAIL_FLAG_UPSCALE]));
    }
    public function thumbnail(array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        $filters[] = new ThumbnailFilter(
            $width,
            $height ?? null,
            $config["mode"]  ?? ImageInterface::THUMBNAIL_INSET,
            $config["resampling"] ?? ImageInterface::FILTER_UNDEFINED
        );

        $config = array_key_removes($config, "width", "height", "mode", "resampling");
        return $this->image($path, $config, $filters);
    }

    public function obfuscate(string|null $path, array $config = [], array $filters = []): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = "/".str_strip($path, $this->router->getAssetUrl(""));
        
        $config["path"] = $path;
        $config["options"] = array_merge(["quality" => $this->getMaximumQuality()], $config["options"] ?? []);
        $config["local_cache"] = $config["local_cache"] ?? null;
        if (!empty($filters)) {
            $config["filters"] = $filters;
        }

        while (($pathConfig = $this->obfuscator->decode(basename($path)))) {
            
            $path = $pathConfig["path"] ?? $path;
            $config["path"] = $path;
            $config["filters"] = array_merge_recursive($pathConfig["filters"] ?? [], $config["filters"] ?? []);
            $config["options"] = array_merge_recursive($pathConfig["options"] ?? [], $config["options"] ?? []);
            $config["local_cache"] = $pathConfig["local_cache"] ?? $config["local_cache"];
        }

        $data = $this->obfuscator->encode($config);
        if ($this->obfuscator->isShort()) {
            $data = path_subdivide(str_replace("-", "", $data), 5, 2);
        } else {
            $data = path_subdivide($data, self::CACHE_SUBDIVISION, self::CACHE_SUBDIVISION_LENGTH);
        }

        return $data;
    }

    public function lightbox(
        null|array|string $path,
        array $attributes = [],
        array|string $lightboxId = null,
        array|string $lightboxTitle = null,
        array $lightboxAttributes = [],
        ...$srcset
    ): ?string
    {
        $lightboxPathType = gettype($path);
        $lightboxIdType = gettype($lightboxId);
        $lightboxTitleType = gettype($lightboxTitle);

        if ($path != null && $lightboxPathType !== $lightboxIdType && $lightboxIdType !== gettype(null)) {
            throw new Exception("Unexpected `lightboxId` type parameter received: ".$lightboxIdType." (expected:".$lightboxPathType.")");
        }
        if ($path != null && $lightboxPathType !== $lightboxTitleType && $lightboxTitleType !== gettype(null)) {
            throw new Exception("Unexpected `lightboxTitle` type parameter received: ".$lightboxIdType." (expected:".$lightboxPathType.")");
        }

        if (!$path) {
            return $path;
        }
        if (is_array($path)) {
            return array_map(fn ($k) => $this->lightbox($path[$k], $attributes, $lightboxId[$k], $lightboxTitle[$k], $lightboxAttributes), array_keys($path));
        }

        $routeName = $this->router->getRouteName($path);
        if ($routeName && !str_starts_with($routeName, "ux_")) {
            $path = $this->image($path);
        }

        $lightboxAttributes["loading"] ??= "lazy";
        $lightboxAttributes["class"] = trim(($lightboxAttributes["class"] ?? "")." lightbox-wrapper");
        $lightboxAttributes["data-lightbox"] = $lightboxId ?? "lightbox";
        if ($lightboxTitle !== null) {
            $lightboxAttributes["data-title"] = $lightboxTitle;
        }

        $lazyload = array_pop_key("lazy", $attributes);
        $lazybox = array_pop_key("lazy-box", $attributes);

        $srcset = array_map(fn ($src) => array_pad(is_array($src) ? $src : [$src,$src], 2, null), $srcset);
        $srcset = implode(", ", array_map(fn ($src) => $this->thumbnail($path, $src[0], $src[1]). " ".$src[0]."w ".$src[1]."h", $srcset));
        $attributes[$lazyload ? "data-srcset" : "srcset"] = str_strip(($attributes["srcset"] ?? $attributes["data-srcset"] ?? "").",".$srcset, ",");

        return $this->twig->render("@Base/media/image-lightbox.html.twig", [
            "path" => $path,
            "attr" => $attributes,
            "attr_lightbox" => $lightboxAttributes,
            "lazyload" => $lazyload,
            "lazybox"  => $lazybox
        ]);
    }

    public function generate(string $proxyRoute, array $proxyRouteParameters = [], ?string $path = null, array $config = []): ?string
    {
        if (!$path) {
            return null;
        }

        $warmup = array_pop_key("warmup", $config);

        $config["filters"] ??= [];
        $routeUrl = parent::generate($proxyRoute, $proxyRouteParameters, $path, $config);

        // Call controller to warmup image
        if ($warmup && $this->mediaController !== null) {
            $routeMatch = $this->router->getRouteMatch($routeUrl);

            list($className, $controllerName) = array_pad(explode("::", $routeMatch["_controller"] ?? ""), 2, null);
            if (is_instanceof($className, get_class($this->mediaController)) && $controllerName) {

                $routeParameters = array_key_removes($routeMatch, "_route", "_controller");
                $this->mediaController->{$controllerName}(...$routeParameters);
            }
        }

        return $routeUrl;
    }

    public function resolve(string $data, array $filters = []): array
    {
        $config = parent::resolve($data) ?? [];
        $config["filters"]  = array_merge($config["filters"] ?? [], $filters);
        return $config;
    }

    public function serve(?string $file, int $status = 200, array $headers = []): ?Response
    {
        if (!file_exists($file)) {
            if (!$this->fallback) {
                throw is_length_safe($file) ?
                    new NotFoundHttpException($file ? "Image \"" . str_shorten($file, 50, SHORTEN_MIDDLE) . "\" not found." : "Empty path provided.") :
                    new \LogicException("Image \"" . str_shorten($file, 50, SHORTEN_MIDDLE) . "\" overflowed the PHP_MAXPATHLEN (= " . constant("PHP_MAXPATHLEN") . ") limit. Maybe use a compress option (\"gzcompress\",\"gzdeflate\",\"gzencode\") ?");
            }

            $file = $this->getNoImage($this->getExtension($file));
            array_pop_key("http_cache", $headers);
        }

        $useProfiler = $headers["profiler"] ?? true;
        if ($this->profiler !== null && !$useProfiler) {
            $this->profiler->disable();
        }

        return parent::serve($file, $status, $headers);
    }

    public function isCached(?string $path, FilterInterface|array $config = [], array $filters = []): bool
    {
        if (!is_array($filters)) {
            $filters = [$filters];
        }

        //
        // Resolve nested paths
        $options = $this->resolve($path, $filters);
        $path    = $config["path"]    ?? $options["path"]    ?? $path; // Cache directory location
        $filters = $config["filters"] ?? $options["filters"] ?? [];
        $storage = $config["storage"] ?? $options["storage"] ?? null;
        $output  = $config["output"]  ?? $options["output"]  ?? realpath($path);

        //
        // Apply image resolution limitation
        if (!is_instanceof($this->maxResolution, ThumbnailFilter::class)) {
            throw new NotFoundHttpException("Resolution filter \"".$this->maxResolution."\" must inherit from ".ThumbnailFilter::class);
        }

        //
        // Extract last filter
        $filters = array_filter($filters, fn ($f) => class_implements_interface($f, FilterInterface::class));
        $formatter = end($filters);
        if ($formatter === null) {
            throw new NotFoundHttpException("Last filter is missing.");
        }

        //
        // Apply size limitation to bitmap only
        if (class_implements_interface($formatter, BitmapFilterInterface::class)) {
            $definitionFilters = array_filter($formatter->getFilters(), fn ($f) => $f instanceof ThumbnailFilter);
            if (empty($definitionFilters)) {
                $formatter->addFilter(new $this->maxResolution());
            }

            if (!class_implements_interface($formatter, FormatFilterInterface::class)) {
                throw new NotFoundHttpException("Last filter \"".($formatter ? get_class($formatter) : null)."\" must implement \"".FormatFilterInterface::class."\"");
            }
        }

        $filtersButLast = array_slice($filters, 0, count($filters)-1);
        foreach ($filtersButLast as $filter) {
            if (class_implements_interface($filter, FormatFilterInterface::class)) {
                throw new NotFoundHttpException("Only last filter must implement \"".FormatFilterInterface::class."\"");
            }
        }

        $pathRelative = $this->flysystem->stripPrefix($output, $storage);
        $pathCache = $pathRelative;

        // NB: Encode path using hash only: make sure the path is matching route generator
        // ... Otherwise, the controller will take over
        // $pathExtras   = array_map(fn ($f) => is_stringeable($f) ? strval($f) : null, $filters);
        // $pathCache    = path_suffix($pathRelative, $pathExtras  );

        //
        // Compute a response.. (if cache not found)
        if ($config["local_cache"] ?? true) {
            $localCache = array_pop_key("local_cache", $config);
            if (!is_string($localCache)) {
                $localCache = $this->localCache;
            }
            if (!$this->flysystem->hasStorage($this->localCache)) {
                throw new InvalidArgumentException("\"".$this->localCache."\" storage not found in your Flysystem configuration.");
            }

            return $this->flysystem->fileExists($pathCache, $localCache);
        }

        return false;
    }

    public function filter(?string $path, array $config = [], FilterInterface|array $filters = []): ?string
    {
        if ($this->debug) {
            return $this->getNoImage($this->getExtension($path));
        }
        if (!is_array($filters)) {
            $filters = [$filters];
        }

        //
        // Resolve nested paths
        $options = $this->resolve($path, $filters);
        $path    = $config["path"]    ?? $options["path"]    ?? $path; // Cache directory location
        $filters = $config["filters"] ?? $options["filters"] ?? [];
        $storage = $config["storage"] ?? $options["storage"] ?? null;
        $output  = $config["output"]  ?? $options["output"]  ?? realpath($path);

        //
        // Apply image resolution limitation
        if (!is_instanceof($this->maxResolution, ThumbnailFilter::class)) {
            throw new NotFoundHttpException("Resolution filter \"".$this->maxResolution."\" must inherit from ".ThumbnailFilter::class);
        }

        //
        // Extract last filter
        $filters = array_filter($filters, fn ($f) => class_implements_interface($f, FilterInterface::class));
        $formatter = end($filters);
        if ($formatter === false) {
            throw new NotFoundHttpException("No filter provided at least one must be provided (and must implement \"".FormatFilterInterface::class."\").");
        }
        if (!$formatter instanceof FormatFilterInterface) {
            throw new NotFoundHttpException("The last filter must implement \"".FormatFilterInterface::class."\"). A \"".get_class($formatter)."\" received.");
        }

        //
        // Apply size limitation to bitmap only
        if (class_implements_interface($formatter, BitmapFilterInterface::class)) {
            $definitionFilters = array_filter($formatter->getFilters(), fn ($f) => $f instanceof ThumbnailFilter);
            if (empty($definitionFilters)) {
                $formatter->addFilter(new $this->maxResolution());
            }

            if (!class_implements_interface($formatter, FormatFilterInterface::class)) {
                throw new NotFoundHttpException("Last filter \"".($formatter ? get_class($formatter) : null)."\" must implement \"".FormatFilterInterface::class."\"");
            }
        }

        $filtersButLast = array_slice($filters, 0, count($filters)-1);
        foreach ($filtersButLast as $filter) {
            if (class_implements_interface($filter, FormatFilterInterface::class)) {
                throw new NotFoundHttpException("Only last filter must implement \"".FormatFilterInterface::class."\"");
            }
        }

        $pathRelative = $this->flysystem->stripPrefix($output, $storage);
        $pathCache = $pathRelative;

        // Encode path using hashid only: make sure the path is matching route generator
        // ... Otherwise, the controller will take over. Lines below make sure suffix is applied including filter operations
        // $pathExtras   = array_map(fn ($f) => is_stringeable($f) ? strval($f) : null, $filters);
        // $pathCache    = path_suffix($pathRelative, $pathExtras  );

        if (!$pathRelative) {
            if (!$this->fallback) {
                throw new NotFoundHttpException($path ? "Image not found behind system path \"$path\"." : "Empty path provided.");
            }

            return $this->getNoImage($this->getExtension($path));
        }

        //
        // Compute a response.. (if cache not found)
        if ($config["local_cache"] ?? false) {
            $localCache = array_pop_key("local_cache", $config);
            if (!is_string($localCache)) {
                $localCache = $this->localCache;
            }
            if (!$this->flysystem->hasStorage($this->localCache)) {
                throw new InvalidArgumentException("\"".$this->localCache."\" storage not found in your Flysystem configuration.");
            }

            if (!$this->flysystem->fileExists($pathCache, $localCache)) {
                $maxExecutionTime = ini_get('max_execution_time');
                set_time_limit($this->timeout);

                $filteredPath = $this->filter($path, array_merge($config, ["local_cache" => false]), $filters) ?? $path;
                if (!file_exists($filteredPath)) {

                    if (!$this->fallback) {
                        throw new NotFoundHttpException($pathCache ? "Image \"$pathCache\" not found." : "Empty path provided.");
                    }

                    return $this->getNoImage($this->getExtension($path));
                }

                try {
                    $this->flysystem->mkdir(dirname($pathCache), $localCache);
                    $this->flysystem->write($pathCache, file_get_contents($filteredPath), $localCache);
                } catch(\League\Flysystem\UnableToCreateDirectory $e) {
                    $localDir = $this->flysystem->prefixPath("", $localCache);
                    mkdir_length_safe($localDir."/".dirname($pathCache), 0777, true);
                }

                set_time_limit($maxExecutionTime);

                if ($formatter->getPath() === null) {
                    unlink_tmpfile($filteredPath);
                }
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
        catch (Exception $e) { return $this->fallback ? $this->getNoImage($this->getExtension($path)) : null; }

        try {

            if ($formatter instanceof BitmapFilterInterface) {
                $image->usePalette(new \Imagine\Image\Palette\RGB());
                $image->strip();
            }

        } catch (Exception $e) {

            if(!$this->fallback) throw $e;
        }

        // Apply filters
        foreach ($filters as $filter) {

            $oldImage = $image;
            try { $image = $filter->apply($oldImage); }
            catch (\Exception $e) {

                if(!$this->fallback) throw $e;
                $image = $oldImage;
            }

            if (spl_object_id($image) != spl_object_id($oldImage)) {
                $oldImage->__destruct();
            }
        }

        // Last filter is in charge of saving the final image
        // So we can safely destroy it
        $image->__destruct();

        // Fallback
        if(!file_exists($formatter->getPath()) && $this->fallback)
            return $this->getNoImage($this->getExtension($path));

        return $formatter->getPath();
    }

    public function getNoImage(null|string|FormatFilterInterface $extensionOrFormatter = null)
    {
        if(is_string($extensionOrFormatter))
            $extension = $extensionOrFormatter;

        $extension ??= "png";
        if($extensionOrFormatter instanceof SvgFilterInterface)
            $extension = "svg";

        $extensions = array_map(fn($a) => $a["extension"], $this->noImage);

        $noImage = first(array_search_by($this->noImage, "extension", $extension));
        if(!$noImage) {
            throw new Exception("Replacement image not defined for \"" . strtoupper($extension) . "\"." . PHP_EOL .
                "Please define `base.images.no_image." . $extension . "` or disable `base.images.fallback`"
            );
        }

        return $noImage["path"];
    }
}
