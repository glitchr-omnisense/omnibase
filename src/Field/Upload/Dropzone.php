<?php

namespace Base\Field\Upload;

use Base\Database\Attribute\Uploader;
use Base\Field\Search\Answer;
use Base\Service\ObfuscatorInterface;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Symfony\Component\Uid\Uuid;

/**
 * A file field's uploads before its form is sent, answered for any host (plan "omnibase
 * agnostic", step F5d): what omnibase's DropzoneController answered under Symfony, moved here as
 * it was, so that every host takes a dropzone's files at the same addresses (ROUTES) with the
 * same answers.
 *
 * - data(): what a dropzone hands its address (the route's `data`), obfuscated (Obfuscator, short
 *   form) - the dropzone's settings and a token of intent "dropzone" (the Tokens contract) that
 *   every action checks first. FileType gives it under Symfony, the Upload behaviour elsewhere.
 * - store(): the file sent under `file` kept in the cache folder's dropzone/ under a new uuid -
 *   {status, uuid, mime_type, size, error} - or the reason it is refused, in the fields' words.
 * - preview(): a kept file by its uuid, to show it again; delete(): one removed.
 * - files(): what the form sends back - the uuids it kept, "|"-separated - as the files to store
 *   (FileType's SUBMIT): a kept file, or the name of one the record holds already.
 *
 * The host's controller - Symfony's DropzoneController, omnibridge/laravel's UploadController -
 * hands the request's file and sends the Answer (a file's path for a preview).
 */
class Dropzone
{
    /** The dropzone's routes, in the order DropzoneController declares them: name => [path, action]. */
    public const ROUTES = [
        'ux_dropzone' => ['/ux/dropzone/{data}', 'store'],
        'ux_dropzone_preview' => ['/ux/dropzone/{data}/{uuid}', 'preview'],
        'ux_dropzone_delete' => ['/ux/dropzone/{data}/{uuid}/delete', 'delete'],
    ];

    /** The intent of the token every action checks. */
    public const TOKEN = 'dropzone';

    public const STATUS_OK = 'OK';
    public const STATUS_BAD = 'BAD';
    public const STATUS_NOTOKEN = 'NO_TOKEN';

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

    public function __construct(
        private readonly ObfuscatorInterface $obfuscator,
        private readonly Tokens $tokens,
        private readonly string $cacheDir,
        private readonly ?Translations $translations = null,
    ) {
    }

    /** The folder the uploads wait in until their form is sent. */
    public function directory(): string
    {
        return $this->cacheDir.'/dropzone';
    }

    /**
     * What a dropzone hands its address (the route's `data`): its settings and the visitor's token.
     *
     * @param array<string, mixed> $settings Dropzone's options (maxFilesize in MB, acceptedFiles...)
     */
    public function data(array $settings): string
    {
        return $this->obfuscator->encode(array_merge($settings, ['token' => $this->tokens->csrf(self::TOKEN)]), ObfuscatorInterface::USE_SHORT);
    }

    /**
     * The file sent, kept under a new uuid. $file is the host's uploaded file (Symfony's UploadedFile,
     * Laravel's); $error its UPLOAD_ERR_* when the host tells it apart, $moved how to move it where it is
     * kept (move_uploaded_file() by default, which takes only a file PHP received).
     *
     * @param (\Closure(string $from, string $to): bool)|null $moved
     */
    public function store(string $data, ?\SplFileInfo $file, int $error = \UPLOAD_ERR_OK, ?\Closure $moved = null): Answer
    {
        $config = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);
        $token = \is_array($config) ? ($config['token'] ?? null) : null;
        if (!$token || !$this->tokens->isValid(self::TOKEN, (string) $token)) {
            return Answer::refused($this->words('fileupload.error.invalid_token'));
        }
        if (null === $file) {
            return Answer::refused($this->words('fileupload.error.no_file'));
        }
        if (\UPLOAD_ERR_OK !== $error) {
            return Answer::refused(isset(self::ERRORS[$error]) ? $this->words(self::ERRORS[$error]) : 'Unknown error during upload.');
        }
        $size = $file->getSize();
        if (\array_key_exists('maxFilesize', $config) && $size > 1e6 * $config['maxFilesize']) {
            return Answer::refused($this->words('fileupload.error.too_big'));
        }

        $directory = $this->directory();
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            return Answer::refused($this->words('fileupload.error.cant_write'));
        }
        $uuid = (string) Uuid::v4();
        $metadata = ['status' => self::STATUS_OK, 'uuid' => $uuid, 'mime_type' => Uploader::getFileMimeType($file), 'size' => $size, 'error' => $error];

        if (!is_writable(\dirname($file->getPathname()))) {
            return Answer::refused('Repository directory not writable.');
        }
        if (!file_exists($file->getPathname())) {
            return Answer::refused('Uploaded file lost in the limbo.');
        }
        $moved ??= static fn (string $from, string $to): bool => move_uploaded_file($from, $to);
        if (!$moved((string) $file->getRealPath(), $directory.'/'.$uuid)) {
            return Answer::refused($this->words('fileupload.error.cant_write'));
        }

        return new Answer(200, $metadata);
    }

    /** A kept file's path, to send it again; an Answer when it is refused, null when there is none. */
    public function preview(string $data, string $uuid): Answer|string|null
    {
        if (null !== $refusal = $this->check($data, $uuid)) {
            return $refusal;
        }
        $path = $this->directory().'/'.$uuid;

        return file_exists($path) ? $path : null;
    }

    /** A kept file removed. */
    public function delete(string $data, string $uuid): Answer
    {
        if (null !== $refusal = $this->check($data, $uuid)) {
            return $refusal;
        }
        $path = $this->directory().'/'.$uuid;
        if (file_exists($path)) {
            unlink($path);
        }

        return new Answer(200, ['status' => self::STATUS_OK, 'uuid' => $uuid]);
    }

    /**
     * What a dropzone's form sends back ("uuid|uuid"), as FileType's SUBMIT reads it: each uuid a file
     * kept here (a file to store), or the name of a file the record holds already; one value, or the
     * list of them for a field holding several; null for nothing.
     *
     * @return list<\SplFileInfo|string|null>|\SplFileInfo|string|null
     */
    public static function files(string $submitted, string $directory, bool $multiple): array|\SplFileInfo|string|null
    {
        $files = [];
        foreach (explode('|', $submitted) as $uuid) {
            $path = '' !== $uuid ? $directory.'/'.$uuid : $uuid;
            $files[] = '' !== $path && file_exists($path) ? Uploader::file($path) : ('' !== $path ? basename($path) : null);
        }
        if (!$multiple) {
            $files = $files[0] ?? null;
        }

        return empty($files) ? null : $files;
    }

    /** The token and the uuid a preview or a removal is asked with: null when both hold. */
    private function check(string $data, string $uuid): ?Answer
    {
        $config = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);
        $token = \is_array($config) ? ($config['token'] ?? null) : null;
        if (!$token) {
            return Answer::refused('Invalid CSRF token.', 403);
        }
        if (!$this->tokens->isValid(self::TOKEN, (string) $token)) {
            return Answer::refused('Invalid token.');
        }
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $uuid)) {
            return Answer::refused('Invalid uuid.');
        }

        return null;
    }

    private function words(string $key): string
    {
        return null !== $this->translations ? $this->translations->trans($key, [], 'fields') : $key;
    }
}
