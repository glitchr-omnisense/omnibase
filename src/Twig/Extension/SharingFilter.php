<?php

namespace Base\Twig\Extension;

use Base\Service\Model\LinkableInterface;
use Base\Service\Sharing;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SharingFilter extends AbstractExtension
{
    /**
     * @var Sharing
     */
    protected $sharing;

    public function __construct(Sharing $sharing)
    {
        $this->sharing = $sharing;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'sharing_filter';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('share', [$this, 'generate'], ['is_safe' => ['all']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFilter('share', [Sharing::class, 'generate'], ['is_safe' => ['all']]),
        ];
    }

    public function generate(LinkableInterface $url, string $identifier, array $options = [], ?string $template = null): ?string
    {
        return $this->sharing->generate($identifier, $url, $options, $template);
    }
}
