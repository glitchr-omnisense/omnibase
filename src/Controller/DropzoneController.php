<?php

namespace Base\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use  Base\Service\BaseService;
use Exception;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

class DropzoneController extends AbstractController
{
    const CACHE_DURATION = 24*3600;

    public const STATUS_OK      = "OK";
    public const STATUS_BAD     = "BAD";
    public const STATUS_NOTOKEN = "NO_TOKEN";

    public function __construct(CacheInterface $cache, string $cacheDir)
    {
        $this->cache    = $cache;
        $this->cacheDir = $cacheDir;

        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();
    }

    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Controller example
     *
     * @Route("/ux/dropzone/{token}", name="ux_dropzone")
     */
    public function Main(Request $request, $token = null): Response
    {
        if(!$token || !$this->isCsrfTokenValid("dropzone", $token)) 
            return new Response("Invalid token.", 500);

        // Move.. with flysystem
        if( !($file = $request->files->get("file")) )
            return new Response("Unexpected number of files provided", 500);
 
        if($file->getError()) 
           return new Response("Error during upload.", 500);

        $cacheDir = $this->getCacheDir()."/dropzone";
        if(!$this->filesystem->exists($cacheDir))
            $this->filesystem->mkdir($cacheDir);

        $fileUuid = Uuid::v4();
        $filePath = $cacheDir."/".$fileUuid;
        $fileMetadata = [
            "status"    => self::STATUS_OK,
            "uuid"      => $fileUuid,
            "path"      => $filePath,
            "mime_type" => $file->getMimeType(),
            "size"      => $file->getSize(),
            "error"     => $file->getError(),
        ];

        if(!move_uploaded_file($file->getRealPath(), $filePath))
            return new Response("Failed to write into buffer", 500);

        $fnExpiry = function($expiry, $uuid) use ($cacheDir) {

            if($expiry > time()) return true;

            if(!preg_match('/^[a-f0-9\-]{36}$/i', $uuid))
                return new Response("Invalid uuid.", 500);

            $fname = $cacheDir."/".$uuid;
            if(file_exists($fname)) unlink($fname);

            return false;
        };

        $cacheDropzone = $this->cache->getItem("cache:dropzone");
        if($cacheDropzone->isHit()) { // If cache found and didn't expired

            $dropzone = $cacheDropzone->get();
            $dropzone = array_filter($dropzone, $fnExpiry, ARRAY_FILTER_USE_BOTH);

        } else { // If cache not found or expired

            $dropzone = $cacheDropzone->get() ?? [];
            foreach($dropzone as $uuid => $_)
                if(file_exists($cacheDir."/".$uuid)) unlink($cacheDir."/".$uuid);

        }

        $dropzone[(string) $fileUuid] = time() + self::CACHE_DURATION;
        $cacheDropzone->set($dropzone);
        $cacheDropzone->expiresAfter(self::CACHE_DURATION);
        $this->cache->save($cacheDropzone);

        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }

    /**
     * Controller example
     *
     * @Route("/ux/dropzone/{token}/{uuid}/delete", name="ux_dropzone_delete")
     */
    public function Delete(Request $request, string $token, string $uuid): Response
    {
        if(!$token) throw new InvalidCsrfTokenException();

        if(!$this->isCsrfTokenValid("dropzone", $token)) 
            return new Response("Invalid token.", 500);

        if(!preg_match('/^[a-f0-9\-]{36}$/i', $uuid))
            return new Response("Invalid uuid.", 500);

        $cacheDir = $this->getCacheDir()."/dropzone";
        $path = $cacheDir."/".$uuid;
        if(file_exists($path)) !unlink($path);

        return JsonResponse::fromJsonString(json_encode(["status"    => self::STATUS_OK, 'uuid' => $uuid]));
    }
}