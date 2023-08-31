<?php

namespace Base\Service;

use Base\Routing\AdvancedRouter;
use Base\Routing\RouterInterface;
use finfo;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;

/**
 *
 */
class FileService implements FileServiceInterface
{
    protected const CACHE_SUBDIVISION = 3;
    protected const CACHE_SUBDIVISION_LENGTH = 1;

    /**
     * @var MimeTypes
     */
    protected MimeTypes $mimeTypes;

    /**
     * @var ObfuscatorInterface
     */
    protected $obfuscator;

    /**
     * @var FlysystemInterface
     */
    protected FlysystemInterface $flysystem;

    /**
     * @var RouterInterface
     */
    protected RouterInterface $router;

    /**
     * @var Environment
     */
    protected Environment $twig;

    /** * @var string */
    protected string $projectDir;

    /** * @var string */
    protected string $publicDir;

    public function __construct(Environment $twig, RouterInterface $router, ObfuscatorInterface $obfuscator, FlysystemInterface $flysystem)
    {
        $this->twig = $twig;
        $this->router = $router;
        $this->obfuscator = $obfuscator;
        $this->flysystem = $flysystem;
        $this->projectDir = $flysystem->getProjectDir();
        $this->publicDir = $flysystem->getPublicDir();

        $this->mimeTypes = new MimeTypes();
    }

    /**
     * @return string
     */
    public function getProjectDir()
    {
        return $this->projectDir;
    }

    /**
     * @return string
     */
    public function getPublicDir()
    {
        return $this->publicDir;
    }

    public function getExtension(null|string|array $fileOrMimetypeOrArray): ?string
    {
        return first($this->getExtensions($fileOrMimetypeOrArray));
    }

    public function getExtensions(null|string|array $fileOrMimetypeOrArray): array
    {
        if (!$fileOrMimetypeOrArray) {
            return [];
        }

        if (is_array($fileOrMimetypeOrArray)) {
            
            $extensions = [];
            $extensionList = array_map(fn($mimetype) => $this->getExtensions($mimetype), $fileOrMimetypeOrArray);
            foreach ($extensionList as $extension) {
                $extensions = array_merge($extensions, $extension);
            }

            return array_unique($extensions);
        }

        if (!filter_var($fileOrMimetypeOrArray, FILTER_VALIDATE_URL)) {
            $fileOrMimetypeOrArray = mime_content_type2($fileOrMimetypeOrArray) ?? $fileOrMimetypeOrArray;
        }

        return $this->mimeTypes->getExtensions($fileOrMimetypeOrArray);
    }

    public function getMimeType(null|string|array $fileOrContentsOrArray): null|string|array
    {
        if ($fileOrContentsOrArray === null) {
            return null;
        }
        if (is_array($fileOrContentsOrArray)) {
            return array_map(fn($f) => $this->getMimeType($f), $fileOrContentsOrArray);
        }

        // Attempt to read from flysystem
        $mimeType = $this->flysystem->mimeType($fileOrContentsOrArray);
        if ($mimeType && $mimeType !== "application/x-empty") {
            return $mimeType;
        }

        // Attempt to read mimetype
        $extension = pathinfo($fileOrContentsOrArray, PATHINFO_EXTENSION);
        $extension = $extension ?: $fileOrContentsOrArray; // Assume extension is provided without filename
        $mimeType = $this->mimeTypes->getMimeTypes($extension)[0] ?? null;
        if ($mimeType && $mimeType !== "application/x-empty") {
            return $mimeType;
        }


        // Attempt to guess mimetype using MimeTypes class
        try {
            
            return $this->mimeTypes->guessMimeType($fileOrContentsOrArray);

        } catch (InvalidArgumentException $e) {

            if(is_string($fileOrContentsOrArray) && !is_binary($fileOrContentsOrArray) && is_url($fileOrContentsOrArray))
                $fileOrContentsOrArray = $this->router->getUrl($fileOrContentsOrArray, [], AdvancedRouter::ABSOLUTE_URL);

            // Attempt to read based on custom mime_content_content method
            try { $mimeType = mime_content_type2($fileOrContentsOrArray); }
            catch (\ErrorException $e) { $mimeType = "application/x-empty"; }
            if ($mimeType && $mimeType !== "application/x-empty") {
                return $mimeType;
            }

            return explode(";", (new finfo(FILEINFO_MIME))->buffer($fileOrContentsOrArray))[0] ?? null; /* Read file content content */
        }
    }

    public function linkable(array|string|null $path, array $config = []): array|string|null
    {
        return $this->generate("ux_serve", [], $path, $config);
    }

    public function downloadable(array|string|null $path, array $config = []): array|string|null
    {
        $attachment = array_pop_key("attachment", $config);
        if (!$attachment) {
            $attachment = true;
        }

        return $this->generate("ux_serve", [], $path, array_merge($config, ["attachment" => $attachment]));
    }

    public function public(array|string|null $path, ?string $storage = null): array|string|null
    {
        return $this->flysystem->getPublic($path, $storage);
    }

    public function asset(string $path, ?string $packageName = null): ?string
    {
        return $this->router->getAssetUrl($path, $packageName);
    }

    /**
     * @param string|null $file
     * @return bool
     */
    public function isEmpty(?string $file)
    {
        return $file === null || preg_match("/application\/x-empty/", $this->getMimeType($file));
    }

    /**
     * @param string|null $file
     * @return false|int
     */
    public function isImage(?string $file)
    {
        return $file ? preg_match("/image\//", $this->getMimeType($file)) : false;
    }

    /**
     * @param string|null $file
     * @return bool
     */
    public function isSvg(?string $file)
    {
        return $this->getMimeType($file) == "image/svg+xml";
    }

    /**
     * @param $size
     * @param array $unitPrefix
     * @return string
     */
    public function filesize($size, array $unitPrefix = DECIMAL_PREFIX): string
    {
        return byte2str($size, $unitPrefix);
    }

    public function obfuscate(string|null $path, array $config = []): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = realpath($path);
        $path = "/" . str_strip($path, $this->router->getAssetUrl(""));

        $config["path"] = $path;
        $config["options"] = $config["options"] ?? [];
        $config["local_cache"] = $config["local_cache"] ?? null;

        while (($pathConfig = $this->obfuscator->decode(basename($path)))) {
            $config["path"] = $path = $pathConfig["path"] ?? $path;
            $config["options"] = array_merge_recursive2($pathConfig["options"] ?? [], $config["options"]);
            $config["local_cache"] = $pathConfig["local_cache"] ?? $config["local_cache"];
        }

        return $this->obfuscator->encode($config);
    }

    public function generate(string $proxyRoute, array $proxyRouteParameters = [], ?string $path = null, array $config = []): ?string
    {
        $routeMatch = $this->router->getRouteMatch($path) ?? [];
        if (array_key_exists("_route", $routeMatch) && $routeMatch["_route"] == $proxyRoute) {
            $data = $routeMatch["data"];
            $config["options"] = $config["options"] ?? [];
            $config["local_cache"] = $config["local_cache"] ?? null;

            if (($pathConfig = $this->obfuscator->decode($data))) {
                $path = $pathConfig["path"] ?? $path;
                $config["path"] = $path;
                $config["filters"] = array_merge_recursive($pathConfig["filters"] ?? [], $config["filters"] ?? []);
                $config["options"] = array_merge_recursive2($pathConfig["options"] ?? [], $config["options"]);
                $config["local_cache"] = $pathConfig["local_cache"] ?? $config["local_cache"];
            }

            $path = $path ?? array_pop_key("path", $config);
        }

        $extension = array_pop_key("extension", $config);
        if ($extension !== null) {
            $proxyRouteParameters["extension"] = $extension;
        }

        $host = array_pop_key("_host", $config); // Use custom _host if found
        $referenceType = array_pop_key("reference_type", $config); // Get reference type
        
        $data = $this->obfuscate($path, $config);
        if (!$data) {
            return null;
        }

        if ($host !== null) {
            $proxyRouteParameters["_host"] = $host;
        }

        $proxyRouteParameters["data"] = $data;

        $variadic = [$proxyRoute, $proxyRouteParameters];
        if ($referenceType !== null) {
            $variadic[] = $referenceType;
        }

        return $this->router->generate(...$variadic);
    }

    public function resolve(string $data): ?array
    {
        $config = [];
        $match = $this->router->getRouteMatch($data);
        $data = $match && array_key_exists("data", $match) ? $match["data"] : $data;

        $uuid = format_uuid(str_replace("/", "-", $data));
        if (Uuid::isValid($uuid)) {
            $data = $uuid;
        } else {
            $data = str_replace("/", "", $data);
        }

        $data = $this->obfuscator->decode($data);

        foreach ($data ?? [] as $key => $el) {
            $config[$key] = is_array($el) ? array_merge($config[$key] ?? [], $el) : $el;
        }

        if (array_key_exists("path", $config ?? [])) {
            $resolvedPath = $this->resolve($config["path"]);
            foreach ($resolvedPath ?? [] as $key => $el) {
                $config[$key] = is_array($el) ? array_merge($config[$key] ?? [], $el) : $el;
            }
        }

        if (!$config) {
            return null;
        }

        return array_merge($config, ["options" => $config["options"] ?? []]);
    }

    public function serve(?string $path, int $status = 200, array $headers = []): ?Response
    {
        return $this->serveContents(file_get_contents($path), $status, $headers);
    }

    public function serveContents(?string $contents, int $status = 200, array $headers = []): ?Response
    {
        $httpCache = array_pop_key("http_cache", $headers) ?? false;
        $attachment = array_pop_key("attachment", $headers) ?? false;

        $response = new Response($contents, $status, $headers);
        if ($this->isEmpty($contents)) {
            return $response;
        }

        if ($httpCache) {
            $response->setMaxAge(300);
            $response->setPublic();
            $response->setEtag(md5($response->getContent()));
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        $mimeType = $this->getMimeType($contents);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Length', strlen($contents));

        if ($attachment) {
            
            $extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
            $extensions = $this->getExtensions($mimeType);

            if ($attachment === true) {
                if ($mimeType) {
                    $attachment = explode("/", $mimeType)[0] . $extension;
                } else {
                    $attachment = "unnamed";
                }
            }

            if ($extension && !in_array($extension, $extensions)) {
                $extensions[] = $extension;
            }

            $attachment = pathinfo_extension($attachment, first($extensions));

            $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($attachment) . '"');
        }

        return $response;
    }
}
