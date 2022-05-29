<?php

namespace Base\Service;

use Exception;
use InvalidArgumentException;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\RouterInterface;

class FileService implements FileServiceInterface
{
    /**
     * @var MimeTypes
     */
    protected $mimeTypes;

    /**
     * @var Obfuscator
     */
    protected $obfuscator;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var AssetExtension
     */
    protected $assetExtension;
    /**
     * @var RouterInterface
     */
    protected $router;

    public function __construct(RouterInterface $router, ObfuscatorInterface $obfuscator, AssetExtension $assetExtension, Filesystem $filesystem)
    {
        $this->router = $router;
        $this->obfuscator = $obfuscator;
        $this->assetExtension = $assetExtension;
        $this->filesystem = $filesystem;

        $this->mimeTypes = new MimeTypes();
    }

    public function getFilesystem() { return $this->filesystem; }
    public function getExtensions(null|string|array $fileOrMimetypeOrArray): array
    {
        if(!$fileOrMimetypeOrArray) return [];
        if(is_array($fileOrMimetypeOrArray)) {

            $extensions = [];
            $extensionList = array_map(fn($mimetype) => $this->getExtensions($mimetype), $fileOrMimetypeOrArray);
            foreach ( $extensionList as $extension )
                $extensions = array_merge($extensions,$extension);

            return array_unique($extensions);
        }

        $mimeType = mime_content_type2($fileOrMimetypeOrArray) ?? $fileOrMimetypeOrArray;
        return $this->mimeTypes->getExtensions($mimeType);
    }

    public function getMimeType(null|string|array $fileOrContentsOrArray):null|string|array  {

        if($fileOrContentsOrArray === null) return null;
        if(is_array($fileOrContentsOrArray)) return array_map(fn($f) => $this->getMimeType($f), $fileOrContentsOrArray);

        // Attempt to read from flysystem
        $mimeType = $this->filesystem->mimeType($fileOrContentsOrArray);
        if($mimeType && $mimeType !== "application/x-empty") return $mimeType;

        // Attempt to read based on custom mime_content_content method
        $mimeType = mime_content_type2($fileOrContentsOrArray);
        if($mimeType && $mimeType !== "application/x-empty") return $mimeType;

        // Attempt to read mimetype
        $extension = pathinfo($fileOrContentsOrArray, PATHINFO_EXTENSION);
        $extension = $extension ? $extension : $fileOrContentsOrArray; // Assume extension is provided without filename
        $mimeType = $this->mimeTypes->getMimeTypes($extension)[0] ?? null;
        if($mimeType && $mimeType !== "application/x-empty") return $mimeType;

        // Attempt to guess mimetype using MimeTypes class
        try { return $this->mimeTypes->guessMimeType($fileOrContentsOrArray); }
        catch (InvalidArgumentException $e) { return explode(";", (new \finfo(FILEINFO_MIME))->buffer($fileOrContentsOrArray))[0] ?? null; /* Read file content content */ }
    }

    public function downloadable(array|string|null $path, array $config = []): array|string|null
    {
        $attachment = array_pop_key("attachment", $config);
        if(!$attachment) $attachment = true;

        return $this->generate("ux_serve", [], $path, array_merge($config, ["attachment" => $attachment]));
    }

    public function public(array|string|null $path, ?string $storage = null): array|string|null { return $this->getFilesystem()->read($path, $storage); }

    public function isEmpty(?string $file) { return $file === null || preg_match("/application\/x-empty/", $this->getMimeType($file)); }
    public function isImage(?string $file) { return preg_match("/image\/.*/", $this->getMimeType($file)); }
    public function isSvg  (?string $file) { return $this->getMimeType($file) == "image/svg+xml"; }

    public function obfuscate(string|null $path, array $config = []): ?string
    {
        if($path === null ) return null;

        $path = realpath($path);
        $path = "/".str_strip($path, $this->assetExtension->getAssetUrl(""));

        $config["path"] = $path;
        $config["options"] = $config["options"] ?? [];
        $config["local_cache"] = $config["local_cache"] ?? null;

        while ( ($pathConfig = $this->obfuscator->decode(basename($path))) ) {

            $config["path"] = $path = $pathConfig["path"] ?? $path;
            $config["options"] = array_merge_recursive2($pathConfig["options"] ?? [], $config["options"]);
            $config["local_cache"] = $pathConfig["local_cache"] ?? $config["local_cache"];
        }

        return $this->obfuscator->encode($config);
    }

    public function generate(string $proxyRoute, array $proxyRouteParameters = [], ?string $path = null, array $config = []): ?string
    {
        $hashid = $this->obfuscate($path, $config);
        if(!$hashid) return null;

        return $this->router->generate($proxyRoute, array_merge($proxyRouteParameters, [
            "hashid" => $hashid
        ]));
    }

    public function resolve(string $hashid)
    {
        $path = null;
        $args = [];

        do {

            // Path fallback
            $args0 = null;
            $hashid0 = $hashid;
            while(strlen($hashid0) > 1) {

                $args0 = $this->obfuscator->decode(basename($hashid0));
                if($args0) break;

                $hashid0 = dirname($hashid0);
            }

            if(!is_array($args0)) $path = $hashid;
            else {

                $hashid = array_pop_key("path", $args0) ?? $hashid;
                $args = array_merge_recursive2($args, $args0);
            }

        } while(is_array($args0));

        $args["path"]    = $path;
        $args["options"] = $args["options"] ?? [];

        return $args;
    }

    public function serve(?string $file, int $status = 200, array $headers = []): ?Response { return $this->serveContents(file_get_contents($file), $status, $headers); }
    public function serveContents(?string $contents, int $status = 200, array $headers = []): ?Response
    {
        $httpCache  = array_pop_key("http_cache", $headers) ?? false;
        $attachment = array_pop_key("attachment", $headers) ?? false;

        $response = new Response($contents, $status, $headers);
        if($this->isEmpty($contents)) return $response;

        if($httpCache) {

            $response->setMaxAge(300);
            $response->setPublic();
            $response->setEtag(md5($response->getContent()));
            $response->headers->addCacheControlDirective('must-revalidate', true);
        }

        $mimeType = $this->getMimeType($contents);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Length', strlen($contents));

        if($attachment) {

            $extension  = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
            $extensions = $this->getExtensions($mimeType);

            if($attachment === true) {

                if($mimeType && str_starts_with($mimeType, "image/")) $attachment = "image";
                else $attachment = "unnamed";
            }

            if($extension && !in_array($extension, $extensions))
                $attachment = pathinfo_extension($attachment, $extensions[0]);

            $response->headers->set('Content-Disposition', 'attachment; filename="'.basename($attachment).'"');
        }

        return $response;
    }
}
