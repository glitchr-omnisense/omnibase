<?php

namespace Base\Notifier\Mail;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Twig\Environment;

/**
 * The `email` an e-mail's template is given where Symfony's Twig bridge is
 * not (EmailComposer::compose()): a picture of the template's paths embedded
 * as an inline part, its cid: address returned - what
 * Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail::image() does, which
 * omnibase's embed() asks for.
 */
final class InlineImages
{
    public function __construct(private readonly Environment $twig, private readonly Email $message)
    {
    }

    public function image(string $image, ?string $contentType = null, ?string $name = null): string
    {
        $file = $this->twig->getLoader()->getSourceContext($image);
        $body = $file->getPath() ? new File($file->getPath()) : $file->getCode();
        $dataPart = (new DataPart($body, $name ?: basename($image), $contentType))->asInline();
        $this->message->addPart($dataPart);

        return 'cid:'.$dataPart->getContentId();
    }

    public function getSubject(): ?string
    {
        return $this->message->getSubject();
    }
}
