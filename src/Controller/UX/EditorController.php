<?php

namespace Base\Controller\UX;

use App\Entity\User;
use Base\Service\PaginatorInterface;
use App\Repository\UserRepository;
use Base\Enum\UserRole;
use Base\Repository\Thread\TagRepository;
use Base\Repository\ThreadRepository;
use Base\Traits\BaseTrait;
use Base\Service\FlysystemInterface;
use Base\Service\MediaServiceInterface;
use Base\Service\Model\LinkableInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;

#[ Route(priority: -1, name: "ux_editorjs_") ]
class EditorController extends AbstractController
{
    use BaseTrait;

    public const STATUS_OK = 1;
    public const STATUS_BAD = 0;
    public const STATUS_NOTOKEN = -1;

    /**
     * @var ObfuscatorInterface
     */
    protected ObfuscatorInterface $obfuscator;

    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var FlysystemInterface
     */
    protected FlysystemInterface $flysystem;

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    /**
     * @var UserRepository
     */
    protected UserRepository $userRepository;
    
    /**
     * @var TagRepository
     */
    protected TagRepository $tagRepository;
    
    /**
     * @var ThreadRepository
     */
    protected ThreadRepository $threadRepository;
    
    /**
     * @var MediaServiceInterface
     */
    protected MediaServiceInterface $mediaService;
    
    /**
     * @var MimeTypes
     */
    protected MimeTypes $mimeTypes;

    /**
     * @var Profiler|null
     */
    protected ?Profiler $profiler;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * @var PaginatorInterface
     */
    protected PaginatorInterface $paginator;

    /**
     * @var SluggerInterface
     */
    protected SluggerInterface $slugger;

    public function __construct(ParameterBagInterface $parameterBag, SluggerInterface $slugger, MediaServiceInterface $mediaService, FlysystemInterface $flysystem, TranslatorInterface $translator, RequestStack $requestStack, PaginatorInterface $paginator, ObfuscatorInterface $obfuscator, UserRepository $userRepository, ThreadRepository $threadRepository, TagRepository $tagRepository, ?Profiler $profiler = null)
    {
        $this->translator = $translator;
        $this->obfuscator = $obfuscator;

        $this->flysystem = $flysystem;
        $this->mediaService = $mediaService;
        $this->parameterBag = $parameterBag;

        $this->slugger = $slugger;

        $this->threadRepository   = $threadRepository;
        $this->tagRepository = $tagRepository;
        $this->userRepository     = $userRepository;

        $this->mimeTypes = new MimeTypes();
        $this->profiler = $profiler;

        $this->requestStack = $requestStack;
        $this->paginator = $paginator;
    }

    #[Route("/ux/editorjs/user/{data}/{page}", name:"endpointByUser")]
    public function EndpointByUser(Request $request, $data = null, array $fields = [], $page = 1): Response
    {
        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }

        $config = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);
        $token = $config["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new Response($this->translator->trans("editor.error.invalid_token", [], "fields"), 500);
        }

        $vars = json_decode($request->getContent(), true);
        $page = intval($vars["page"] ?? $page);
        $page = $page ? $page : 1;
        
        $query = $vars["query"] ?? NULL;

        $expectedMethod = $this->getService()->isDebug() ? ["GET", "POST"] : ["POST"];
        if (!in_array($request->getMethod(), $expectedMethod) || !$query) {
            return new Response($this->translator->trans("editor.error.invalid_query", [], "fields"), 500);
        }

        $items = [];

        $users = $this->paginator->paginate($this->userRepository->cacheByInsensitiveIdentifier($query, $fields), $page, 5);
        foreach($users as $user)
        {
            $items[] = [

                "id" => $user->getId(),
                "label" => $user->__toString(), 
                "avatar" => $this->mediaService->image($user->getAvatarFile()),
                "link" => [
                    "name" => $this->isGranted(UserRole::ADMIN) ? $user->__autocomplete() : ($this->translator->transEntity($user)." #".$user->getId()),
                    "url" => $user instanceof LinkableInterface ? $user->__toLink() : null
                ],

                "data" => [$this->obfuscator->encode([
                    "id" => $user->getId(), 
                    "className" => get_class($user)
                ])]
            ];
        }

        $fileMetadata = [
            "success" => self::STATUS_OK,
            "results" => $items,
            "pagination" => [
                "page" => $page,
                "more" => $page > 0 && $page < $users->getTotalPages()
            ]
        ];

        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }

    #[Route("/ux/editorjs/keyword/{data}/{page}", name:"endpointByKeyword")]
    public function EndpointByKeyword(Request $request, $data = null, array $fields = ["slug"], $page = 1): Response
    {
        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }

        $config = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);
        $token = $config["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new Response($this->translator->trans("editor.error.invalid_token", [], "fields"), 500);
        }

        $vars = json_decode($request->getContent(), true);
        $page = intval($vars["page"] ?? $page);
        $page = $page ? $page : 1;
        
        $query = $this->slugger->slug($vars["query"] ?? NULL);

        $expectedMethod = $this->getService()->isDebug() ? ["GET", "POST"] : ["POST"];
        if (!in_array($request->getMethod(), $expectedMethod) || !$query) {
            return new Response($this->translator->trans("editor.error.invalid_query", [], "fields"), 500);
        }

        $items = [];

        $tags = $this->paginator->paginate($this->tagRepository->cacheByInsensitiveIdentifier($query, $fields), $page, 5);
        foreach($tags as $tag)
        {
            $items[] = [

                "id" => $tag->getId(),
                "label" => $tag->__toString(), 
                "link" => [
                    "name" => $this->translator->transEntity($tag)." #".$tag->getId(),
                    "url" => $tag instanceof LinkableInterface ? $tag->__toLink() : null
                ],

                "data" => [$this->obfuscator->encode([
                    "id" => $tag->getId(), 
                    "className" => get_class($tag)
                ])]
            ];
        }

        $fileMetadata = [
            "success" => self::STATUS_OK,
            "results" => $items,
            "pagination" => [
                "page" => $page,
                "more" => $page > 0 && $page < $tags->getTotalPages()
            ]
        ];

        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }

    #[Route("/ux/editorjs/thread/{data}/{page}", name:"endpointByThread")]
    public function EndpointByThread(Request $request, $data = null, array $fields = [], $page = 1): Response
    {
        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }

        $config = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);
        $token = $config["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new Response($this->translator->trans("editor.error.invalid_token", [], "fields"), 500);
        }

        $vars = json_decode($request->getContent(), true);
        $page = intval($vars["page"] ?? $page);
        $page = $page ? $page : 1;
        
        $query = $this->slugger->slug($vars["query"] ?? NULL);

        $expectedMethod = $this->getService()->isDebug() ? ["GET", "POST"] : ["POST"];
        if (!in_array($request->getMethod(), $expectedMethod) || !$query) {
            return new Response($this->translator->trans("editor.error.invalid_query", [], "fields"), 500);
        }

        $items = [];

        $threads = $this->paginator->paginate($this->threadRepository->cacheByInsensitiveIdentifier($query, $fields), $page, 5);
        foreach($threads as $thread)
        {
            $items[] = [

                "id" => $thread->getId(),
                "label" => $thread->__toString(), 
                "link" => [
                    "name" => $this->isGranted(UserRole::ADMIN) ? str_shorten($thread->getExcerpt(), 40) : ($this->translator->transEntity($thread)." #".$thread->getId()),
                    "url" => $thread instanceof LinkableInterface ? $thread->__toLink() : null
                ],

                "data" => [$this->obfuscator->encode([
                    "id" => $thread->getId(), 
                    "className" => get_class($thread)
                ])]
            ];
        }

        $fileMetadata = [
            "success" => self::STATUS_OK,
            "results" => $items,
            "pagination" => [
                "page" => $page,
                "more" => $page > 0 && $page < $threads->getTotalPages()
            ]
        ];

        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }

    #[Route("/ux/editorjs/{data}", name:"uploadByFile")]
    public function UploadByFile(Request $request, $data = null): Response
    {
        $config = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);
        $token = $config["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new Response($this->translator->trans("fileupload.error.invalid_token", [], "fields"), 500);
        }

        // Move.. with flysystem
        if (!($file = $request->files->get("image"))) {
            return new Response($this->translator->trans("fileupload.error.no_file", [], "fields"), 500);
        }

        switch ($file->getError()) {
            case UPLOAD_ERR_OK:
                break;
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

        if (array_key_exists("maxFilesize", $config) && $file->getSize() > 1e6 * $config["maxFilesize"]) {
            return new Response($this->translator->trans("fileupload.error.too_big", [], "fields"), 500);
        }

        $fileUuid = Uuid::v4();
        $mimeType = mime_content_type2($file->getPathname());

        $fileExtension = $mimeType ? $this->mimeTypes->getExtensions($mimeType)[0] ?? null : null;
        $filePath = "/" . $fileUuid . ($fileExtension ? "." . $fileExtension : "");

        if (!file_exists($file->getPathname())) {
            return new Response("Uploaded file lost in the limbo.", 500);
        }
        
        $operator = $this->parameterBag->get("base.twig.editor.operator");
        if (!$operator) {
            return new Response("No storage provided.", 500);
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

    #[Route("/ux/editorjs/{data}/fetch", name:"uploadByUrl")]
    public function UploadByUrl(Request $request, $data = null): Response
    {
        $config = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);
        $token = $config["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new Response($this->translator->trans("fileupload.error.invalid_token", [], "fields"), 500);
        }

        $content = $request->getContent();
        $path = $content ? json_decode($content)->url : null;
        if ($path) {
            $path = fetch_url($path);
        }

        // Move.. with flysystem
        if (!$path || !($file = new UploadedFile($path, $path))) {
            return new Response($this->translator->trans("fileupload.error.no_file", [], "fields"), 500);
        }

        switch ($file->getError()) {
            case UPLOAD_ERR_OK:
                break;
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

        if (array_key_exists("maxFilesize", $config) && $file->getSize() > 1e6 * $config["maxFilesize"]) {
            return new Response($this->translator->trans("fileupload.error.too_big", [], "fields"), 500);
        }

        $fileUuid = Uuid::v4();
        $mimeType = mime_content_type2($file->getPathname());

        $fileExtension = $mimeType ? $this->mimeTypes->getExtensions($mimeType)[0] ?? null : null;
        $filePath = "/" . $fileUuid . ($fileExtension ? "." . $fileExtension : "");

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
