<?php

namespace Base\Controller\UX;

use Base\Service\FileService;
use Base\Service\ObfuscatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Uid\Uuid;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route(priority = -1, name="ux_editorjs_")
 * */
class EditorController extends AbstractController
{
    public const CACHE_DURATION = 24*3600;

    public const STATUS_OK      = "OK";
    public const STATUS_BAD     = "BAD";
    public const STATUS_NOTOKEN = "NO_TOKEN";

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var ObfuscatorInterface
     */
    protected $obfuscator;

    /** * @var string */
    protected $cacheDir;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    public function __construct(TranslatorInterface $translator, CacheInterface $cache, ObfuscatorInterface $obfuscator, string $cacheDir)
    {
        $this->cache      = $cache;
        $this->cacheDir   = $cacheDir;
        $this->translator = $translator;
        $this->obfuscator = $obfuscator;

        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();
    }

    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * @Route("/ux/editorjs/{data}", name="endpointByFile")
     */
    public function EndpointByFile(Request $request, $data = null): Response
    {
        $config = $this->obfuscator->decode($data);
        $token = $config["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new Response($this->translator->trans("fileupload.error.invalid_token", [], "fields"), 500);
        }



        // Move.. with flysystem
        if (!($file = $request->files->get("file"))) {
            return new Response($this->translator->trans("fileupload.error.no_file", [], "fields"), 500);
        }

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

        if (array_key_exists("maxFilesize", $config) && $file->getSize() > 1e6*$config["maxFilesize"]) {
            return new Response($this->translator->trans("fileupload.error.too_big", [], "fields"), 500);
        }

        $cacheDir = $this->getCacheDir()."/editorjs";
        if (!$this->filesystem->exists($cacheDir)) {
            $this->filesystem->mkdir($cacheDir);
        }

        $fileUuid = Uuid::v4();
        $filePath = $cacheDir."/".$fileUuid;
        $fileMetadata = [
            "status"    => self::STATUS_OK,
            "uuid"      => $fileUuid,
            "mime_type" => $file->getMimeType(),
            "size"      => $file->getSize(),
            "error"     => $file->getError(),
        ];

        // dirname($newFileName))
        if (!is_writable(dirname($file->getPathname()))) {
            return new Response("Repository directory not writable.", 500);
        }
        if (!file_exists($file->getPathname())) {
            return new Response("Uploaded file lost in the limbo.", 500);
        }

        if (!move_uploaded_file($file->getRealPath(), $filePath)) {
            return new Response($this->translator->trans("fileupload.error.cant_write", [], "fields"), 500);
        }

        // $fnExpiry = function($expiry, $uuid) use ($cacheDir) {

        //     if($expiry > time()) return true;

        //     if(!preg_match('/^[a-f0-9\-]{36}$/i', $uuid))
        //         return new Response("Invalid uuid.", 500);

        //     $fname = $cacheDir."/".$uuid;
        //     if(file_exists($fname)) unlink($fname);

        //     return false;
        // };

        // @TODO Implement a cleaning command..
        // $cacheDropzone = $this->cache->getItem("cache:editorjs");
        // if($cacheDropzone->isHit()) { // If cache found and didn't expired

        //     $editorjs = $cacheDropzone->get();
        //     $editorjs = array_filter($editorjs, $fnExpiry, ARRAY_FILTER_USE_BOTH);

        // } else { // If cache not found or expired

        //     $editorjs = $cacheDropzone->get() ?? [];
        //     foreach($editorjs as $uuid => $_)
        //         if(file_exists($cacheDir."/".$uuid)) unlink($cacheDir."/".$uuid);
        // }

        // $editorjs[(string) $fileUuid] = time() + self::CACHE_DURATION;
        // $cacheDropzone->set($editorjs);
        // $cacheDropzone->expiresAfter(self::CACHE_DURATION);
        // $this->cache->save($cacheDropzone);

        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }

        /**
     * @Route("/ux/editorjs/{data}/fetch", name="endpointByUrl")
     */
    public function EndpointByUrl(Request $request, $data = null): Response
    {
        dump(1);
        $config = $this->obfuscator->decode($data);
        $token = $config["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new Response($this->translator->trans("fileupload.error.invalid_token", [], "fields"), 500);
        }
        dump(2);

        dump($request);
        // Move.. with flysystem
        if (!($file = $request->files->get("file"))) {
            return new Response($this->translator->trans("fileupload.error.no_file", [], "fields"), 500);
        }
        dump(3);

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

        if (array_key_exists("maxFilesize", $config) && $file->getSize() > 1e6*$config["maxFilesize"]) {
            return new Response($this->translator->trans("fileupload.error.too_big", [], "fields"), 500);
        }

        $cacheDir = $this->getCacheDir()."/editorjs";
        if (!$this->filesystem->exists($cacheDir)) {
            $this->filesystem->mkdir($cacheDir);
        }

        $fileUuid = Uuid::v4();
        $filePath = $cacheDir."/".$fileUuid;
        $fileMetadata = [
            "status"    => self::STATUS_OK,
            "uuid"      => $fileUuid,
            "mime_type" => $file->getMimeType(),
            "size"      => $file->getSize(),
            "error"     => $file->getError(),
        ];

        // dirname($newFileName))
        if (!is_writable(dirname($file->getPathname()))) {
            return new Response("Repository directory not writable.", 500);
        }
        if (!file_exists($file->getPathname())) {
            return new Response("Uploaded file lost in the limbo.", 500);
        }

        if (!move_uploaded_file($file->getRealPath(), $filePath)) {
            return new Response($this->translator->trans("fileupload.error.cant_write", [], "fields"), 500);
        }

        // $fnExpiry = function($expiry, $uuid) use ($cacheDir) {

        //     if($expiry > time()) return true;

        //     if(!preg_match('/^[a-f0-9\-]{36}$/i', $uuid))
        //         return new Response("Invalid uuid.", 500);

        //     $fname = $cacheDir."/".$uuid;
        //     if(file_exists($fname)) unlink($fname);

        //     return false;
        // };

        // @TODO Implement a cleaning command..
        // $cacheDropzone = $this->cache->getItem("cache:editorjs");
        // if($cacheDropzone->isHit()) { // If cache found and didn't expired

        //     $editorjs = $cacheDropzone->get();
        //     $editorjs = array_filter($editorjs, $fnExpiry, ARRAY_FILTER_USE_BOTH);

        // } else { // If cache not found or expired

        //     $editorjs = $cacheDropzone->get() ?? [];
        //     foreach($editorjs as $uuid => $_)
        //         if(file_exists($cacheDir."/".$uuid)) unlink($cacheDir."/".$uuid);
        // }

        // $editorjs[(string) $fileUuid] = time() + self::CACHE_DURATION;
        // $cacheDropzone->set($editorjs);
        // $cacheDropzone->expiresAfter(self::CACHE_DURATION);
        // $this->cache->save($cacheDropzone);

        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }
}
