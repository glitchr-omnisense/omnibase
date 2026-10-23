<?php

namespace Base\Twig\Extension;

use Base\Controller\UX\MediaController;
use Base\Routing\AdvancedRouter;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\MediaService;
use Base\Service\Obfuscator;
use Base\Twig\Environment;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

final class MediaTwigExtension extends AbstractExtension
{
    protected AdvancedRouterInterface $router;

    protected MediaController $mediaController;

    /**
     * @var MediaService
     */
    protected $mediaService;

    protected string $projectDir;

    public function __construct(AdvancedRouterInterface $router, MediaService $mediaService, MediaController $mediaController, string $projectDir)
    {
        $this->projectDir = $projectDir;

        $this->router = $router;
        $this->mediaController = $mediaController;

        $this->mediaService = $mediaService;
        $this->mediaService->setController($mediaController);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('embed', [$this, 'embed'], ['needs_environment' => true, 'needs_context' => true]),
            new TwigFunction('url', [$this, 'url'], ['needs_context' => true]),

            new TwigFunction('asset', [AdvancedRouter::class, 'getAssetUrl']),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('ondisk', [$this, 'onDisk']),
        ];
    }

    public function getFilters(): array
    {
        return
            [
                new TwigFilter('inline_css_email', [$this, 'inline_css_email'], ['needs_context' => true, 'is_safe' => ['all']]),
                new TwigFilter('embed', [$this, 'embed'], ['needs_environment' => true, 'needs_context' => true]),
                new TwigFilter('url', [$this, 'url'], ['needs_context' => true]),

                new TwigFilter('asset', [AdvancedRouter::class, 'getAssetUrl']),
                new TwigFilter('obfuscate', [Obfuscator::class, 'encode']),
            ];
    }

    public function onDisk(string $file): bool
    {
        return file_exists(sanitize_url($this->projectDir . '/public/' . $file));
    }

    /**
     * The page's links (`|urlify`, `|linkify`): omnibase's router says
     * whether an address is the page's own (the link is then "highlight").
     */
    public function links(): LinkTwigExtension
    {
        return new LinkTwigExtension(fn ($urlOrPath): bool => $this->router->getUrl() == $this->router->getAssetUrl($urlOrPath));
    }

    /**
     * @param array $context
     * @param string|null $name
     * @param array $parameters
     * @param int $referenceType
     * @return string|null
     */
    public function url(array $context, ?string $name, array $parameters = [], int $referenceType = AdvancedRouter::ABSOLUTE_URL)
    {
        if (null == $name) {
            return $name;
        }

        $email = $context['email'] ?? null;
        $referenceType = $email instanceof WrappedTemplatedEmail ? AdvancedRouter::ABSOLUTE_URL : $referenceType;

        return trim($this->router->getUrl($name, $parameters, $referenceType));
    }

    public function inline_css_email(array $context, string $body, string ...$css): string
    {
        return MailTwigExtension::inlineCss($context, $body, ...$css);
    }

    /**
     * @param Environment $twig
     * @param array $context
     * @param string $src
     * @param array $options
     * @return string
     */
    public function embed(Environment $twig, array $context, string $src, array $options = [])
    {
        return MailTwigExtension::source($twig, $context, $src, $options, $this->projectDir);
    }
}
