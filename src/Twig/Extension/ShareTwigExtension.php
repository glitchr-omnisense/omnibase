<?php

namespace Base\Twig\Extension;

use Base\Model\LinkableInterface;
use Base\Service\Sharer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ShareTwigExtension extends AbstractExtension
{
    public function __construct(Sharer $sharer)
    {
        $this->sharer = $sharer;
    }

    public function getName() { return 'share_extension'; }
    public function getFilters():array
    {
        return [
            new TwigFilter('share', [$this, 'share'], ['is_safe' => ['all']]),
        ];
    }
    public function getFunctions():array
    {
        return [
            new TwigFilter('share', [Sharer::class, 'share'], ['is_safe' => ['all']]),
        ];
    }

    public function share(LinkableInterface $url, string $identifier, array $options = [], ?string $template = null): ?string
    {
        return $this->sharer->share($identifier, $url, $options, $template);
    }
}
