<?php

namespace Base\Field\Behaviour;

use Base\Database\Attribute\Uploader;
use Base\Database\UnitOfWorks;
use Base\Field\Kind;
use Base\Field\Upload\Dropzone;
use Base\Service\FileService;
use Base\Service\MediaService;
use Base\Service\MediaServiceInterface;
use Omnibridge\Contract\Urls;
use Omnibridge\Form\Endpoint;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;

/**
 * A file sent with its form (plan "omnibase agnostic", step F5d): what omnibase's FileType and its
 * children (ImageType, AvatarType, AudioType, VideoType) decided, which they now ask - their
 * options, and what their blocks read of the record's files: the stored files' public addresses,
 * thumbnails, previews, download links, the largest size and the types accepted (the record's
 * #[Uploader] and #[File]), the dropzone's settings and its address (Base\Field\Upload\Dropzone),
 * the cropper's. The same on every host: the Symfony types read it, the neutral FormView gives it
 * to omnibridge/laravel's renderer.
 *
 * What a form sends back is FileType's compound - `file` (the stored file's name, or a dropzone's
 * uuids "|"-separated), `raw` (the file sent), `url` (a file to fetch, with allow_url) - read by
 * reverse() as FileType reads it (mapFormsToData(), then SUBMIT): the file sent, else the one
 * fetched, else what `file` names (a file the dropzone kept, or the name of one already stored);
 * nothing is null. The record's #[Uploader] stores the file when it is flushed.
 *
 * vars() reads the record from the options FormView and FileType give it: upload_record (the
 * form's data), upload_property (the field's name), upload_data (its value, for data that is not
 * a record), and upload_media and upload_files (the host's MediaService and FileService, where its runtime does not give
 * them).
 */
final class Upload extends AbstractBehaviour
{
    public const FILE = 'file';
    public const IMAGE = 'image';
    public const AVATAR = 'avatar';
    public const AUDIO = 'audio';
    public const VIDEO = 'video';

    /** FileType's defaults (its sortable-js, the Symfony parameter of the script's address, stays its own). */
    public const DEFAULTS = [
        'class' => null,
        'empty_data' => null,
        'webpack_entry' => 'form.dropzone',
        'dropzone' => [],
        'allow_delete' => true,
        'allow_delete[confirmation]' => true,
        'allow_cancel[confirmation]' => true,
        'multiple' => null,
        'clipboard' => false,
        'href' => null,
        'title' => null,
        'allow_url' => false,
        'allow_reupload' => true,
        'sortable' => null,
        'lightbox' => ['resizeDuration' => 500, 'fadeDuration' => 250, 'imageFadeDuration' => 100],
        'alt' => null,
        'thumbnail_width' => null,
        'thumbnail_height' => 250,
        'max_size' => null,
        'max_files' => null,
        'mime_types' => [],
        'data_mapping' => null,
        'parallel_uploads' => 5,
        'upload_multiple' => false,
        'inline' => false,
    ];

    /** What each child type sets over FileType's defaults. */
    public const VARIANTS = [
        self::FILE => [],
        self::IMAGE => ['thumbnail' => 'bundles/base/images/image.svg', 'clipboard' => true, 'modal' => ['keyboard' => false, 'backdrop' => 'static'], 'cropper' => null, 'mime_types' => ['image/*'], 'inline' => false],
        self::AVATAR => ['thumbnail' => 'bundles/base/images/user.svg', 'clipboard' => false, 'modal' => ['keyboard' => false, 'backdrop' => 'static'], 'cropper' => null, 'mime_types' => ['image/*'], 'lightbox' => null, 'inline' => true],
        self::AUDIO => ['mime_types' => ['audio/*'], 'multisource' => true, 'lightbox' => null],
        self::VIDEO => ['mime_types' => ['video/*'], 'multisource' => true, 'lightbox' => null],
    ];

    /** The cropper's settings an image's cropper gets where it says nothing (ImageType). */
    public const CROPPER = ['viewMode' => 2, 'autoCropArea' => true, 'movable' => false, 'zoomable' => false, 'rotatable' => false, 'scalable' => false];

    /** The fields' words of a dropzone, by its option (FileType). */
    private const WORDS = [
        'dictDefaultMessage' => 'default_message', 'dictFallbackMessage' => 'fallback_message', 'dictFileTooBig' => 'file_too_big',
        'dictInvalidFileType' => 'invalid_file_type', 'dictMaxFilesExceeded' => 'max_files_exceeded', 'dictResponseError' => 'response_error',
        'dictCancelUpload' => 'cancel_upload', 'dictRemoveFile' => 'remove_file',
    ];

    public function __construct(public readonly string $variant = self::FILE)
    {
    }

    /** The variant of a kind (file, image, avatar, audio, video); null for a kind that is no upload of its own. */
    public static function variantOf(?string $kind): ?string
    {
        return match ($kind) {
            Kind::FILE => self::FILE, Kind::IMAGE => self::IMAGE, Kind::AVATAR => self::AVATAR,
            Kind::AUDIO => self::AUDIO, Kind::VIDEO => self::VIDEO, default => null,
        };
    }

    public function isImage(): bool
    {
        return \in_array($this->variant, [self::IMAGE, self::AVATAR], true);
    }

    protected function defaults(): array
    {
        return array_replace(self::DEFAULTS, self::VARIANTS[$this->variant] ?? []);
    }

    public function options(array $given): array
    {
        $options = parent::options($given);
        // The types' normalizers: the class the form's data class, a sound's or a film's several sources, the
        // clipboard for a single file or a dropzone, an avatar's cropper square.
        $options['class'] ??= $options['data_class'] ?? null;
        if (\in_array($this->variant, [self::AUDIO, self::VIDEO], true) && null === $options['multiple']) {
            $options['multiple'] = $options['multisource'];
        }
        $options['clipboard'] = $options['clipboard'] ? (!$options['multiple'] || null !== $options['dropzone']) : false;
        if (self::AVATAR === $this->variant && \is_array($options['cropper']) && !\array_key_exists('aspectRatio', $options['cropper'])) {
            $options['cropper']['aspectRatio'] = 1;
        }

        return $options;
    }

    protected function controller(): ?string
    {
        return match ($this->variant) {
            self::AVATAR => 'avatar',
            self::IMAGE => 'image',
            default => 'file',
        };
    }

    public function endpoints(array $options): array
    {
        // A dropzone sends each file as it is dropped; an image's cropper sends the cropped one the same way.
        return \is_array($options['dropzone'] ?? null) && (true === ($options['multiple'] ?? null) || \is_array($options['cropper'] ?? null))
            ? ['dropzone' => new Endpoint('ux_dropzone', [], 'POST')] : [];
    }

    /**
     * What FileType's block reads (and ImageType's, AvatarType's, AudioType's): files, value (the stored
     * files' names, "|"-separated for several), path, previews, download, clippable, max_size, max_files,
     * mime_types, the dropzone's settings and its ajax address; an image's thumbnail, modal and cropper.
     */
    public function vars(array $options, mixed $shown, string $id): array
    {
        $entity = $options['upload_record'] ?? null;
        $name = (string) ($options['upload_property'] ?? '');
        $mapping = $options['data_mapping'] ?? $name;
        $multiple = (bool) ($options['multiple'] ?? false);
        // The media rule, its files' side (isImage(), linkable(), downloadable()): the host's (upload_media), else the runtime's.
        $media = $options['upload_media'] ?? Runtime::find(MediaServiceInterface::class);
        $media = $media instanceof MediaService ? $media : null;
        // Its files' addresses as FileType gives them: the file service's (a download is a /contents/ address), not the media's.
        $fileService = $options['upload_files'] ?? (null !== $media ? cast($media, FileService::class) : null);
        $fileService = $fileService instanceof FileService ? $fileService : null;

        $vars = ['inline' => $options['inline'], 'lightbox' => \is_array($options['lightbox']) ? json_encode($options['lightbox']) : null];
        if ($options['upload_entity'] ?? self::isRecord($entity)) {
            $files = Uploader::get($entity, $name);
            $files = array_map(static fn ($file) => $file ? $file->getPath() : null, \is_array($files) ? $files : [$files]);
            if ($multiple && 'array' != Uploader::getTypeOfField($entity, $name)) {
                $vars['max_files'] = 1;
            }
        } else {
            $files = $options['upload_data'] ?? null;
        }
        if (!\is_array($files)) {
            $files = $files ? [$files] : [];
        }
        $vars['files'] = array_filter($files);
        $vars['allow_reupload'] = $options['allow_reupload'];
        $vars['allow_url'] = $options['allow_url'];
        $vars['max_files'] ??= $options['max_files'];

        // The constraint's ceiling (FileType::buildForm()), the record's #[Uploader] and #[File] narrowing PHP's.
        $maxSize = Uploader::getMaxFilesize($options['class'] ?? $entity ?? null, $mapping);
        if (!empty($options['max_size'])) {
            $maxSize = min($maxSize, $options['max_size']);
        }
        $vars['max_size'] = $maxSize;
        $mimeTypes = $options['mime_types'];
        if (!$mimeTypes && $entity) {
            $mimeTypes = Uploader::getMimeTypes($options['class'] ?? $entity, $mapping);
        }
        $vars['mime_types'] = $mimeTypes;

        $value = Uploader::getPublic($entity ?? null, $mapping) ?? $files;
        $vars['clippable'] = $vars['path'] = $vars['download'] = json_encode([]);
        $vars['previews'] = json_encode([]);
        if (!\is_array($value) && $multiple) {
            $value = [$value];
        } elseif (\is_array($value) && !$multiple) {
            $value = first($value);
        }
        if (\is_array($value)) {
            if ($value) {
                $available = fn (?string $path): bool => self::available($path, $fileService);
                // By position, not by name: every image is stored as "<hash>/image.webp" (FileType's notes).
                $vars['path'] = json_encode(array_values(array_transforms(static fn ($k, $v): ?array => $available($v) ? [$k, $fileService->isImage($v) ? $media->thumbnail($v, 1024, 1024) : $fileService->linkable($v)] : null, array_filter($value))));
                $vars['previews'] = json_encode(array_values(array_transforms(static fn ($k, $v): ?array => $available($v) ? [$k, $fileService->isImage($v) ? $media->thumbnailOutbound($v, 320, 320) : $fileService->linkable($v)] : null, array_filter($value))));
                $vars['download'] = json_encode(array_transforms(static fn ($k, $v): ?array => $available($v) ? [basename($v), $fileService->downloadable($v)] : null, array_filter($value)));
                $vars['clippable'] = json_encode(array_transforms(static fn ($k, $v): ?array => $available($v) ? [basename($v), $fileService->isImage($v)] : null, array_filter($value)));
            }
            $value = implode('|', array_map(static fn ($v) => null !== $v ? basename($v) : null, $value));
        } else {
            $vars['path'] = $value;
            $vars['previews'] = $value;
            $vars['download'] = $fileService?->downloadable($value);
            $vars['clippable'] = $fileService?->isImage($value);
        }
        $vars['value'] = $value;
        // The stored file's name, what its hidden input carries (FileType::mapDataToForms()).
        $vars['file_value'] = \is_string($value) && !$multiple && '' !== $value ? basename($value) : (\is_string($value) ? $value : null);

        $vars['clipboard'] = $options['clipboard'];
        $vars['sortable'] = true;
        $vars['dropzone'] = null;
        $vars['ajax'] = null;
        $vars['multiple'] = $multiple;
        $vars['allow_delete'] = $options['allow_delete'];
        $vars['href'] = $options['href'];
        $vars['confirm_action'] = $options['allow_cancel[confirmation]'] || $options['allow_delete[confirmation]'];

        if (\is_array($options['dropzone']) && $multiple) {
            $vars['attr'] = ['class' => 'dropzone'];
            $dropzone = $options['dropzone'];
            if (!\array_key_exists('url', $dropzone)) {
                $dropzone['url'] = !empty($options['action']) ? $options['action'] : '.';
            }
            if (null !== $options['allow_delete']) {
                $dropzone['addRemoveLinks'] = $options['allow_delete'];
            }
            $dropzone['maxFilesize'] = $maxSize / 1e6; // from B to MB
            if (null !== $options['max_files']) {
                $dropzone['maxFiles'] = $options['max_files'];
            }
            if ($mimeTypes) {
                $dropzone['acceptedFiles'] = implode(',', $mimeTypes);
            }
            $dropzone['parallelUploads'] ??= $options['parallel_uploads'];
            $dropzone['uploadMultiple'] ??= $options['upload_multiple'];
            $dropzone['thumbnailWidth'] = $options['thumbnail_width'] ?? null;
            $dropzone['thumbnailHeight'] = $options['thumbnail_height'] ?? null;
            foreach (self::WORDS as $option => $key) {
                $dropzone[$option] ??= self::trans('fileupload.dropzone.'.$key, [], 'fields');
            }
            if ($options['allow_cancel[confirmation]']) {
                $dropzone['dictCancelUploadConfirmation'] ??= self::trans('fileupload.dropzone.cancel_upload_confirmation', [], 'fields');
            }
            if ($options['allow_delete[confirmation]']) {
                $dropzone['dictRemoveFileConfirmation'] ??= self::trans('fileupload.dropzone.remove_file_confirmation', [], 'fields');
            }
            if (\array_key_exists('maxFiles', $dropzone) && !empty($value)) {
                $dropzone['maxFiles'] -= \count(explode('|', $value));
            }

            $vars['ajax'] = self::address($dropzone);
            $dropzone['url'] = $vars['ajax'];
            $vars['dropzone'] = json_encode($dropzone);
            $vars['sortable'] = json_encode($options['sortable']);
        }

        if (\in_array($this->variant, [self::AUDIO, self::VIDEO], true) && !$vars['mime_types']) {
            $vars['mime_types'] = $this->variant.'/*';
        }
        if ($this->isImage()) {
            if (!$vars['mime_types']) {
                $vars['mime_types'] = 'image/*';
            }
            $vars['thumbnail'] = self::asset((string) $options['thumbnail']);
            $vars['modal'] = json_encode($options['modal']);
            $vars['cropper'] = null;
            if (\is_array($options['cropper'])) {
                $vars['ajax'] = self::address(\is_array($options['dropzone']) ? $options['dropzone'] : []);
                $vars['cropper'] = json_encode($options['cropper'] + self::CROPPER);
            }
        }
        if (self::AVATAR === $this->variant) {
            $vars['avatar'] = $vars['files'][0] ?? null;
            $vars['files'] = [];
        }

        return $vars;
    }

    /**
     * What the form sent, as FileType reads it: the file sent (raw), else the one fetched (url), else what
     * `file` names - a dropzone's uuids its kept files, a stored file's name itself; null for nothing.
     * A value that is no compound (a plain file input) is read as it is.
     */
    public function reverse(mixed $submitted, array $options): mixed
    {
        if (!\is_array($submitted)) {
            return '' === $submitted ? null : $submitted;
        }
        $raw = ($options['allow_reupload'] ?? true) ? ($submitted['raw'] ?? null) : null;
        if (\is_array($raw)) {
            $raw = array_values(array_filter($raw, static fn ($file): bool => $file instanceof \SplFileInfo)) ?: null;
        }
        $url = null;
        if ($options['allow_url'] ?? false) {
            $url = \is_string($submitted['url'] ?? null) && filter_var($submitted['url'], \FILTER_VALIDATE_URL) ? fetch_url($submitted['url']) : null;
        }
        $file = $submitted['file'] ?? null;
        $data = ($raw ?: null) ?? ($url ?: null) ?? (\is_string($file) && '' !== $file ? $file : null);
        if (\is_string($data)) {
            $dropzone = Runtime::find(Dropzone::class);
            $data = Dropzone::files($data, $dropzone instanceof Dropzone ? $dropzone->directory() : sys_get_temp_dir().'/omnibase-dropzone', (bool) ($options['multiple'] ?? false));
        }

        return empty($data) ? null : $data;
    }

    /**
     * The file sent refused as FileType's constraint refuses it (Base\Validator\Constraints\File on its
     * `raw` part, when the form is validated): a type its `mime_types` do not match, else a size over the
     * ceiling - PHP's upload limit, narrowed by the class' #[Uploader] and #[File] (its `class` option) and its
     * `max_size`. Its message and parameters are the constraint's (the validators catalogue: file.mime_type,
     * file.max_size); null for a file accepted, for none sent, for several (the constraint reads one file).
     *
     * @param array<string, mixed> $options the field's options, completed
     *
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    public function refusal(mixed $submitted, array $options, string $property): ?array
    {
        $file = \is_array($submitted) ? ($submitted['raw'] ?? null) : null;
        if (!$file instanceof \SplFileInfo || !($options['allow_reupload'] ?? true) || !is_file($file->getPathname())) {
            return null;
        }
        $mimeTypes = array_values((array) ($options['mime_types'] ?? []));
        $maxSize = Uploader::getMaxFilesize($options['class'] ?? null, (string) ($options['data_mapping'] ?? $property));
        if (!empty($options['max_size'])) {
            $maxSize = min($maxSize, (int) $options['max_size']);
        }

        $type = method_exists($file, 'getMimeType') ? $file->getMimeType() : (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname());
        $compatible = [] === $mimeTypes;
        foreach ($mimeTypes as $mimeType) {
            $compatible = $compatible || (bool) preg_match('/'.str_replace('/', '\/', (string) $mimeType).'/', (string) $type);
        }
        if (!$compatible) {
            return ['file.mime_type', ['{0}' => \count($mimeTypes), '{1}' => implode(', ', array_map(static fn (string $mimeType): string => (string) last(explode('/', $mimeType)), $mimeTypes))]];
        }
        $size = (int) $file->getSize();
        if ($size > $maxSize) {
            return ['file.max_size', ['{1}' => byte2str($size), '{0}' => byte2str($maxSize)]];
        }

        return null;
    }

    /** Whether the form's data is a record the persistence maps (FileType: the metadata's isEntity()). */
    private static function isRecord(mixed $data): bool
    {
        $unitOfWork = Runtime::find(UnitOfWork::class);
        if (!\is_object($data) || !$unitOfWork instanceof UnitOfWork) {
            return false;
        }
        try {
            return UnitOfWorks::shapes($unitOfWork)->has($data::class);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether the storage sees a file (FileType::fileIsAvailable()): on the local disk, or through the
     * media's storages (a remote one answers its MIME type).
     */
    private static function available(?string $path, ?FileService $files): bool
    {
        if (null === $path || '' === $path || null === $files) {
            return false;
        }
        if (file_exists($path)) {
            return true;
        }
        try {
            return null !== $files->getMimeType($path);
        } catch (\Throwable) {
            return false;
        }
    }

    /** The dropzone's address on this host (ux_dropzone with its data); null where it has no such route or service. */
    private static function address(array $settings): ?string
    {
        $dropzone = Runtime::find(Dropzone::class);
        $urls = Runtime::find(Urls::class);
        if (!$dropzone instanceof Dropzone || !$urls instanceof Urls) {
            return null;
        }
        try {
            return $urls->path('ux_dropzone', ['data' => $dropzone->data($settings)]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** A file of the bundles' public folder, as the page links it (omnibase's AssetPaths, the host's Urls otherwise). */
    private static function asset(string $path): string
    {
        $assets = Runtime::find(\Base\View\AssetPaths::class);
        if ($assets instanceof \Base\View\AssetPaths) {
            return $assets->getAsset($path);
        }

        return '/'.ltrim($path, '/');
    }
}
