<?php

namespace Base\Controller\UX;

use Base\Service\FlysystemInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route(priority = -1, name="ux_editorjs_")
 * */
class EditorController extends AbstractController
{
    public const STATUS_OK      = 1;
    public const STATUS_BAD     = 0;
    public const STATUS_NOTOKEN = -1;

    /**
     * @var ObfuscatorInterface
     */
    protected $obfuscator;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @var FlysystemInterface
     */
    protected $flysystem;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    /**
     * @var MimeTypes
     */
    protected $mimeTypes;

    public function __construct(ParameterBagInterface $parameterBag, FlysystemInterface $flysystem, TranslatorInterface $translator, ObfuscatorInterface $obfuscator)
    {
        $this->translator = $translator;
        $this->obfuscator = $obfuscator;

        $this->flysystem  = $flysystem;
        $this->parameterBag = $parameterBag;

        $this->mimeTypes = new MimeTypes();
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
        if (!($file = $request->files->get("image"))) {
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

        $fileUuid = Uuid::v4();
        $mimeType = mime_content_type2($file->getPathname());

        $fileExtension = $mimeType ? $this->mimeTypes->getExtensions($mimeType)[0] ?? null : null;
        $filePath = "/".$fileUuid.($fileExtension ? ".".$fileExtension : "");

        $operator = $this->parameterBag->get("base.twig.editor.operator");
        if (!file_exists($file->getPathname())) {
            return new Response("Uploaded file lost in the limbo.", 500);
        }

        if (!$this->flysystem->write($filePath, file_get_contents($file->getRealPath()), $operator)) {
            return new Response("Repository directory not writable.", 500);
        }

        $fileMetadata = [
            "success" => self::STATUS_OK,
            "file" => ["url" => str_lstrip($this->flysystem->getPublic($filePath, $operator), $this->flysystem->getPublicDir())]
        ];

        unlink($file->getRealPath());
        
        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }

    /**
     * @Route("/ux/editorjs/{data}/fetch", name="endpointByUrl")
     */
    public function EndpointByUrl(Request $request, $data = null): Response
    {
        $config = $this->obfuscator->decode($data);
        $token = $config["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new Response($this->translator->trans("fileupload.error.invalid_token", [], "fields"), 500);
        }

        $content = $request->getContent();
        $path = $content ? json_decode($content)->url : null;
        if($path) $path = fetch_url($path);

        // Move.. with flysystem
        if (!$path || !($file = new UploadedFile($path, $path))) {
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

        $fileUuid = Uuid::v4();
        $mimeType = mime_content_type2($file->getPathname());

        $fileExtension = $mimeType ? $this->mimeTypes->getExtensions($mimeType)[0] ?? null : null;
        $filePath = "/".$fileUuid.($fileExtension ? ".".$fileExtension : "");

        $operator = $this->parameterBag->get("base.twig.editor.operator");
        if (!file_exists($file->getPathname())) {
            return new Response("Uploaded file lost in the limbo.", 500);
        }
        if (!$this->flysystem->write($filePath, file_get_contents($file->getRealPath()), $operator)) {
            return new Response("Repository directory not writable.", 500);
        }

        $fileMetadata = [
            "success" => self::STATUS_OK,
            "file" => ["url" => str_lstrip($this->flysystem->getPublic($filePath, $operator), $this->flysystem->getPublicDir())]
        ];

        unlink($file->getRealPath());
        
        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }
}
