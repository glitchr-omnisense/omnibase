<?php

namespace Base\Request;

use Base\Database\Repository\Repositories;
use Base\Entity\Layout\Widget\Attachment;
use Base\Screen\File;

/**
 * A widget's attachment downloaded (/attachment/{slug}), written once for every host (plan "omnibase agnostic", step
 * F5e, slice 5b; formerly WidgetController::Attachment()): its stored file, typed by its content (text/plain where
 * PHP's fileinfo is missing), downloaded under its slug; no such attachment, no such page (null).
 */
final class AttachmentDownload
{
    public function __construct(private readonly Repositories $repositories)
    {
    }

    public function file(string $slug): ?File
    {
        $attachment = $this->repositories->of(Attachment::class)->findOneBySlug($slug);
        if (!$attachment instanceof Attachment) {
            return null;
        }
        $path = (string) $attachment->getPublic();

        return new File($path, self::type($path));
    }

    /** What a file holds, as its content says (Symfony's FileinfoMimeTypeGuesser's answer), text/plain without fileinfo. */
    public static function type(string $path): string
    {
        if (!\function_exists('finfo_open')) {
            return 'text/plain';
        }
        $type = is_file($path) && is_readable($path) ? (new \finfo(\FILEINFO_MIME_TYPE))->file($path) : false;

        return \is_string($type) && '' !== $type ? $type : 'text/plain';
    }
}
