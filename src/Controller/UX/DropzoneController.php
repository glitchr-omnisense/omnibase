<?php

namespace Base\Controller\UX;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Uid\Uuid;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DropzoneController extends AbstractController
{
    const CACHE_DURATION = 24*3600;

    public const STATUS_OK      = "OK";
    public const STATUS_BAD     = "BAD";
    public const STATUS_NOTOKEN = "NO_TOKEN";

    public function __construct(TranslatorInterface $translator, CacheInterface $cache, string $cacheDir)
    {
        $this->cache    = $cache;
        $this->cacheDir = $cacheDir;
        $this->translator = $translator;

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
            return new Response($this->translator->trans("fileupload.error.invalid_token", [], "fields"), 500);

        // Move.. with flysystem
        if( !($file = $request->files->get("file")) )
            return new Response($this->translator->trans("fileupload.error.no_file", [], "fields"), 500);

        switch($file->getError()) {

            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return new Response($this->translator->trans("fileupload.error.too_big", [], "fields"), 500);
            case UPLOAD_ERR_PARTIAL:
                return new Response($this->translator->trans("fileupload.error.partial_upload", [], "fields"), 500);
            case UPLOAD_ERR_NO_FILE:
                return new Response($this->translator->trans("fileupload.error.no_file", [], "fields"), 500);
            case UPLOAD_ERR_NO_TMP_DIR:
                return new Response($this->translator->trans("fileupload.error.no_tmp_dir", [], "fields"), 500);
            case UPLOAD_ERR_CANT_WRITE:
                return new Response($this->translator->trans("fileupload.error.cant_write", [], "fields"), 500);
            case UPLOAD_ERR_EXTENSION:
                return new Response($this->translator->trans("fileupload.error.php_extension", [], "fields"), 500);
            default:
                return new Response("Unknown error during upload.", 500);
        }

        $cacheDir = $this->getCacheDir()."/dropzone";
        if(!$this->filesystem->exists($cacheDir))
            $this->filesystem->mkdir($cacheDir);

        $fileUuid = Uuid::v4();
        $filePath = $cacheDir."/".$fileUuid;
        $fileMetadata = [
            "status"    => self::STATUS_OK,
            "uuid"      => $fileUuid,
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
     * @Route("/ux/dropzone/{token}/{uuid}", name="ux_dropzone_preview")
     */
    public function Preview(Request $request, string $token, string $uuid): Response
    {
        if(!$token) throw new InvalidCsrfTokenException();

        if(!$this->isCsrfTokenValid("dropzone", $token))
            return new Response("Invalid token.", 500);

        if(!preg_match('/^[a-f0-9\-]{36}$/i', $uuid))
            return new Response("Invalid uuid.", 500);

        $cacheDir = $this->getCacheDir()."/dropzone";
        $path = $cacheDir."/".$uuid;
        if(file_exists($path)) {

            $content = file_get_contents2($path);
            $mimetype = mime_content_type2($path);

            $response = new Response();
            $response->setContent($content);

            $response->setMaxAge(300);
            $response->setPublic();
            $response->setEtag(md5($response->getContent()));
            $response->headers->addCacheControlDirective('must-revalidate', true);

            $response->headers->set('Content-Type', $mimetype);
            return $response;
        }

        return throw new NotFoundHttpException();
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
