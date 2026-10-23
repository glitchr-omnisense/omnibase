<?php

namespace Base\Twig\Extension;

use Base\Notifier\Mail\InlineImages;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * What omnibase's e-mail templates ask of Twig beyond Twig's own extras
 * (inky_to_html, inline_css): embed() - a picture of the site's public folder
 * (or of a Twig path) as the e-mail being written embeds it, its cid: address
 * through the template's `email` (Symfony's WrappedTemplatedEmail, or
 * InlineImages where omnibase writes the e-mail itself:
 * Base\Notifier\Mail\EmailComposer), else its public path -, |url (an
 * address made whole) and |inline_css_email (the styles inlined in an
 * e-mail). MediaTwigExtension gives them under Symfony (its embed() and
 * inline_css_email() are these lines); this extension gives them to a host
 * whose Twig has no MediaTwigExtension (omnibridge/laravel), for omnibase's
 * e-mails. Its templates find the public folder as @Public.
 */
final class MailTwigExtension extends AbstractExtension
{
    /** @param (\Closure(string $nameOrUrl, array<string, mixed> $parameters): string)|null $url an address whole: a route's name, a path or an address (AdvancedRouter::getUrl() under Symfony) */
    public function __construct(private readonly string $projectDir, private readonly ?\Closure $url = null)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('embed', $this->embed(...), ['needs_environment' => true, 'needs_context' => true]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('embed', $this->embed(...), ['needs_environment' => true, 'needs_context' => true]),
            new TwigFilter('url', $this->url(...)),
            new TwigFilter('inline_css_email', self::inlineCss(...), ['needs_context' => true, 'is_safe' => ['all']]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function embed(Environment $twig, array $context, string $src, array $options = []): string
    {
        return self::source($twig, $context, $src, $options, $this->projectDir);
    }

    /** @param array<string, mixed> $parameters */
    public function url(?string $name, array $parameters = []): ?string
    {
        if (null === $name || '' === $name) {
            return $name;
        }

        return trim(null !== $this->url ? ($this->url)($name, $parameters) : $name);
    }

    /**
     * The styles inlined into an e-mail's HTML (an e-mail being written: its `email`); a page's HTML as it is.
     *
     * @param array<string, mixed> $context
     */
    public static function inlineCss(array $context, string $body, string ...$css): string
    {
        static $inliner;
        if (null === $inliner) {
            $inliner = new CssToInlineStyles();
        }

        $email = $context['email'] ?? null;

        return $email ? $inliner->convert($body, implode("\n", $css)) : $body;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public static function source(Environment $twig, array $context, string $src, array $options, string $projectDir): string
    {
        if (!$src) {
            return $src;
        }

        if (!str_starts_with($src, '@')) {
            $src = '@Public/' . str_lstrip($src, [$projectDir . '/public', '/']);
        }

        $path = $src;
        $url = explode('/', $src);
        try {
            $path = $twig->getLoader()->getSourceContext($src)->getPath();
            $contentType = mime_content_type($twig->getLoader()->getSourceContext($src)->getPath());

            $url = explode('/', $twig->getLoader()->getSourceContext($src)->getName());
        } catch (LoaderError $e) {
            return $src;
        }

        $prefix = str_rstrip($path, [implode('/', tail($url)), '/']);
        $email = $options['email'] ?? $context['email'] ?? null;

        return $email instanceof WrappedTemplatedEmail || $email instanceof InlineImages ? $email->image($src, $contentType) : str_lstrip($path, [
            $prefix,
            $projectDir . '/public',
            $projectDir,
        ]);
    }
}
