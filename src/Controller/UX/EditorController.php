<?php

namespace Base\Controller\UX;

use App\Entity\User;
use Base\Service\PaginatorInterface;
use App\Repository\UserRepository;
use Base\Enum\UserRole;
use Base\Repository\Thread\TagRepository;
use Base\Repository\ThreadRepository;
use Base\Service\Collab\CollabRoomResolver;
use Base\Service\Collab\CollabTicketFactory;
use Base\Traits\BaseTrait;
use Base\Service\FlysystemInterface;
use Base\Service\MediaServiceInterface;
use Base\Service\Model\LinkableInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
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

    /**
     * @var CollabRoomResolver
     */
    protected CollabRoomResolver $roomResolver;

    /**
     * @var CollabTicketFactory
     */
    protected CollabTicketFactory $ticketFactory;

    public function __construct(ParameterBagInterface $parameterBag, SluggerInterface $slugger, MediaServiceInterface $mediaService, FlysystemInterface $flysystem, TranslatorInterface $translator, RequestStack $requestStack, PaginatorInterface $paginator, ObfuscatorInterface $obfuscator, UserRepository $userRepository, ThreadRepository $threadRepository, TagRepository $tagRepository, CollabRoomResolver $roomResolver, CollabTicketFactory $ticketFactory, ?Profiler $profiler = null)
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
        $this->roomResolver       = $roomResolver;
        $this->ticketFactory      = $ticketFactory;

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

        $filePath = "/" . path_subdivide(str_replace("-", "", $fileUuid), 5, 2) . ($fileExtension ? "." . $fileExtension : "");

        if (!file_exists($file->getPathname())) {
            return new Response("Uploaded file lost in the limbo.", 500);
        }
        
        $operator = $this->parameterBag->get("base.twig.editor.storage");
        if (!$operator) {
            return new Response("No storage provided.", 500);
        }
        if (!$this->flysystem->write($filePath, file_get_contents($file->getRealPath()), $operator)) {
            return new Response("Repository directory not writable.", 500);
        }

        // Local storage: public/ symlink URL (unchanged). Remote storage
        // (S3/MinIO): no local symlink exists, so route through the /images
        // resolver with the storage in-config — MediaService::filter() streams
        // the source from the remote and caches the derivative locally.
        $filePublic = $this->flysystem->getPublic($filePath, $operator);
        $fileUrl = $filePublic !== null
            ? str_lstrip($filePublic, $this->flysystem->getPublicDir())
            : $this->mediaService->image($filePath, ["storage" => $operator]);

        $fileMetadata = [
            "success" => self::STATUS_OK,
            "file" => ["url" => $fileUrl]
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

        $operator = $this->parameterBag->get("base.twig.editor.storage");
        if (!file_exists($file->getPathname())) {
            return new Response("Uploaded file lost in the limbo.", 500);
        }
        if (!$this->flysystem->write($filePath, file_get_contents($file->getRealPath()), $operator)) {
            return new Response("Repository directory not writable.", 500);
        }

        // Same local-vs-remote URL resolution as UploadByFile above.
        $filePublic = $this->flysystem->getPublic($filePath, $operator);
        $fileUrl = $filePublic !== null
            ? str_lstrip($filePublic, $this->flysystem->getPublicDir())
            : $this->mediaService->image($filePath, ["storage" => $operator]);

        $fileMetadata = [
            "success" => self::STATUS_OK,
            "file" => ["url" => $fileUrl]
        ];

        unlink($file->getRealPath());

        return JsonResponse::fromJsonString(json_encode($fileMetadata));
    }

    /**
     * This action creates a short-lived, signed ticket. A browser
     * presents this ticket to the collab relay. Refer to the collab-relay/
     * directory at the bundle root for the relay code. This ticket lets a
     * client join a room's WebSocket connection, for the `collab_live`
     * option. This action uses the same CSRF check and session check as
     * the other actions in this controller. Refer to the Autosave()
     * method below for the reason: this action adds no separate
     * per-room check.
     */
    #[Route("/ux/editorjs/collab-ticket", name:"collabTicket", methods:["POST"], priority: 1)]
    public function CollabTicket(Request $request): JsonResponse
    {
        $vars = json_decode($request->getContent(), true) ?? [];

        $token = $vars["token"] ?? null;
        if (!$token || !$this->isCsrfTokenValid("editorjs", $token)) {
            return new JsonResponse(["success" => self::STATUS_NOTOKEN, "error" => $this->translator->trans("editor.error.invalid_token", [], "fields")], 403);
        }

        $room = $vars["room"] ?? null;
        if (!$room) {
            return new JsonResponse(["success" => self::STATUS_BAD, "error" => "Missing room."], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(["success" => self::STATUS_BAD, "error" => "Not authenticated."], 403);
        }

        if (!$this->ticketFactory->isConfigured()) {
            return new JsonResponse(["success" => self::STATUS_BAD, "error" => "Collaboration relay is not configured."], 503);
        }

        $ticket = $this->ticketFactory->mint($room, [
            "id"     => $user->getId(),
            "name"   => (string) $user,
            "avatar" => $user->getAvatar(),
            "color"  => $user->getColor(),
        ]);

        return new JsonResponse([
            "success" => self::STATUS_OK,
            "ticket"  => $ticket,
            "wsUrl"   => $this->ticketFactory->getWsUrl(),
        ]);
    }

    /**
     * This action is an optional, debounced autosave method. This action
     * serves EditorType fields with the `collab_autosave` option. This
     * action also serves regular fields with the `collab` option. This
     * action saves one field's value outside a full form submission. This
     * action uses an optimistic-concurrency version hash. Because of this
     * hash, a stale client never overwrites a newer saved value silently.
     * This action, by itself, with no WebSocket relay, is the complete
     * "plain autosave" mode. In "live" collaboration mode, the relay calls
     * this same action. In "live" mode, the browser does not call this
     * action directly.
     *
     * This action has no separate per-entity permission check, by design.
     * A user can reach this action only through a form or a route that
     * already allowed that user in, through this app's normal firewall
     * and session system. This action also needs the same per-action CSRF
     * token as the other actions in this controller. This action creates
     * no new trust boundary.
     *
     * This action accepts two callers, through two authentication paths.
     * A browser sends the usual session-scoped "token" value, for CSRF
     * protection. A browser uses this path in plain `collab_autosave`
     * mode. A browser also uses this path as the fallback path in
     * `collab_live` mode. The collab relay itself uses a different path.
     * The relay saves a `collab_live` room's content on its own debounced
     * schedule. The relay has no Symfony session for that call. Because
     * of this, the relay sends a "serviceToken" value and a "room" value
     * instead. This action checks these two values through
     * CollabTicketFactory::verifyServiceToken(). This method uses the
     * same shared secret as ticket creation.
     */
    #[Route("/ux/editorjs/autosave", name:"autosave", methods:["POST"], priority: 1)]
    public function Autosave(Request $request): JsonResponse
    {
        $vars = json_decode($request->getContent(), true) ?? [];

        $token = $vars["token"] ?? null;
        $serviceToken = $vars["serviceToken"] ?? null;
        $room = $vars["room"] ?? null;

        $authorized = ($token && $this->isCsrfTokenValid("editorjs", $token))
            || ($serviceToken && $room && $this->ticketFactory->verifyServiceToken($serviceToken, $room));

        if (!$authorized) {
            return new JsonResponse(["success" => self::STATUS_NOTOKEN, "error" => $this->translator->trans("editor.error.invalid_token", [], "fields")], 403);
        }

        $fqcn        = $vars["fqcn"]        ?? null;
        $id          = $vars["id"]          ?? null;
        $field       = $vars["field"]       ?? null;
        $locale      = $vars["locale"]      ?? null;
        $value       = $vars["value"]       ?? null;
        $baseVersion = $vars["baseVersion"] ?? null;

        if (!$fqcn || !$id || !$field || !class_exists($fqcn)) {
            return new JsonResponse(["success" => self::STATUS_BAD, "error" => "Invalid autosave target."], 400);
        }

        $em = $this->getEntityManager();
        if (!$em || $em->getMetadataFactory()->isTransient($fqcn)) {
            return new JsonResponse(["success" => self::STATUS_BAD, "error" => "Invalid autosave target."], 400);
        }

        $entity = $em->find($fqcn, $id);
        if (!$entity) {
            return new JsonResponse(["success" => self::STATUS_BAD, "error" => "Entity not found."], 404);
        }

        // A translatable entity, for example Thread or Article content,
        // needs the locale-specific translation object here. This action
        // must not save the value to the parent record.
        $target = ($locale && method_exists($entity, "translate")) ? $entity->translate($locale) : $entity;
        if (!$target) {
            return new JsonResponse(["success" => self::STATUS_BAD, "error" => "Unknown locale."], 400);
        }

        $accessor = PropertyAccess::createPropertyAccessor();
        if (!$accessor->isReadable($target, $field) || !$accessor->isWritable($target, $field)) {
            return new JsonResponse(["success" => self::STATUS_BAD, "error" => "Unknown field."], 400);
        }

        $currentValue = $accessor->getValue($target, $field);
        $currentVersion = $this->roomResolver->hash($currentValue);

        // Another save can occur after this client last read the field.
        // In this case, this action rejects the new value. This action
        // does not overwrite the newer value. This action also returns
        // the current value. The client uses this value for a
        // highlighted restore-or-suppress choice. This method prevents
        // silent data loss.
        if ($baseVersion && $currentVersion !== $baseVersion) {
            return new JsonResponse([
                "success"  => self::STATUS_BAD,
                "conflict" => true,
                "value"    => $currentValue,
                "version"  => $currentVersion,
            ], 409);
        }

        $accessor->setValue($target, $field, $value);
        $em->flush();

        return new JsonResponse([
            "success" => self::STATUS_OK,
            "version" => $this->roomResolver->hash($value),
        ]);
    }
}
