<?php

namespace Base\Service;

use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Base\Imagine\Filter\Format\SvgFilter;
use Base\Imagine\Filter\Format\WebpFilter;
use Base\Repository\Layout\ImageCropRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * omnibase's image route, answered for any host (audit "lesringostars face
 * au master plan", R6): what Base\Controller\UX\MediaController answered under
 * Symfony, moved here as it was, so that a host without the framework serves
 * the same images and thumbnails from the same addresses - an obfuscated
 * `data` resolved by MediaService, its file filtered by Imagine (a format, a
 * thumbnail, a crop) and kept in the local.cache storage, a redirection to
 * the address with the right extension, the placeholder when nothing
 * resolves.
 *
 * MediaController keeps its routes and its actions, and delegates here;
 * omnibridge/laravel declares ROUTES on Laravel's router and calls the same
 * actions. Its answers are HttpFoundation responses, which both frameworks
 * send as they are; what it throws for a missing image (NotFoundHttpException)
 * both render as a 404. The profiler's part stays the host's: $profiler says
 * whether the request keeps it (Symfony: not on an ux_ route), the served
 * image carries that flag to MediaService::serve() as before.
 */
class ImageRoute
{
    /**
     * The image routes, in the order MediaController declares them (Symfony
     * matches them in that order, a host that declares them keeps it): name
     * => [path, action, cacheless]. The action is this class's method;
     * cacheless, the same action without the local cache. The data takes
     * any character, slashes included (".+"). tests/Service/ImageRouteTest
     * keeps the list equal to MediaController's #[Route] attributes.
     *
     * ux_debug_image and ux_debug_imageExtension (MediaController::ImageDebug,
     * for an editor, through Security) are left out: Symfony's alone.
     */
    public const ROUTES = [
        'ux_serve' => ['/contents/{data}', 'contents', false],
        'ux_imageCropExtension_cacheless' => ['/images/cacheless/cropper/{identifier}/{data}/image.{extension}', 'imageCrop', true],
        'ux_imageCrop_cacheless' => ['/images/cacheless/cropper/{identifier}/{data}', 'imageCrop', true],
        'ux_imageCropExtension' => ['/images/cropper/{identifier}/{data}/image.{extension}', 'imageCrop', false],
        'ux_imageCrop' => ['/images/cropper/{identifier}/{data}', 'imageCrop', false],
        'ux_imageWebp_cacheless' => ['/images/cacheless/{data}/image.webp', 'imageWebp', true],
        'ux_imageExtension_cacheless' => ['/images/cacheless/{data}/image.{extension}', 'image', true],
        'ux_image_cacheless' => ['/images/cacheless/{data}', 'image', true],
        'ux_imageWebp' => ['/images/{data}/image.webp', 'imageWebp', false],
        'ux_imageSvg' => ['/images/{data}/image.svg', 'imageSvg', false],
        'ux_imageExtension' => ['/images/{data}/image.{extension}', 'image', false],
        'ux_image' => ['/images/{data}', 'image', false],
    ];

    protected MediaService $mediaService;

    /** @var FileService MediaService read as its parent: the contents' resolution (ux_serve) */
    protected mixed $fileService;

    protected FlysystemInterface $flysystem;

    protected ?ImageCropRepository $imageCropRepository;

    protected ?bool $localCache;

    protected bool $profiler = true;

    public function __construct(MediaService $mediaService, FlysystemInterface $flysystem, ?ImageCropRepository $imageCropRepository = null, ?bool $localCache = null)
    {
        $this->mediaService = $mediaService;
        $this->fileService = cast($mediaService, FileService::class);
        $this->flysystem = $flysystem;
        $this->imageCropRepository = $imageCropRepository;
        $this->localCache = $localCache ?? true;
    }

    /**
     * The same route for one request: the local cache used or not (the
     * cacheless routes), the profiler kept or not (MediaService::serve()).
     */
    public function with(?bool $localCache = null, ?bool $profiler = null): static
    {
        $route = clone $this;
        $route->localCache = $localCache ?? $this->localCache;
        $route->profiler = $profiler ?? $this->profiler;

        return $route;
    }

    /** The same route without the local cache: the cacheless routes. */
    public function cacheless(): static
    {
        return $this->with(false);
    }

    /**
     * A redirection to an image route, its cacheless twin when the local
     * cache is off, as MediaController::redirectToRoute() made it.
     */
    public function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        $cacheless = !$this->localCache ? "_cacheless" : "";

        return new RedirectResponse($this->mediaService->getRouter()->generate($route . $cacheless, $parameters), $status);
    }

    /** A stored file as it is (ux_serve), an attachment when its data says so. */
    public function contents($data): Response
    {
        $config = $this->fileService->resolve($data);
        if (!array_key_exists("path", $config ?? [])) {
            throw new NotFoundHttpException('Not Found');
        }

        $path = $config["path"];

        $contents = file_exists($path) ? file_get_contents($path) : $this->flysystem->read($path, $config["storage"] ?? null);
        if ($contents === null) {
            throw new NotFoundHttpException('Not Found');
        }

        $options = $config["options"];
        $options["attachment"] = $config["attachment"] ?? null;

        return $this->fileService->serveContents($contents, 200, $options);
    }

    public function imageCrop($data, string $identifier, ?string $extension = null): Response
    {
        //
        // Extract parameters
        $config = $this->mediaService->resolve($data);
        if (!array_key_exists("path", $config)) {
            return $this->mediaService->serve(null);
        }

        $filters = $config["filters"] ?? [];
        $options = $config["options"] ?? [];
        $path = $config["path"] ?? null;
        if (!$path) {
            return $this->mediaService->serve(null);
        }

        // Redirect to proper path
        $extensions = $this->mediaService->getExtensions($path);
        if (!$extensions) {
            throw new NotFoundHttpException('Not Found');
        }
        if ($extension == null || !in_array($extension, $extensions)) {
            return $this->imageCrop($data, $identifier, first($extensions));
        }

        //
        // Get the most accurate cropping
        $uuid = basename($path);

        // Dimension information
        $imagesize = getimagesize($path);
        $naturalWidth = $imagesize[0] ?? 0;
        if ($naturalWidth == 0) {
            throw new NotFoundHttpException('Not Found');
        }
        $naturalHeight = $imagesize[1] ?? 0;
        if ($naturalHeight == 0) {
            throw new NotFoundHttpException('Not Found');
        }

        // Providing "label" information
        $imageCrop = $this->imageCropRepository?->cacheOneBySlug($identifier, ["image.source" => $uuid]);

        // Providing just a "ratio" number
        if ($imageCrop === null && $this->imageCropRepository !== null && preg_match("/^(\d+|\d*\.\d+)$/", $identifier, $matches)) {
            $ratio = floatval($matches[1]);
            $ratio0 = $ratio / ($naturalWidth / $naturalHeight);

            $imageCrop = $this->imageCropRepository->cacheOneByRatio0ClosestTo($ratio0, ["image.source" => $uuid], [], [], ["ratio0" => "e.width0/e.height0"])[0] ?? null;
        }

        // Providing a "width:height" information
        $width = null;
        $height = null;
        if ($imageCrop === null && $this->imageCropRepository !== null && preg_match("/([0-9]+)[:x]([0-9]+)/", $identifier, $matches)) {
            $width = $matches[1];
            $width0 = $width / $naturalWidth;
            $height = $matches[2];
            $height0 = $height / $naturalHeight;

            $ratio = $height ? $width / $height : 0;
            $ratio0 = $width0 / $height0;
            if ($ratio0 == 0) {
                throw new NotFoundHttpException('Not Found');
            }

            $imageCrop = $this->imageCropRepository->findOneByRatio0ClosestToAndWidth0ClosestToAndHeight0ClosestTo($ratio0, $width0, $height0, ["image.source" => $uuid], [], [], ["ratio0" => "e.width0/e.height0"])[0] ?? null;
            if($imageCrop) $identifier = $imageCrop->getWidth() . "x" . $imageCrop->getHeight();
        }

        //
        // Apply filter
        // NB: Only applying cropping if ImageCrop is found ..
        //     .. otherwise some naughty users might be generating infinite amount of image
        if ($imageCrop) {
            array_prepend($filters, new CropFilter(
                $imageCrop->getX0(),
                $imageCrop->getY0(),
                $imageCrop->getWidth0(),
                $imageCrop->getHeight0()
            ));
        }

        // WARN: This has been removed, otherwise users might overload the server changing the size in the URL..
        // if($width && $height)
        //     array_prepend($filters, new ThumbnailFilter($height, $width));
        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $config["local_cache"] ?? $localCache;

        // File should be access from default "image" route to spare some computing time
        // NB: These lines below are commented to keep the same url and cache the image
        // $config["identifier"] = $identifier;
        // $data = $this->mediaService->obfuscate($path, $config, $filters);
        if ($imageCrop === null) {
            $identifier = "image";
        }

        $output = pathinfo_extension($data . "/" . $identifier, $extension);
        $path = $this->mediaService->filter($path, ["local_cache" => $localCache, "output" => $output, "storage" => $config["storage"] ?? null], new BitmapFilter(null, $options, $filters));

        return $this->mediaService->serve($path, 200, ["http_cache" => $path !== null, "profiler" => $this->profiler]);
    }

    public function imageWebp($data): Response
    {
        $config = $this->mediaService->resolve($data);

        if (!array_key_exists("path", $config)) {
            return $this->mediaService->serve(null);
        }

        $webp = $config["webp"] ?? $this->mediaService->isWebpEnabled();
        if (!$webp) {
            return $this->redirectToRoute("ux_image", ["data" => $data], Response::HTTP_MOVED_PERMANENTLY);
        }

        $mimeType = $config["mimetype"] ?? $this->mediaService->getMimeType($config["path"]);
        if ($mimeType == "image/svg+xml") {
            return $this->redirectToRoute("ux_imageSvg", ["data" => $data], Response::HTTP_MOVED_PERMANENTLY);
        }

        $filters = $config["filters"] ?? [];
        $options = $config["options"] ?? [];
        $identifier = $config["identifier"] ?? null;
        if ($identifier != null) {
            return $this->imageCrop($data, $identifier, "webp");
        }

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $config["local_cache"] ?? $localCache;

        $output = pathinfo_extension($data . "/image", "webp");

        $path = $this->mediaService->filter($config["path"], ["local_cache" => $localCache, "output" => $output, "storage" => $config["storage"] ?? null], new WebpFilter(null, $options, $filters));

        return $this->mediaService->serve($path, 200, ["http_cache" => $path !== null, "profiler" => $this->profiler]);
    }

    public function imageSvg($data): Response
    {
        $config = $this->mediaService->resolve($data);
        if (!array_key_exists("path", $config)) {
            return $this->mediaService->serve(null);
        }

        $filters = $config["filters"];
        $options = $config["options"];

        $mimeType = $config["mimetype"] ?? $this->mediaService->getMimeType($config["path"]);
        if ($mimeType != "image/svg+xml") {
            return $this->redirectToRoute("ux_image", ["data" => $data], Response::HTTP_MOVED_PERMANENTLY);
        }

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $config["local_cache"] ?? $localCache;

        $output = pathinfo_extension($data . "/image", "svg");
        $path = $this->mediaService->filter($config["path"], ["local_cache" => $localCache, "output" => $output, "storage" => $config["storage"] ?? null], new SvgFilter(null, $options, $filters));

        return $this->mediaService->serve($path, 200, ["http_cache" => $path !== null, "profiler" => $this->profiler]);
    }

    public function image($data, ?string $extension = null, bool $debug = false): Response
    {
        //
        // Extract parameters
        $config = $this->mediaService->resolve($data);

        $filters = $config["filters"] ?? [];
        $options = $config["options"] ?? [];
        $path = $config["path"] ?? null;
        $identifier = $config["identifier"] ?? null;

        // Redirect to proper path
        $extensions = $this->mediaService->getExtensions($path);
        if (!$extensions && $extension !== null) $extensions[] = $extension;
        if (!$extensions) {
            return $path === null ? $this->mediaService->serve(null) : throw new NotFoundHttpException('Not Found');
        }

        if ($extension === null || !in_array($extension, $extensions)) {
            return $this->redirectToRoute("ux_imageExtension", ["data" => $data, "extension" => first($extensions)], Response::HTTP_MOVED_PERMANENTLY);
        }

        // If cropping identifier found
        if ($identifier != null) {
            return $this->imageCrop($data, $identifier, $extension);
        }

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $config["local_cache"] ?? $localCache;

        $output = pathinfo_extension($data . "/image", $extension);
        $path = $this->mediaService->filter($config["path"] ?? "", ["local_cache" => $localCache, "output" => $output, "storage" => $config["storage"] ?? null], new BitmapFilter(null, $options, $filters));
        if ($debug) {

            dump($data, $config, $path);
            exit(1);
        }

        return $this->mediaService->serve($path, 200, ["http_cache" => $path !== null, "profiler" => $this->profiler]);
    }
}
