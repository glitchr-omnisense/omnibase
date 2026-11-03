<?php

namespace Base\Field\Editor;

use Base\Database\UnitOfWorks;
use Base\Entity\User;
use Base\Enum\UserRole;
use Base\Field\Search\Answer;
use Base\Service\Collab\CollabRoomResolver;
use Base\Service\Collab\CollabTicketFactory;
use Base\Service\MediaServiceInterface;
use Base\Service\Model\LinkableInterface;
use Base\Service\Model\Wysiwyg\MediaEnhancerInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\PaginatorInterface;
use Base\Service\Storages;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * An Editor.js field's routes, answered for any host (plan "omnibase agnostic", step F5d): what omnibase's
 * EditorController answered under Symfony, moved here as it was, so that every host serves an editor at the
 * same addresses (ROUTES) with the same answers.
 *
 * - data(): what an editor hands its routes (their `data`), obfuscated (Obfuscator, long form): a token of
 *   intent "editorjs" (the Tokens contract) that the mentions and the uploads check first. addresses(): the
 *   five routes' addresses with it, as EditorType gives them to the widget.
 * - users(), keywords(), threads(): the mentions' search (@someone, #keyword, a thread), five by page.
 * - uploadByFile(), uploadByUrl(): a picture of the text stored in the editor's storage
 *   (base.twig.editor.storage), its public address answered.
 * - ticket(): a collaboration relay's ticket for one room; autosave(): one field of one record written
 *   outside its form, refused on a conflict (an optimistic version hash). Both check the room's own token.
 *
 * The host's controller - Symfony's EditorController, omnibridge/laravel's EditorController - hands the
 * request's parts and sends the Answer as JSON (a refusal as text); the profiler stays its own.
 */
class EditorJs
{
    /**
     * The routes, in the order a host declares them (the collaboration's first: /ux/editorjs/{data} would
     * take their paths): name => [path, action, methods]. {page} is optional.
     */
    public const ROUTES = [
        'ux_editorjs_collabTicket' => ['/ux/editorjs/collab-ticket', 'ticket', ['POST']],
        'ux_editorjs_autosave' => ['/ux/editorjs/autosave', 'autosave', ['POST']],
        'ux_editorjs_endpointByUser' => ['/ux/editorjs/user/{data}/{page}', 'users', ['GET', 'POST']],
        'ux_editorjs_endpointByKeyword' => ['/ux/editorjs/keyword/{data}/{page}', 'keywords', ['GET', 'POST']],
        'ux_editorjs_endpointByThread' => ['/ux/editorjs/thread/{data}/{page}', 'threads', ['GET', 'POST']],
        'ux_editorjs_uploadByFile' => ['/ux/editorjs/{data}', 'uploadByFile', ['GET', 'POST']],
        'ux_editorjs_uploadByUrl' => ['/ux/editorjs/{data}/fetch', 'uploadByUrl', ['GET', 'POST']],
    ];

    /** The editor's addresses as the widget reads them: its var => route. */
    public const ADDRESSES = [
        'uploadByFile' => 'ux_editorjs_uploadByFile',
        'uploadByUrl' => 'ux_editorjs_uploadByUrl',
        'endpointByUser' => 'ux_editorjs_endpointByUser',
        'endpointByThread' => 'ux_editorjs_endpointByThread',
        'endpointByKeyword' => 'ux_editorjs_endpointByKeyword',
    ];

    /** The intent of the token the mentions and the uploads check. */
    public const TOKEN = 'editorjs';

    public const STATUS_OK = 1;
    public const STATUS_BAD = 0;
    public const STATUS_NOTOKEN = -1;

    /** An upload's error (UPLOAD_ERR_*) => the key of its words in the fields' catalogue. */
    private const ERRORS = [
        \UPLOAD_ERR_INI_SIZE => 'fileupload.error.too_big',
        \UPLOAD_ERR_FORM_SIZE => 'fileupload.error.too_big',
        \UPLOAD_ERR_PARTIAL => 'fileupload.error.partial_upload',
        \UPLOAD_ERR_NO_FILE => 'fileupload.error.no_file',
        \UPLOAD_ERR_NO_TMP_DIR => 'fileupload.error.no_tmp_dir',
        \UPLOAD_ERR_CANT_WRITE => 'fileupload.error.cant_write',
        \UPLOAD_ERR_EXTENSION => 'fileupload.error.php_extension',
    ];

    private readonly CollabRoomResolver $rooms;
    private readonly SluggerInterface $slugger;

    public function __construct(
        private readonly ObfuscatorInterface $obfuscator,
        private readonly Tokens $tokens,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?PaginatorInterface $paginator = null,
        private readonly ?MediaServiceInterface $mediaService = null,
        private readonly ?Storages $storages = null,
        private readonly ?Access $access = null,
        private readonly ?Urls $urls = null,
        ?CollabRoomResolver $rooms = null,
        private readonly ?CollabTicketFactory $tickets = null,
        ?SluggerInterface $slugger = null,
        private readonly ?MediaEnhancerInterface $enhancer = null,
        private readonly ?string $storage = null,
        private readonly ?bool $debug = null,
    ) {
        $this->rooms = $rooms ?? new CollabRoomResolver($unitOfWork);
        $this->slugger = $slugger ?? new AsciiSlugger();
    }

    /** What an editor hands its routes (their `data`): the visitor's token. */
    public function data(): string
    {
        return $this->obfuscator->encode(['token' => $this->tokens->csrf(self::TOKEN)], ObfuscatorInterface::NO_SHORT);
    }

    /**
     * The five addresses the widget reads (ADDRESSES), with data(); an address is null where the host has no
     * such route.
     *
     * @return array<string, string|null>
     */
    public function addresses(): array
    {
        $data = $this->data();
        $addresses = [];
        foreach (self::ADDRESSES as $var => $route) {
            try {
                $addresses[$var] = $this->urls?->path($route, ['data' => $data]);
            } catch (\Throwable) {
                $addresses[$var] = null;
            }
        }

        return $addresses;
    }

    /** The room's routes (autosave, ticket), the collaboration's rooms and tickets. */
    public function rooms(): CollabRoomResolver
    {
        return $this->rooms;
    }

    public function tickets(): ?CollabTicketFactory
    {
        return $this->tickets;
    }

    /** The token of one room (one record's one field), which its autosave and its ticket check. */
    public function roomToken(string $room): string
    {
        return $this->tokens->csrf($this->rooms->tokenId($room));
    }

    /** The account signed in, as the collaboration shows it; null for no one. @return array<string, mixed>|null */
    public function author(): ?array
    {
        $user = $this->access?->getUser();

        return $user instanceof User ? self::who($user) : null;
    }

    /**
     * A picture of a document by its stored origin: the image route's address in the editor's storage
     * (omnibase's MediaEnhancer), the origin itself where the host has no enhancer.
     */
    public function picture(string $origin): mixed
    {
        return null !== $this->enhancer ? $this->enhancer->enhance($origin, ['storage' => $this->storage], [], []) : $origin;
    }

    /** A route's address, null where the host has none. */
    public function address(string $route): ?string
    {
        try {
            return $this->urls?->path($route);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The accounts whose identifier holds the query (@someone), among $fields where given.
     *
     * @param array<string, mixed> $body   the request's JSON body: query, page
     * @param list<string>         $fields the columns compared (a site's username, first and last names), the identifier's by default
     */
    public function users(?string $data, string $method, array $body = [], int|string|null $page = 1, array $fields = []): Answer
    {
        $class = class_exists('App\Entity\User') ? 'App\Entity\User' : User::class;

        return $this->mentions($data, $method, $body, $page, $class, false, fn (object $user): array => [
            'avatar' => $this->mediaService?->image($user->getAvatarFile()),
            'link' => [
                'name' => $this->isAdmin() ? $user->__autocomplete() : $this->noun($user),
                'url' => $user instanceof LinkableInterface ? $user->__toLink() : null,
            ],
        ], $fields);
    }

    /** The keywords (thread tags) whose slug holds the query's (#keyword). @param array<string, mixed> $body */
    public function keywords(?string $data, string $method, array $body = [], int|string|null $page = 1, array $fields = ['slug']): Answer
    {
        return $this->mentions($data, $method, $body, $page, \Base\Entity\Thread\Tag::class, true, fn (object $tag): array => [
            'link' => ['name' => $this->noun($tag), 'url' => $tag instanceof LinkableInterface ? $tag->__toLink() : null],
        ], $fields);
    }

    /** The threads whose identifier holds the query's slug. @param array<string, mixed> $body */
    public function threads(?string $data, string $method, array $body = [], int|string|null $page = 1, array $fields = []): Answer
    {
        return $this->mentions($data, $method, $body, $page, \Base\Entity\Thread::class, true, fn (object $thread): array => [
            'link' => [
                'name' => $this->isAdmin() ? str_shorten($thread->getExcerpt(), 40) : $this->noun($thread),
                'url' => $thread instanceof LinkableInterface ? $thread->__toLink() : null,
            ],
        ], $fields);
    }

    /**
     * A picture sent under `image`, stored in the editor's storage under a new uuid's subfolders. $file is
     * the host's uploaded file (Symfony's UploadedFile, Laravel's), $error its UPLOAD_ERR_*.
     */
    public function uploadByFile(?string $data, ?\SplFileInfo $file, int $error = \UPLOAD_ERR_OK): Answer
    {
        $config = $this->config($data);
        if (null === $config) {
            return Answer::refused($this->words('fileupload.error.invalid_token'));
        }
        if (null === $file) {
            return Answer::refused($this->words('fileupload.error.no_file'));
        }

        return $this->store($config, $file, $error, true);
    }

    /** A picture fetched from the address the JSON body gives (`url`), stored as uploadByFile() stores one. */
    public function uploadByUrl(?string $data, ?string $url): Answer
    {
        $config = $this->config($data);
        if (null === $config) {
            return Answer::refused($this->words('fileupload.error.invalid_token'));
        }
        $path = null !== $url && '' !== $url ? fetch_url($url) : null;
        if (!$path) {
            return Answer::refused($this->words('fileupload.error.no_file'));
        }

        return $this->store($config, new \SplFileInfo($path), \UPLOAD_ERR_OK, false);
    }

    /**
     * A collaboration relay's ticket for one room (collab_live), for the account signed in, with the room's
     * own token handed out with its field.
     *
     * @param array<string, mixed> $body the request's JSON body: room, token
     */
    public function ticket(array $body): Answer
    {
        $room = $body['room'] ?? null;
        if (!\is_string($room) || '' === $room) {
            return new Answer(400, ['success' => self::STATUS_BAD, 'error' => 'Missing room.']);
        }
        $token = $body['token'] ?? null;
        if (!\is_string($token) || !$this->tokens->isValid($this->rooms->tokenId($room), $token)) {
            return new Answer(403, ['success' => self::STATUS_NOTOKEN, 'error' => $this->words('editor.error.invalid_token')]);
        }
        $user = $this->access?->getUser();
        if (!$user instanceof User) {
            return new Answer(403, ['success' => self::STATUS_BAD, 'error' => 'Not authenticated.']);
        }
        if (null === $this->tickets || !$this->tickets->isConfigured()) {
            return new Answer(503, ['success' => self::STATUS_BAD, 'error' => 'Collaboration relay is not configured.']);
        }

        return new Answer(200, [
            'success' => self::STATUS_OK,
            'ticket' => $this->tickets->mint($room, self::who($user)),
            'wsUrl' => $this->tickets->getWsUrl(),
        ]);
    }

    /**
     * One field of one record written outside its form (collab_autosave, and the relay's saves), with the
     * room's token or the relay's service token; refused with the current value when another save came
     * after the version the client read (409).
     *
     * @param array<string, mixed> $body the request's JSON body: fqcn, id, field, locale, value, baseVersion, token or serviceToken and room
     */
    public function autosave(array $body): Answer
    {
        $fqcn = $body['fqcn'] ?? null;
        $id = $body['id'] ?? null;
        $field = $body['field'] ?? null;
        $locale = $body['locale'] ?? null;
        $value = $body['value'] ?? null;
        $baseVersion = $body['baseVersion'] ?? null;
        if (!\is_string($fqcn) || !\is_scalar($id) || !\is_string($field) || (null !== $locale && !\is_string($locale))) {
            return new Answer(400, ['success' => self::STATUS_BAD, 'error' => 'Invalid autosave target.']);
        }

        // The target's own room: its token was handed out with the form of that one field.
        $room = $this->rooms->buildRoom($fqcn, $id, $field, $locale);
        $token = $body['token'] ?? null;
        $serviceToken = $body['serviceToken'] ?? null;
        $authorized = (\is_string($token) && $this->tokens->isValid($this->rooms->tokenId($room), $token))
            // The relay's save: for the room it names, and that room must be this target.
            || (\is_string($serviceToken) && ($body['room'] ?? null) === $room && null !== $this->tickets && $this->tickets->verifyServiceToken($serviceToken, $room));
        if (!$authorized) {
            return new Answer(403, ['success' => self::STATUS_NOTOKEN, 'error' => $this->words('editor.error.invalid_token')]);
        }
        if (!class_exists($fqcn) || !UnitOfWorks::shapes($this->unitOfWork)->has($fqcn)) {
            return new Answer(400, ['success' => self::STATUS_BAD, 'error' => 'Invalid autosave target.']);
        }
        $entity = UnitOfWorks::repositories($this->unitOfWork)->of($fqcn)->find($id);
        if (!$entity) {
            return new Answer(404, ['success' => self::STATUS_BAD, 'error' => 'Entity not found.']);
        }

        // A translatable record's text is its translation's in that language, not the record's.
        $target = ($locale && method_exists($entity, 'translate')) ? $entity->translate($locale) : $entity;
        if (!$target) {
            return new Answer(400, ['success' => self::STATUS_BAD, 'error' => 'Unknown locale.']);
        }
        $accessor = PropertyAccess::createPropertyAccessor();
        if (!$accessor->isReadable($target, $field) || !$accessor->isWritable($target, $field)) {
            return new Answer(400, ['success' => self::STATUS_BAD, 'error' => 'Unknown field.']);
        }

        $currentValue = $accessor->getValue($target, $field);
        $currentVersion = $this->rooms->hash($currentValue);
        // A save came after the version this client read: refused, the current value returned to choose from.
        if ($baseVersion && $currentVersion !== $baseVersion) {
            return new Answer(409, ['success' => self::STATUS_BAD, 'conflict' => true, 'value' => $currentValue, 'version' => $currentVersion]);
        }

        $accessor->setValue($target, $field, $value);
        $this->unitOfWork->flush();

        return new Answer(200, ['success' => self::STATUS_OK, 'version' => $this->rooms->hash($value)]);
    }

    /**
     * An account as the relay and the editor's collaboration show it.
     *
     * @return array{id: mixed, name: string, avatar: mixed, color: mixed}
     */
    public static function who(User $user): array
    {
        return ['id' => $user->getId(), 'name' => (string) $user, 'avatar' => $user->getAvatar(), 'color' => $user->getColor()];
    }

    /**
     * A page of records whose identifier holds the query, five by page.
     *
     * @param array<string, mixed>                  $body
     * @param \Closure(object): array<string, mixed> $entry
     * @param list<string>                          $fields
     */
    private function mentions(?string $data, string $method, array $body, int|string|null $page, string $class, bool $slugged, \Closure $entry, array $fields = []): Answer
    {
        if (null === $this->config($data)) {
            return Answer::refused($this->words('editor.error.invalid_token'));
        }
        $page = \intval($body['page'] ?? $page);
        $page = $page ?: 1;
        $query = $body['query'] ?? null;
        if ($slugged) {
            $query = (string) $this->slugger->slug((string) $query);
        }
        $expected = ($this->debug ?? (bool) \Omnibridge\Runtime::find(\Omnibridge\Contract\Environment::class)?->isDebug()) ? ['GET', 'POST'] : ['POST'];
        if (!\in_array(strtoupper($method), $expected, true) || !$query || null === $this->paginator) {
            return Answer::refused($this->words('editor.error.invalid_query'));
        }

        $items = [];
        $records = $this->paginator->paginate(UnitOfWorks::repositories($this->unitOfWork)->of($class)->cacheByInsensitiveIdentifier($query, $fields), $page, 5);
        foreach ($records as $record) {
            $items[] = ['id' => $record->getId(), 'label' => $record->__toString()] + $entry($record) + [
                'data' => [$this->obfuscator->encode(['id' => $record->getId(), 'className' => \get_class($record)])],
            ];
        }

        return new Answer(200, [
            'success' => self::STATUS_OK,
            'results' => $items,
            'pagination' => ['page' => $page, 'more' => $page > 0 && $page < $records->getTotalPages()],
        ]);
    }

    /**
     * A file stored in the editor's storage: under a uuid's subfolders (a file sent), or named by the uuid
     * (one fetched), its extension its type's.
     *
     * @param array<string, mixed> $config
     */
    private function store(array $config, \SplFileInfo $file, int $error, bool $subdivide): Answer
    {
        if (\UPLOAD_ERR_OK !== $error) {
            return Answer::refused(isset(self::ERRORS[$error]) ? $this->words(self::ERRORS[$error]) : 'Unknown error during upload.');
        }
        if (\array_key_exists('maxFilesize', $config) && $file->getSize() > 1e6 * $config['maxFilesize']) {
            return Answer::refused($this->words('fileupload.error.too_big'));
        }

        $uuid = Uuid::v4();
        $mimeType = mime_content_type2($file->getPathname());
        $extension = $mimeType ? (new MimeTypes())->getExtensions($mimeType)[0] ?? null : null;
        $path = '/'.($subdivide ? path_subdivide(str_replace('-', '', (string) $uuid), 5, 2) : $uuid).($extension ? '.'.$extension : '');
        if (!file_exists($file->getPathname())) {
            return Answer::refused('Uploaded file lost in the limbo.');
        }
        if (!$this->storage || null === $this->storages) {
            return Answer::refused('No storage provided.');
        }
        if (!$this->storages->write($path, (string) file_get_contents((string) $file->getRealPath()), $this->storage)) {
            return Answer::refused('Repository directory not writable.');
        }

        // A local storage: its public link's address; a remote one (S3, MinIO): through the image route, its
        // storage in the address, which streams the source and keeps the derivative here.
        $public = $this->storages->getPublic($path, $this->storage);
        $url = null !== $public
            ? str_lstrip($public, $this->storages::getPublicDir())
            : $this->mediaService?->image($path, ['storage' => $this->storage]);

        unlink((string) $file->getRealPath());

        return new Answer(200, ['success' => self::STATUS_OK, 'file' => ['url' => $url]]);
    }

    /** The decoded data when its token holds; null otherwise. @return array<string, mixed>|null */
    private function config(?string $data): ?array
    {
        $config = null !== $data ? $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT) : null;
        $token = \is_array($config) ? ($config['token'] ?? null) : null;

        return $token && $this->tokens->isValid(self::TOKEN, (string) $token) ? $config : null;
    }

    private function isAdmin(): bool
    {
        try {
            return (bool) $this->access?->isGranted(UserRole::ADMIN);
        } catch (\Throwable) {
            return false; // no one signed in where the host asks for a token first
        }
    }

    /** A record's noun and id (an article #12). */
    private function noun(object $record): string
    {
        return ($this->translator?->transEntity($record) ?? '').' #'.$record->getId();
    }

    private function words(string $key): string
    {
        return null !== $this->translator ? $this->translator->trans($key, [], 'fields') : $key;
    }
}
