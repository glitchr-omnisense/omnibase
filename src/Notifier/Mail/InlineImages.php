<?php

namespace Base\Notifier\Mail;

use Omnibridge\Contract\Views;
use Omnibridge\Contract\ViewSources;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Twig\Environment;

/**
 * The `email` an e-mail's template is given where Symfony's Twig bridge is
 * not (EmailComposer::compose()): a picture of the template's paths embedded
 * as an inline part, its cid: address returned - what
 * Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail::image() does, which
 * omnibase's embed() asks for. The picture is found by the views' engine
 * (omnibridge's ViewSources: Twig's loader through TwigViews), since step F5a.
 */
final class InlineImages
{
    private readonly Views $views;

    /** @param Views|Environment $views Twig's environment is deprecated (until 6.0) */
    public function __construct(Views|Environment $views, private readonly Email $message)
    {
        $this->views = EmailComposer::views($views);
    }

    public function image(string $image, ?string $contentType = null, ?string $name = null): string
    {
        if (!$this->views instanceof ViewSources) {
            throw new \LogicException(sprintf('The views (%s) cannot say where "%s" is: an e-mail embeds a picture its engine finds (omnibridge\'s ViewSources).', get_debug_type($this->views), $image));
        }
        $path = $this->views->sourcePath($image);
        $body = null !== $path ? new File($path) : $this->views->sourceCode($image);
        $dataPart = (new DataPart($body, $name ?: basename($image), $contentType))->asInline();
        $this->message->addPart($dataPart);

        return 'cid:'.$dataPart->getContentId();
    }

    public function getSubject(): ?string
    {
        return $this->message->getSubject();
    }
}
